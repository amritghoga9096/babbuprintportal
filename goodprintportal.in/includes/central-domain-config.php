<?php
/*
|--------------------------------------------------------------------------
| GoodPrintPortal — CENTRAL API DOMAIN CLIENT
|--------------------------------------------------------------------------
|
| This module automatically fetches API_DOMAIN + GATEWAY_DOMAIN from a
| single central "API Domain Manager" panel. Results are cached locally
| so the site NEVER BREAKS even if the central panel is offline.
|
| Fallback chain:
|   1. Fresh fetch from central (if cache expired)
|   2. Cached result on disk
|   3. Hardcoded DEFAULTS (original pariprint.in + gateway.pdfgods.in)
|
| USAGE (from any code):
|   $api = get_central_api_domain();        // 'https://pariprint.in' (or new value)
|   $gw  = get_central_gateway_domain();    // 'https://gateway.pdfgods.in'
|   // Build an API endpoint dynamically:
|   $balanceUrl = rtrim($api, '/') . '/api-balance.php';
|
| Admin config (Settings page) saves these fields into `settings` table:
|   central_manager_url   → https://manager.yourdomain.com/
|   central_access_key    → cdm_...
|   central_cache_min     → (optional, default = 60 minutes)
|
*/

if (!defined('CENTRAL_DOMAIN_CLIENT_LOADED')) {
    define('CENTRAL_DOMAIN_CLIENT_LOADED', true);

    // -------------- FALLBACK DEFAULTS (if central panel fails) -----------------
    if (!defined('CENTRAL_DEFAULT_API_DOMAIN')) {
        define('CENTRAL_DEFAULT_API_DOMAIN', 'https://pariprint.in');
    }
    if (!defined('CENTRAL_DEFAULT_GATEWAY_DOMAIN')) {
        define('CENTRAL_DEFAULT_GATEWAY_DOMAIN', 'https://gateway.pdfgods.in');
    }
    if (!defined('CENTRAL_CACHE_LIFETIME_DEFAULT_SEC')) {
        define('CENTRAL_CACHE_LIFETIME_DEFAULT_SEC', 3600); // 1 hour default
    }

    // -------------- CACHE LOCATION (Dynamically find /storage dir) --------------
    function central_cache_path() {
        static $path = null;
        if ($path !== null) return $path;

        // Prefer: {site_root}/storage/central-cache.json
        $candidates = [
            dirname(__DIR__) . '/storage/central-cache.json',                 // includes/../storage  → goodprintportal.in/storage
            dirname(__DIR__) . '/cache/central-cache.json',
            dirname(__DIR__) . '/includes/central-cache.json',
            sys_get_temp_dir() . '/gpp-central-cache-' . md5(__FILE__) . '.json',
        ];

        foreach ($candidates as $c) {
            $dir = dirname($c);
            if (is_dir($dir) && is_writable($dir)) {
                $path = $c;
                break;
            }
            if (@mkdir($dir, 0755, true)) {
                @chmod($dir, 0755);
                $path = $c;
                break;
            }
        }
        if (!$path) $path = end($candidates); // final fallback (tmp)
        return $path;
    }

    // -------------- READ CONFIG FROM settings TABLE (if present) ----------------
    function central_read_site_config($override = []) {
        global $webdata, $ahk_conn;

        $out = [
            'central_manager_url'   => '',  // https://manager.yourdomain.com/
            'central_access_key'    => '',  // cdm_...
            'central_cache_min'     => (string)60,
        ];

        // 1. Try $webdata (pre-loaded by includes/config.php)
        if (is_array($webdata)) {
            foreach (array_keys($out) as $k) {
                if (isset($webdata[$k]) && (is_string($webdata[$k]) || is_numeric($webdata[$k]))) {
                    $out[$k] = (string)$webdata[$k];
                }
            }
        }

        // 2. Fallback - fetch from DB directly if any column missing
        if (!empty($GLOBALS['ahk_conn']) || (function_exists('ahkweb') && !empty($ahk_conn))) {
            $conn = $GLOBALS['ahk_conn'] ?? null;
            if ($conn) {
                $foundMissing = false;
                foreach (array_keys($out) as $k) {
                    if ($out[$k] === '' || $out[$k] === null) { $foundMissing = true; break; }
                }
                if ($foundMissing) {
                    $res = @mysqli_query($conn, "SHOW COLUMNS FROM settings");
                    if ($res) {
                        $cols = [];
                        while ($r = mysqli_fetch_row($res)) $cols[] = $r[0];
                        $toSelect = [];
                        foreach (array_keys($out) as $k) if (in_array($k, $cols, true)) $toSelect[] = $k;
                        if ($toSelect) {
                            $row = @mysqli_fetch_assoc(@mysqli_query($conn,
                                "SELECT " . implode(',', $toSelect) . " FROM settings WHERE id=1 LIMIT 1"
                            ));
                            if (is_array($row)) {
                                foreach ($row as $kk => $vv) $out[$kk] = (string)$vv;
                            }
                        }
                    }
                }
            }
        }

        // 3. Apply $override (typed values from Settings page AJAX form)
        if (is_array($override) && $override) {
            foreach ($override as $ok => $ov) {
                if (array_key_exists($ok, $out) && $ov !== null && $ov !== '') {
                    $out[$ok] = (string)$ov;
                }
            }
        }

        // 4. Defensive: ensure cache_min is int-like
        $cm = (int)$out['central_cache_min'];
        if ($cm <= 0) $cm = 60;
        if ($cm > 1440) $cm = 1440; // max 24 hours
        $out['central_cache_min'] = (string)$cm;

        return $out;
    }

    // -------------- LOW LEVEL TRANSPORT (curl OR file_get_contents — automatic + SSL tolerant --------
    function central_http_fetch($method, $url, $postArr = null, $timeoutSec = 10, &$outStatusCode = 0, &$outLastError = '') {
        $outStatusCode = 0;
        $outLastError  = '';
        $method = strtoupper($method);
        $isPost = ($method === 'POST');
        $body = null;
        $debug = [
            'via'   => '',
        ];

        // Helper: parse HTTP response code from first header line (file_get_contents)
        $parseFGC = function ($http_response_header, &$code) {
            $code = 0;
            if (!is_array($http_response_header)) return;
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})\b#', $h, $m)) {
                    $code = (int)$m[1];
                }
            }
        };

        // 1) Try CURL FIRST (if available)
        if (function_exists('curl_init')) {
            $ch = curl_init();
            $headers = ['Accept: application/json'];
            if ($isPost) {
                if (is_array($postArr)) {
                    $postBody = http_build_query($postArr);
                } else {
                    $postBody = (string)$postArr;
                    $headers[] = 'Content-Type: multipart/form-data';
                }
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
            } else {
                if (is_array($postArr) && $postArr) {
                    $sep = (strpos($url, '?') === false) ? '?' : '&';
                    $url .= $sep . http_build_query($postArr);
                }
            }
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int)$timeoutSec,
                CURLOPT_CONNECTTIMEOUT => (int)ceil($timeoutSec / 2),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => 'GoodPrintPortal-Central-Domain-Client/1.0',
                CURLOPT_HTTPHEADER     => $headers,
                // Try STRICT SSL first (best practice); if FAILS — retry WITHOUT verification once
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = (string)curl_error($ch);
            $sslFail = ($body === false && $err !== '' && (stripos($err, 'SSL') !== false || stripos($err, 'certificate') !== false));
            if ($sslFail) {
                // Retry with relaxed SSL (cheap hosting / self-signed / Cloudflare Origin CA missing)
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $body = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err  = (string)curl_error($ch);
                $debug['ssl_tried_ssl_fallback'] = true;
            }
            curl_close($ch);
            $outStatusCode = $code;
            $outLastError = $err;
            $debug['via'] = 'curl';
            $debug['ssl_verify'] = ($sslFail ? 'relaxed' : 'strict');
            return [
                'body'   => $body,
                'code'   => $outStatusCode,
                'error'  => $outLastError,
                'debug'  => $debug,
            ];
        }

        // 2) FALLBACK: file_get_contents + stream_context
        if (ini_get('allow_url_fopen')) {
            $ctxOpts = [
                'http' => [
                    'method'  => $method,
                    'timeout' => (int)$timeoutSec,
                    'user_agent' => 'GoodPrintPortal-Central-Domain-Client/1.0 (fopen)',
                    'header'  => "Accept: application/json\r\n",
                    'follow_location' => true,
                    'max_redirects' => 3,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name'=> true,
                    'allow_self_signed' => false,
                ],
            ];
            if ($isPost) {
                if (is_array($postArr)) {
                    $postBody = http_build_query($postArr);
                } else {
                    $postBody = (string)$postArr;
                }
                $ctxOpts['http']['content'] = $postBody;
                $ctxOpts['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $ctxOpts['http']['header'] .= "Content-Length: " . strlen($postBody) . "\r\n";
            } else {
                if (is_array($postArr) && $postArr) {
                    $sep = (strpos($url, '?') === false) ? '?' : '&';
                    $url .= $sep . http_build_query($postArr);
                }
            }
            // Try strict SSL first
            $context = stream_context_create($ctxOpts);
            $body = @file_get_contents($url, false, $context);
            $parseFGC($http_response_header ?? [], $code);
            if ($body === false && !$code) {
                // Retry relaxed SSL
                $ctxOpts['ssl']['verify_peer']       = false;
                $ctxOpts['ssl']['verify_peer_name']   = false;
                $ctxOpts['ssl']['allow_self_signed']= true;
                $context = stream_context_create($ctxOpts);
                $body = @file_get_contents($url, false, $context);
                $parseFGC($http_response_header ?? [], $code);
                $debug['ssl_tried_ssl_fallback'] = true;
                $debug['ssl_verify'] = 'relaxed';
            } else {
                $debug['ssl_verify'] = 'strict';
            }
            $outStatusCode = (int)$code;
            $outLastError  = ($body === false) ? 'file_get_contents failed (fopen disabled? wrapper disabled?)' : '';
            $debug['via'] = 'file_get_contents';
            return [
                'body'  => $body,
                'code'  => $outStatusCode,
                'error' => $outLastError,
                'debug' => $debug,
            ];
        }

        // 3) Nothing works!
        $outLastError = 'Neither curl extension nor allow_url_fopen is enabled on this server. Ask hosting provider to enable curl OR allow_url_fopen (php.ini).';
        $debug['via'] = 'none';
        return [
            'body'  => null,
            'code'  => 0,
            'error' => $outLastError,
            'debug' => $debug,
        ];
    }

    // -------------- LOW LEVEL: fetch from central panel (auto-path-correction) --------
    //    If user pastes ROOT domain without /api-management folder, we AUTOMATICALLY
    //    probe ROOT + "/api-management" + "/api-manager" variants — no URL confusion.
    function central_probe_manager_base($urlIn) {
        $urlIn = rtrim((string)$urlIn, '/ ');
        if ($urlIn === '') return [];
        if (!preg_match('#^https?://#i', $urlIn)) return [];
        $parsed = @parse_url($urlIn);
        if (!$parsed || empty($parsed['host'])) return [];
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'];
        $port   = !empty($parsed['port']) ? (':' . (int)$parsed['port']) : '';
        $path   = rtrim($parsed['path'] ?? '', '/ ');
        $rootNoPath = "{$scheme}://{$host}{$port}";
        $candidates = [];
        $candidates[] = $urlIn;
        if ($path !== '' && $path !== '/api-management' && $path !== '/api-manager') {
            $candidates[] = $rootNoPath . '/api-management';
            $candidates[] = $rootNoPath . '/api-manager';
        }
        if ($path === '' || $path === null) {
            $candidates[] = $rootNoPath . '/api-management';
            $candidates[] = $rootNoPath . '/api-manager';
        }
        $candidates[] = $rootNoPath;
        return array_values(array_unique($candidates));
    }

    function central_fetch_remote($cfg) {
        $mgr = rtrim($cfg['central_manager_url'] ?? '', '/');
        if ($mgr === '') return null;
        $key = (string)($cfg['central_access_key'] ?? '');

        $candidates = central_probe_manager_base($mgr);
        $lastResp    = null;
        $winnerBase  = null;
        $winnerBody  = null;

        foreach ($candidates as $candidateBase) {
            $qs = [];
            if ($key !== '') $qs['access_key'] = $key;
            $url = $candidateBase . '/api/get-domains.php';
            if ($qs) $url .= '?' . http_build_query($qs);

            $resp = central_http_fetch('GET', $url, null, 8, $code, $errStr);
            $code = (int)($resp['code'] ?? 0);
            $body = $resp['body'];
            $lastResp = $resp;
            if ($code >= 200 && $code < 300 && $body) {
                $json = @json_decode($body, true);
                if (is_array($json) && isset($json['status']) && isset($json['data']) && is_array($json['data'])) {
                    $winnerBase = $candidateBase;
                    $winnerBody = $body;
                    break;
                }
            }
        }

        if ($winnerBase === null) {
            $errTxt = '';
            if (is_array($lastResp)) {
                $errTxt = ($lastResp['error'] ?? '') ?: ('HTTP ' . (int)($lastResp['code'] ?? 0) . ' — check URL. Tried paths: ' . implode(', ', $candidates));
            }
            $GLOBALS['_central_last_fetch_error'] = $errTxt ?: 'Could not reach Central Manager at any of: ' . implode(', ', $candidates);
            $GLOBALS['_central_last_fetch_debug'] = [
                'candidates_tried' => $candidates,
                'transport_debug'  => is_array($lastResp) ? ($lastResp['debug'] ?? null) : null,
            ];
            return null;
        }

        $json = @json_decode($winnerBody, true);
        $d = $json['data'];
        $api = rtrim($d['api_domain'] ?? '', '/');
        $gw  = rtrim($d['gateway_domain'] ?? '', '/');
        if ($api === '' || $gw === '') return null;
        if (!preg_match('#^https?://#i', $api) || !preg_match('#^https?://#i', $gw)) return null;

        return [
            'api_domain'     => $api,
            'gateway_domain' => $gw,
            'version'        => (int)($json['version'] ?? 1),
            'fetched_at'     => date('c'),
            'source'         => 'remote',
            'transport'      => is_array($lastResp) ? ($lastResp['debug']['via'] ?? 'unknown') : 'unknown',
            'manager_url_normalized' => $winnerBase,
        ];
    }

    // -------------- READ / WRITE CACHE ------------------------------------------
    function central_read_cache() {
        $path = central_cache_path();
        $raw  = @file_get_contents($path);
        if (!$raw) return null;
        $arr = json_decode($raw, true);
        if (!is_array($arr) || empty($arr['api_domain']) || empty($arr['gateway_domain'])) {
            return null;
        }
        return $arr;
    }

    function central_write_cache($data) {
        $data['cached_at'] = time();
        $ok = @file_put_contents(central_cache_path(),
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if ($ok !== false) {
            @chmod(central_cache_path(), 0644);
            return true;
        }
        return false;
    }

    // -------------- MAIN: resolve domains ---------------------------------------
    function central_resolve_domains($forceRefresh = false, $cfgOverride = []) {
        static $result = null;
        // If override provided, skip static cache entirely for this call
        $skipStatic = (is_array($cfgOverride) && $cfgOverride);

        if ($result !== null && !$forceRefresh && !$skipStatic) return $result;

        $cfg   = central_read_site_config($cfgOverride);
        $cache = central_read_cache();
        $cacheSec = max(60, ((int)$cfg['central_cache_min']) * 60);

        // 1. Cached and still valid? -> return immediately (fastest, no network)
        if (!$forceRefresh && is_array($cache) && !empty($cache['cached_at'])) {
            $age = time() - (int)$cache['cached_at'];
            if ($age <= $cacheSec) {
                $cache['source'] = 'cache';
                $cache['age_sec'] = $age;
                if (!$skipStatic) $result = $cache;
                return $cache;
            }
        }

        // 2. Try fresh remote fetch
        $remote = central_fetch_remote($cfg);
        if (is_array($remote)) {
            central_write_cache($remote);
            $remote['source']  = 'remote';
            $remote['age_sec'] = 0;
            if (!$skipStatic) $result = $remote;
            return $remote;
        }

        // 3. Remote failed -> stale cache is better than fallback (domain may be newer)
        if (is_array($cache) && !empty($cache['api_domain']) && !empty($cache['gateway_domain'])) {
            $cache['source']  = 'cache-stale';
            $cache['age_sec'] = time() - (int)($cache['cached_at'] ?? time());
            if (!$skipStatic) $result = $cache;
            return $cache;
        }

        // 4. LAST RESORT -> hardcoded defaults (NEVER BREAK)
        $fallback = [
            'api_domain'     => rtrim(CENTRAL_DEFAULT_API_DOMAIN, '/'),
            'gateway_domain' => rtrim(CENTRAL_DEFAULT_GATEWAY_DOMAIN, '/'),
            'version'        => 0,
            'source'         => 'default-fallback',
            'age_sec'        => 0,
            'cached_at'      => time(),
            'fetched_at'     => null,
        ];
        // Save defaults as cache so next load is fast
        central_write_cache($fallback);
        if (!$skipStatic) $result = $fallback;
        return $fallback;
    }

    // -------------- PUBLIC HELPER FUNCTIONS -------------------------------------
    function get_central_api_domain() {
        $r = central_resolve_domains();
        return rtrim($r['api_domain'], '/');
    }

    function get_central_gateway_domain() {
        $r = central_resolve_domains();
        return rtrim($r['gateway_domain'], '/');
    }

    // Return diagnostics for Settings page ("Test Connection" etc.)
    function central_debug_info($forceRefresh = false, $cfgOverride = []) {
        $domains = central_resolve_domains($forceRefresh, $cfgOverride);
        $cfg     = central_read_site_config($cfgOverride);
        $cachePath = central_cache_path();
        $cacheRaw  = central_read_cache();
        $lastFetchErr  = $GLOBALS['_central_last_fetch_error'] ?? null;
        $lastFetchDbg  = $GLOBALS['_central_last_fetch_debug'] ?? null;
        return [
            'config'              => [
                'manager_url' => $cfg['central_manager_url'],
                'access_key'  => $cfg['central_access_key'] ? substr($cfg['central_access_key'], 0, 8) . str_repeat('*', max(0, strlen($cfg['central_access_key']) - 8)) : '',
                'cache_min'   => $cfg['central_cache_min'],
            ],
            'cache_path'          => $cachePath,
            'cache_exists'        => file_exists($cachePath),
            'cache_writable'      => is_writable(dirname($cachePath)),
            'resolved'            => $domains,
            'env'                 => [
                'curl'              => function_exists('curl_init'),
                'allow_url_fopen'   => ini_get('allow_url_fopen'),
                'php_version'       => PHP_VERSION,
                'server_software'   => $_SERVER['SERVER_SOFTWARE'] ?? '',
            ],
            'last_fetch_error'    => $lastFetchErr,
            'last_fetch_debug'    => $lastFetchDbg,
        ];
    }

    function central_force_refresh($cfgOverride = []) {
        $out = central_resolve_domains(true, $cfgOverride);
        if (!is_array($out) || empty($out['api_domain']) || empty($out['gateway_domain'])) {
            return [
                'status'  => false,
                'message' => 'Central manager unreachable. Check manager URL and server connectivity.',
                'resolved' => $out,
            ];
        }
        return [
            'status' => true,
            'resolved' => $out,
            'message' => 'Cache refreshed. Domains now active from ' . strtoupper($out['source'] ?? 'unknown') . '.',
        ];
    }

    // Register a NEW site with central (returns access_key + success status)
    // Compatible return: both {ok,error,...} (legacy) AND {status,message,access_key,...} (new AJAX)
    function central_register_site($managerUrl, $siteDomain, $adminEmail = '', $label = '', $contactNo = '') {
        $mgr = rtrim($managerUrl, '/');
        if ($mgr === '' || !preg_match('#^https?://#i', $mgr)) {
            $err = 'Invalid Central Manager URL (must start with http:// or https://)';
            return ['ok' => false, 'status' => false, 'error' => $err, 'message' => $err];
        }
        if ($siteDomain === '') {
            $err = 'Site domain required';
            return ['ok' => false, 'status' => false, 'error' => $err, 'message' => $err];
        }
        $post = [
            'site_domain' => $siteDomain,
            'admin_email' => $adminEmail,
            'label'       => $label,
            'contact_no'  => $contactNo,
        ];

        // Try multiple URL candidates (user may paste root domain, manager lives in /api-management subdir)
        $candidates = central_probe_manager_base($mgr);
        $winner = null;
        $lastResp = null;
        $lastDebug = [];
        $winnerJson = null;

        foreach ($candidates as $candidateBase) {
            $url = $candidateBase . '/api/register-site.php';
            $resp = central_http_fetch('POST', $url, $post, 15, $code, $errStr);
            $body = $resp['body'];
            $httpCode = (int)($resp['code'] ?? 0);
            $dbg = $resp['debug'] ?? [];
            $dbg['url'] = $url;
            $dbg['http_code'] = $httpCode;
            $dbg['body_raw'] = substr((string)$body, 0, 2000);
            $lastResp = $resp;
            $lastDebug = $dbg;
            if ($httpCode >= 200 && $httpCode < 300 && $body) {
                $json = @json_decode($body, true);
                if (is_array($json) && isset($json['status'])) {
                    $winner = $candidateBase;
                    $winnerJson = $json;
                    $lastDebug['winner_base'] = $winner;
                    break;
                }
            }
        }

        if ($winner === null || $winnerJson === null) {
            // Use the final/latest response state for error message
            $httpCode = (int)(is_array($lastResp) ? ($lastResp['code'] ?? 0) : 0);
            $transportError = (string)(is_array($lastResp) ? ($lastResp['error'] ?? '') : '');
            $msg = 'Registration failed (HTTP ' . $httpCode . '). ' . ($transportError ?: 'Tried paths: ' . implode(', ', $candidates) . ' — is the URL correct / folder uploaded?');
            if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
                $msg = 'Your hosting has BOTH curl extension disabled AND allow_url_fopen Off. Contact hosting support to enable curl (recommended) or allow_url_fopen in php.ini.';
            }
            return [
                'ok' => false, 'status' => false,
                'error' => $msg, 'message' => $msg,
                'debug' => $lastDebug + ['candidates_tried' => $candidates, 'sent_post' => $post],
            ];
        }

        $json = $winnerJson;
        if (empty($json['status'])) {
            $msg = $json['message'] ?? 'Registration rejected by Central Manager.';
            return ['ok' => false, 'status' => false, 'error' => $msg, 'message' => $msg, 'debug' => $lastDebug + ['response'=>$json]];
        }
        if (empty($json['access_key'])) {
            $msg = $json['message'] ?? 'Missing access_key in response.';
            return ['ok' => false, 'status' => false, 'error' => $msg, 'message' => $msg, 'debug' => $lastDebug + ['response'=>$json]];
        }
        // Inject auto-corrected manager URL for caller convenience
        if ($winner !== $mgr) {
            if (!isset($json['endpoints']) || !is_array($json['endpoints'])) $json['endpoints'] = [];
            $json['endpoints']['manager_base_auto'] = $winner;
            $json['endpoints']['dashboard_auto']    = rtrim($winner,'/').'/';
        }
        return [
            'ok'         => true,
            'status'     => true,
            'access_key' => $json['access_key'],
            'is_new'     => !empty($json['is_new']),
            'message'    => $json['message'] ?? '',
            'instructions' => $json['instructions'] ?? '',
            'endpoints'  => $json['endpoints'] ?? null,
            'debug'      => $lastDebug,
            'raw'        => $json,
        ];
    }
}
