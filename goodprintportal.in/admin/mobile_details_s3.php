<?php
error_reporting(E_ALL);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
$_fatalErrorMessage = '';
set_error_handler(function ($severity, $message, $file, $line) use (&$_fatalErrorMessage) {
    if (!(error_reporting() & $severity)) return false;
    $_fatalErrorMessage .= "[ERR#{$severity}] " . basename($file) . ":L{$line} — " . $message . " | ";
    return false;
});
register_shutdown_function(function () use (&$_fatalErrorMessage) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        @ob_end_clean();
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fatal Error Recovery</title></head><body style="background:#fee2e2;color:#7f1d1d;padding:32px;font-family:Arial,sans-serif;">';
        echo '<h2 style="margin-top:0;">&#128680; PHP Fatal Error aayi — Server side crash recovery</h2>';
        echo '<div style="background:#fff;border:1px solid #fecaca;border-radius:8px;padding:16px;margin-bottom:14px;">';
        echo '<div><b>Type:</b> ' . $err['type'] . '</div>';
        echo '<div><b>File:</b> ' . htmlspecialchars(basename($err['file'])) . ' <b>Line:</b> ' . (int)$err['line'] . '</div>';
        echo '<div style="margin-top:8px;"><b>Message:</b> ' . htmlspecialchars($err['message']) . '</div>';
        if ($GLOBALS['_fatalErrorMessage'] ?? '') echo '<div style="margin-top:8px;"><b>Stack Warnings:</b> ' . htmlspecialchars($GLOBALS['_fatalErrorMessage']) . '</div>';
        echo '</div>';
        echo '<div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:14px;font-size:13px;">';
        echo '<div>Jaldi fix ke liye is page ka screenshot bhejo admin ko.</div>';
        echo '<div style="margin-top:8px;"><a href="javascript:history.back()" style="background:#0d9488;color:white;padding:10px 18px;border-radius:6px;text-decoration:none;">&#8592; Wapas Form Page Pe Jao</a></div>';
        echo '</div></body></html>';
        exit;
    }
});

include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

// ============================================================
// 🔗 SETTINGS API SYNC — Read LIVE key from settings.pariprint_api_key
// Admin → Settings.php → PariPrint API Key change → automatically reflected here.
// 3-layer fallback: settings → scan all cols for phk_ → global $pariprint_api_key
// ============================================================
$mobileS3ApiKey = isset($pariprint_api_key) ? (string)$pariprint_api_key : '';
$mobileS3KeySource = 'global fallback $pariprint_api_key from config.php';
if (is_object($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
    $_m3cx = $GLOBALS['ahk_conn'] ?? $ahk_conn;
    $_m3r = @mysqli_fetch_assoc(@mysqli_query($_m3cx, "SELECT pariprint_api_key FROM settings WHERE id=1 LIMIT 1"));
    if (is_array($_m3r) && isset($_m3r['pariprint_api_key']) && is_string($_m3r['pariprint_api_key'])) {
        $_m3v = trim((string)$_m3r['pariprint_api_key']);
        if (strlen($_m3v) >= 40) {
            $mobileS3ApiKey = $_m3v;
            $mobileS3KeySource = 'DIRECT DB settings.pariprint_api_key';
            $GLOBALS['pariprint_api_key'] = $_m3v;
            $pariprint_api_key = $_m3v;
        }
    }
    if (strlen($mobileS3ApiKey) < 40 || substr($mobileS3ApiKey, 0, 4) !== 'phk_') {
        $_m3r2 = @mysqli_fetch_assoc(@mysqli_query($_m3cx, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
        if (is_array($_m3r2)) {
            foreach ($_m3r2 as $_k3 => $_v3) {
                if (!is_string($_v3) || strlen($_v3) < 40) continue;
                if (substr($_v3, 0, 4) === 'phk_' || (strpos(strtolower($_k3), 'pari') !== false && strpos(strtolower($_k3), 'key') !== false)) {
                    $mobileS3ApiKey = trim($_v3);
                    $mobileS3KeySource = 'DB FALLBACK col ' . $_k3;
                    $GLOBALS['pariprint_api_key'] = $mobileS3ApiKey;
                    $pariprint_api_key = $mobileS3ApiKey;
                    break;
                }
            }
        }
    }
}

// ============================================================
// 🛢️ AUTO SCHEMA + PRICING SETUP (first page load pe hi ho jayega)
// NO manual SQL needed — strict CREATE IF NOT EXISTS + INSERT IF NOT EXISTS
// ============================================================
function mobile_s3_ensure_schema($conn) {
    if (!$conn) return;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `mobile_details_s3_requests` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(80) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `mobile_number` VARCHAR(20) DEFAULT NULL,
        `order_id` VARCHAR(80) DEFAULT NULL,
        `total_count` INT NOT NULL DEFAULT 0,
        `download_url` TEXT DEFAULT NULL,
        `result_json` LONGTEXT DEFAULT NULL,
        `status_text` VARCHAR(50) DEFAULT NULL,
        `status_code` VARCHAR(20) DEFAULT NULL,
        `reference_id` VARCHAR(255) DEFAULT NULL,
        `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `message` TEXT DEFAULT NULL,
        `date` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_mobile_s3_application_no` (`application_no`),
        KEY `idx_mobile_s3_username` (`username`),
        KEY `idx_mobile_s3_mobile_number` (`mobile_number`),
        KEY `idx_mobile_s3_status_code` (`status_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    @mysqli_query($conn, "ALTER TABLE `mobile_details_s3_requests` ADD COLUMN IF NOT EXISTS `status_code` varchar(20) DEFAULT NULL AFTER `status_text`");
    @mysqli_query($conn, "ALTER TABLE `mobile_details_s3_requests` ADD COLUMN IF NOT EXISTS `reference_id` varchar(255) DEFAULT NULL AFTER `status_code`");
    @mysqli_query($conn, "ALTER TABLE `mobile_details_s3_requests` ADD KEY IF NOT EXISTS `idx_mobile_s3_status_code` (`status_code`)");
}

function mobile_s3_fee($conn) {
    $prExists = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT id, price FROM pricing WHERE service_name='mobile_details_s3_fee' LIMIT 1"));
    if (!$prExists) {
        @mysqli_query($conn, "INSERT INTO pricing (service_name, price, description) VALUES ('mobile_details_s3_fee', '10', 'Mobile Details S3 Service Charge')");
    }
    $queries = [
        "SELECT price FROM pricing WHERE service_name='mobile_details_s3_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='mobile_details_server2_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='mobile_details_server3_fee' LIMIT 1",
    ];
    foreach ($queries as $sql) {
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($row && isset($row['price'])) {
            $_pv = trim((string)$row['price']);
            if (is_numeric($_pv) && (float)$_pv > 0) return (float)$_pv;
        }
    }
    return 10.0;
}

mobile_s3_ensure_schema($ahk_conn);

$resdata = [];
$mobileNumber = '';
$pageMessage = '';
$alertScript = '';
$balance = (float)($udata['balance'] ?? 0);
$userPhone = (string)($udata['phone'] ?? '');
$fee = mobile_s3_fee($ahk_conn);
$downloadUrl = '';
$resultRecords = [];
$summaryHeader = [];

// ================== DEBUG STATE =========================================
$debugData = [
    'active'           => false,
    'request_url'      => '',
    'http_code'        => 0,
    'curl_errno'       => 0,
    'curl_error'       => '',
    'content_type'     => '',
    'response_len'     => 0,
    'redirect_count'   => 0,
    'total_time_sec'   => 0,
    'primary_ip'       => '',
    'raw_preview'      => '',
    'parsed_ok'        => false,
    'key_source'       => $mobileS3KeySource,
    'key_len'          => strlen($mobileS3ApiKey),
    'key_prefix'       => strlen($mobileS3ApiKey) >= 4 ? substr($mobileS3ApiKey, 0, 4) : '----',
    'raw_full_path'    => '',
];

if (isset($_POST['submit_mobile_details_s3'])) {
    $mobileNumber = preg_replace('/[^0-9]/', '', (string)($_POST['mobileNumber'] ?? ''));

    if (!preg_match('/^[0-9]{10}$/', $mobileNumber)) {
        $alertScript = "<script>Swal.fire({title:'Invalid Mobile Number',text:'Please enter a valid 10 digit mobile number',icon:'warning',confirmButtonText:'OK'});</script>";
    } elseif ($balance < $fee) {
        $alertScript = "<script>Swal.fire({title:'Balance Low! Recharge Now',text:'Insufficient Balance',icon:'warning',confirmButtonText:'OK'}).then((result)=>{ if(result.isConfirmed) window.location='wallet.php'; });</script>";
    } elseif (strlen($mobileS3ApiKey) < 40 || substr($mobileS3ApiKey, 0, 4) !== 'phk_') {
        $eh = '<div style="text-align:left;font-size:13px;line-height:1.6;">'
            . '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;">'
            . '<div style="font-weight:800;color:#991b1b;margin-bottom:6px;">&#128308; PARIPRINT API KEY INVALID / EMPTY.</div>'
            . '<div style="margin-top:4px;"><b>Fix:</b> Open → <a href="settings.php" target="_blank" style="color:#1d4ed8;text-decoration:underline;font-weight:700;">Admin → Settings → PariPrint API Key</a> me <code>phk_XXXXXXXX... (44 chars)</code> save karo fir try karo.</div>'
            . '</div><div style="margin-top:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;">'
            . '<div>Key Source: <b>' . htmlspecialchars($mobileS3KeySource) . '</b></div>'
            . '<div>Len: ' . strlen($mobileS3ApiKey) . ', Prefix: ' . htmlspecialchars((strlen($mobileS3ApiKey) >= 4 ? substr($mobileS3ApiKey, 0, 4) : '----')) . '</div>'
            . '</div></div>';
        $alertScript = '<script>$(function(){ Swal.fire({title:"PariPrint Key Missing", html:' . json_encode($eh) . ', icon:"error", confirmButtonColor:"#dc2626", width:640}); });</script>';
    } else {
        // S3: slug = instant-mobile-info-s3, params: mobile + apiKey (as per doc screenshot)
        $apiBase = 'https://pariprint.in';
        $apiUrl = $apiBase . '/api-proxy.php?' . http_build_query([
            'slug'   => 'instant-mobile-info-s3',
            'mobile' => $mobileNumber,
            'apiKey' => $mobileS3ApiKey,
        ]);

        $debugData['active']      = true;
        $debugData['request_url'] = $apiUrl;

        $_logDir = __DIR__ . '/../logs';
        if (!is_dir($_logDir)) { @mkdir($_logDir, 0755, true); }
        $_debugFile = $_logDir . '/mobile_s3_debug_' . $mobileNumber . '_' . date('Ymd_His') . '.txt';
        $debugData['raw_full_path'] = $_debugFile;

        @file_put_contents(
            $_debugFile,
            "MOBILE: {$mobileNumber}\nSLUG: instant-mobile-info-s3\nKEY_SOURCE: {$mobileS3KeySource}\nKEY_LEN: ".strlen($mobileS3ApiKey)."\nEXACT_URL: {$apiUrl}\nTIME: ".date('Y-m-d H:i:s')."\n\n"
        );

        // =========================================================
        // 🔥 BULLETPROOF 2-TIER API CALL + CLOUDFLARE HEADER FINGERPRINT BYPASS
        // TIER A (Primary): SAME-DOMAIN local /pariprint/api-proxy.php
        // TIER B (Fallback): PUBLIC pariprint.in/api-proxy.php (Chrome 129 fingerprint)
        // =========================================================

        $_tierLog = '';
        $_httpCode = 0;
        $_ct = '';
        $_ip = '';
        $_redirects = 0;
        $_timeSec = 0;
        $_respLen = 0;
        $_curlErrnoFinal = 0;
        $_curlErrorFinal = '';
        $response = '';

        $currentHost = !empty($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'goodprintportal.in';
        $cookieJar = $_logDir . '/mobile_s3_cookiejar_' . md5($currentHost) . '.txt';
        if (!is_file($cookieJar)) { @touch($cookieJar); }

        $browserHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: en-IN,en;q=0.9,en-GB;q=0.8,en-US;q=0.7,hi;q=0.6',
            'Cache-Control: max-age=0',
            'Sec-CH-UA: "Google Chrome";v="129", "Not=A?Brand";v="8", "Chromium";v="129"',
            'Sec-CH-UA-Arch: "x86"',
            'Sec-CH-UA-Bitness: "64"',
            'Sec-CH-UA-Full-Version: "129.0.6668.90"',
            'Sec-CH-UA-Full-Version-List: "Google Chrome";v="129.0.6668.90", "Not=A?Brand";v="8.0.0.0", "Chromium";v="129.0.6668.90"',
            'Sec-CH-UA-Mobile: ?0',
            'Sec-CH-UA-Model: ""',
            'Sec-CH-UA-Platform: "Windows"',
            'Sec-CH-UA-Platform-Version: "15.0.0"',
            'Sec-CH-UA-WoW64: ?0',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ];
        $chromeUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

        $tierList = [];

        // TIER A: Same-domain local /pariprint/ (S3 slug)
        $localQs = http_build_query([
            'slug'   => 'instant-mobile-info-s3',
            'mobile' => $mobileNumber,
            'apiKey' => $mobileS3ApiKey,
        ]);
        $tierList[] = [
            'id'       => 'A',
            'name'     => 'Same-Domain Local /pariprint/api-proxy.php',
            'url'      => 'https://' . $currentHost . '/pariprint/api-proxy.php?' . $localQs,
            'headers'  => array_merge($browserHeaders, [
                'Referer: https://' . $currentHost . '/admin/mobile_details_s3',
                'Origin: https://' . $currentHost,
            ]),
            'cookies'  => true,
        ];

        // TIER B: Public pariprint.in (S3 slug)
        $publicQs = http_build_query([
            'slug'   => 'instant-mobile-info-s3',
            'mobile' => $mobileNumber,
            'apiKey' => $mobileS3ApiKey,
        ]);
        $tierList[] = [
            'id'       => 'B',
            'name'     => 'Public pariprint.in (Browser-grade headers)',
            'url'      => 'https://pariprint.in/api-proxy.php?' . $publicQs,
            'headers'  => array_merge($browserHeaders, [
                'Referer: https://pariprint.in/api-docs.php?slug=instant-mobile-info-s3',
            ]),
            'cookies'  => true,
        ];

        $_tierOk = false;
        $_tierIdx = 0;

        foreach ($tierList as $_t) {
            $_tierIdx++;
            $_tStart = microtime(true);
            $_tierLog .= "\n====== TIER {$_t['id']} : {$_t['name']} ======\nURL: " . preg_replace('/(apikey|api_key|apiKey)=[^&]+/i', '$1=***HIDDEN***', $_t['url']) . "\n";

            $ch = curl_init();
            $curlOpts = [
                CURLOPT_URL            => $_t['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPGET        => true,
                CURLOPT_HTTPHEADER     => $_t['headers'],
                CURLOPT_USERAGENT      => $chromeUA,
                CURLOPT_VERBOSE        => false,
                CURLOPT_HEADER         => false,
                CURLOPT_FORBID_REUSE   => false,
                CURLOPT_FRESH_CONNECT  => false,
            ];
            if (!empty($_t['cookies'])) {
                $curlOpts[CURLOPT_COOKIEJAR]  = $cookieJar;
                $curlOpts[CURLOPT_COOKIEFILE] = $cookieJar;
            }
            curl_setopt_array($ch, $curlOpts);
            $tResp   = curl_exec($ch);
            $tErrNo  = curl_errno($ch);
            $tErrStr = curl_error($ch);
            $tInfo   = curl_getinfo($ch);
            curl_close($ch);

            $tHTTP    = (int)($tInfo['http_code'] ?? 0);
            $tCT      = (string)($tInfo['content_type'] ?? '');
            $tIP      = (string)($tInfo['primary_ip'] ?? '');
            $tRedirs  = (int)($tInfo['redirect_count'] ?? 0);
            $tTime    = (float)($tInfo['total_time'] ?? (microtime(true) - $_tStart));
            $tLen     = is_string($tResp) ? strlen($tResp) : 0;

            $_tierLog .= "HTTP: {$tHTTP} | cURL: #{$tErrNo} {$tErrStr} | CT: {$tCT} | IP: {$tIP} | Redirects: {$tRedirs} | Time: ".number_format($tTime,3)."s | Bytes: {$tLen}\n";

            $_looksJson = false;
            if (is_string($tResp) && trim($tResp) !== '') {
                $_tr = trim($tResp);
                $_first = $_tr[0] ?? '';
                $_looksJson = ($_first === '{' || $_first === '[') || stripos($tCT, 'json') !== false;
            }
            $_tierLog .= "Looks-JSON: " . ($_looksJson ? 'YES' : 'NO') . " | cURL-OK: " . ($tErrNo === 0 ? 'YES' : 'NO') . " | HTTP<400: " . ($tHTTP > 0 && $tHTTP < 400 ? 'YES' : 'NO') . "\n";
            $_tierLog .= "RESP PREVIEW (first 800 chars):\n" . (is_string($tResp) ? substr($tResp, 0, 800) : var_export($tResp, true)) . "\n";

            if ($tErrNo === 0 && $tResp !== false && $tHTTP > 0 && $tHTTP < 400 && trim((string)$tResp) !== '' && $_looksJson) {
                $_tierOk   = true;
                $response  = (string)$tResp;
                $_httpCode = $tHTTP;
                $_ct       = $tCT;
                $_ip       = $tIP;
                $_redirects= $tRedirs;
                $_timeSec  = $tTime;
                $_respLen  = $tLen;
                $_curlErrnoFinal = 0;
                $_curlErrorFinal = '';
                $_tierLog .= "=> TIER {$_t['id']} SUCCESS.\n";
                break;
            } else {
                // Cloudflare challenge 2-pass retry (Tier B only)
                $_cfRetry = false;
                if ($_t['id'] === 'B'
                    && $tErrNo === 0
                    && is_string($tResp)
                    && $tHTTP === 403
                    && stripos($tCT, 'html') !== false
                    && (stripos($tResp, 'Checking your browser before accessing') !== false
                        || stripos($tResp, 'challenge-platform') !== false
                        || stripos($tResp, 'cf-chl-widget') !== false
                        || stripos($tResp, 'Just a moment') !== false)
                ) {
                    $_cfRetry = true;
                    $_tierLog .= "=> CLOUDFLARE CHALLENGE DETECTED on TIER B. 2-pass retry after 1.5s...\n";
                    usleep(1500000);
                    $ch2 = curl_init();
                    $curlOpts2 = $curlOpts;
                    $curlOpts2[CURLOPT_URL] = $_t['url'];
                    if (!empty($_t['cookies'])) {
                        $curlOpts2[CURLOPT_COOKIEJAR]  = $cookieJar;
                        $curlOpts2[CURLOPT_COOKIEFILE] = $cookieJar;
                    }
                    curl_setopt_array($ch2, $curlOpts2);
                    $tResp2   = curl_exec($ch2);
                    $tErrNo2  = curl_errno($ch2);
                    $tErrStr2 = curl_error($ch2);
                    $tInfo2   = curl_getinfo($ch2);
                    curl_close($ch2);

                    $tHTTP2   = (int)($tInfo2['http_code'] ?? 0);
                    $tCT2     = (string)($tInfo2['content_type'] ?? '');
                    $tIP2     = (string)($tInfo2['primary_ip'] ?? '');
                    $tRedirs2 = (int)($tInfo2['redirect_count'] ?? 0);
                    $tTime2   = (float)($tInfo2['total_time'] ?? 0);
                    $tLen2    = is_string($tResp2) ? strlen($tResp2) : 0;

                    $_tierLog .= "   [PASS 2] HTTP: {$tHTTP2} | cURL: #{$tErrNo2} {$tErrStr2} | CT: {$tCT2} | Time: ".number_format($tTime2,3)."s | Bytes: {$tLen2}\n";

                    $_looksJson2 = false;
                    if (is_string($tResp2) && trim($tResp2) !== '') {
                        $_tr2 = trim($tResp2);
                        $_first2 = $_tr2[0] ?? '';
                        $_looksJson2 = ($_first2 === '{' || $_first2 === '[') || stripos($tCT2, 'json') !== false;
                    }
                    if ($tErrNo2 === 0 && $tResp2 !== false && $tHTTP2 > 0 && $tHTTP2 < 400 && trim((string)$tResp2) !== '' && $_looksJson2) {
                        $_tierOk   = true;
                        $response  = (string)$tResp2;
                        $_httpCode = $tHTTP2;
                        $_ct       = $tCT2;
                        $_ip       = $tIP2;
                        $_redirects= $tRedirs2;
                        $_timeSec  = $tTime + $tTime2;
                        $_respLen  = $tLen2;
                        $_curlErrnoFinal = 0;
                        $_curlErrorFinal = '';
                        $_tierLog .= "=> TIER B PASS-2 SUCCESS (Cloudflare challenge bypassed).\n";
                        break;
                    } else {
                        $tResp = $tResp2;
                        $tErrNo = $tErrNo2;
                        $tErrStr = $tErrStr2;
                        $tHTTP = $tHTTP2;
                        $tCT = $tCT2;
                        $tIP = $tIP2;
                        $tRedirs = $tRedirs2;
                        $tTime = $tTime + $tTime2;
                        $tLen = $tLen2;
                    }
                }

                $_curlErrnoFinal = $tErrNo > 0 ? $tErrNo : $_curlErrnoFinal;
                $_curlErrorFinal = $tErrStr !== '' ? $tErrStr : $_curlErrorFinal;
                if (!$_tierOk && $_tierIdx === count($tierList)) {
                    $response  = is_string($tResp) ? $tResp : '';
                    $_httpCode = $tHTTP;
                    $_ct       = $tCT;
                    $_ip       = $tIP;
                    $_redirects= $tRedirs;
                    $_timeSec  = $tTime;
                    $_respLen  = $tLen;
                }
                $_tierLog .= "=> TIER {$_t['id']} FAILED" . ($_cfRetry ? " (after Cloudflare 2-pass)." : ".") . "\n";
            }
        }

        @file_put_contents($_debugFile, $_tierLog . "\n\nFINAL: TIER-OK = " . ($_tierOk ? 'YES' : 'NO') . "\n", FILE_APPEND);

        $curlErrno = $_curlErrnoFinal;
        $curlError = $_curlErrorFinal;
        if (!$_tierOk && $curlError === '') {
            $curlError = 'Both Tier A (local /pariprint/) & Tier B (public pariprint.in) failed. Debug log dekho: ' . basename($_debugFile);
        }

        $debugData['curl_errno']     = (int)$curlErrno;
        $debugData['curl_error']     = (string)$curlError;
        $debugData['http_code']      = (int)$_httpCode;
        $debugData['content_type']   = (string)$_ct;
        $debugData['redirect_count'] = (int)$_redirects;
        $debugData['total_time_sec'] = (float)$_timeSec;
        $debugData['primary_ip']     = (string)$_ip;
        $debugData['response_len']   = (int)$_respLen;

        if (is_string($response) && $response !== '') {
            $debugData['raw_preview'] = mb_substr($response, 0, 600, 'UTF-8');
            @file_put_contents($_debugFile, "\n---- FINAL RAW RESPONSE ----\n" . $response . "\n\n---- CURL ERRNO: {$curlErrno} | HTTP_CODE: " . $debugData['http_code'] . " ----\n", FILE_APPEND);
        } else {
            $debugData['raw_preview'] = '(response empty / boolean false)';
        }

        if ($_tierOk === false || $curlError !== '') {
            $pageMessage = 'Service temporarily unavailable. Please try again in a few moments or contact admin.';
            $alertScript = '<script>$(function(){ Swal.fire({title:"Service Unavailable",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonColor:"#dc2626",didRender:function(){ setTimeout(function(){ window.scrollTo({top:0,behavior:"smooth"}); }, 300); } }); });</script>';
        } else {
            if (is_string($response) && trim($response) === '') {
                $pageMessage = 'No details received for this request. Please try again or contact admin if the problem persists.';
                $alertScript = '<script>$(function(){ Swal.fire({title:"No Response",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonColor:"#dc2626"}); });</script>';
            } else {
                $data = json_decode($response, true);
                if (!is_array($data)) {
                    $debugData['parsed_ok'] = false;
                    $pageMessage = 'Unable to process the service response. Please try again after a few minutes.';
                    $alertScript = '<script>$(function(){ Swal.fire({title:"Processing Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonColor:"#dc2626"}); });</script>';
                } else {
                    $debugData['parsed_ok'] = true;
                    $rawStatus = isset($data['status']) ? $data['status'] : ($data['success'] ?? false);
                    $rawStatusCode = isset($data['status_code']) ? $data['status_code'] : (isset($data['statusCode']) ? $data['statusCode'] : null);
                    if ($rawStatusCode === null || $rawStatusCode === '') {
                        if (is_string($rawStatus) && ctype_digit($rawStatus)) $rawStatusCode = $rawStatus;
                        elseif (is_numeric($rawStatus)) $rawStatusCode = (string)$rawStatus;
                    }
                    $statusMessage = (string)($data['statusMessage'] ?? ($data['status_message'] ?? ''));
                    $message = (string)($data['message'] ?? 'No response message');
                    $normStatus = strtolower(trim((string)$rawStatus));
                    $_statusMsgLower = strtolower(trim($statusMessage));

                    $records = [];
                    if (isset($data['data']) && is_array($data['data'])) {
                        if (isset($data['data'][0]) && is_array($data['data'][0])) {
                            foreach ($data['data'] as $r) { if (is_array($r)) $records[] = $r; }
                        } elseif (!isset($data['data'][0])) {
                            $records[] = $data['data'];
                        }
                    }
                    if (empty($records)) {
                        $candidates = ['owner_name', 'owner', 'name', 'fname'];
                        $hasAny = false;
                        foreach ($candidates as $c) { if (!empty($data[$c])) { $hasAny = true; break; } }
                        if ($hasAny) {
                            $records[] = $data;
                        }
                    }
                    $totalCount = isset($data['total_results']) ? (int)$data['total_results'] : (isset($data['total_count']) ? (int)$data['total_count'] : count($records));
                    $summaryHeader = $data;
                    unset($summaryHeader['wallet_balance']);

                    // --------- STRICT S3 SUCCESS LOGIC -----------
                    // SUCCESS = status:true OR status_code=100 (100 integer + "100" string) OR status=100 digit
                    // FAIL (NO DEBIT) = status_code=200 always (Record Not Found)
                    // Also statusMessage=success → success
                    $_s3_status_digit = null;
                    if (is_string($rawStatus) && ctype_digit($rawStatus)) $_s3_status_digit = (int)$rawStatus;
                    elseif (is_numeric($rawStatus)) $_s3_status_digit = (int)$rawStatus;
                    $_s3_scode_digit = null;
                    if ($rawStatusCode !== null) {
                        if (is_string($rawStatusCode) && ctype_digit($rawStatusCode)) $_s3_scode_digit = (int)$rawStatusCode;
                        elseif (is_numeric($rawStatusCode)) $_s3_scode_digit = (int)$rawStatusCode;
                    }

                    $isSuccess = (
                        $rawStatus === true ||
                        $rawStatus === 1 ||
                        $rawStatus === '1' ||
                        $rawStatus === 'true' ||
                        $rawStatus === '100' ||
                        $_s3_status_digit === 100 ||
                        $rawStatusCode === 100 ||
                        $rawStatusCode === '100' ||
                        $_s3_scode_digit === 100 ||
                        $_statusMsgLower === 'success' ||
                        $_statusMsgLower === 'successful' ||
                        $_statusMsgLower === 'completed' ||
                        $_statusMsgLower === 'done'
                    );

                    // 200 = Not Found -> NEVER debit (even if some other marker says ok)
                    if (
                        $rawStatusCode === 200 || $rawStatusCode === '200' || $_s3_scode_digit === 200 ||
                        (is_string($rawStatus) && $rawStatus === '200') || $_s3_status_digit === 200
                    ) {
                        $isSuccess = false;
                    }

                    if ($isSuccess && !empty($records)) {
                        $newBalance = $balance - $fee;
                        $applicationNo = 'MOBS3' . rand(100000, 999999);
                        $safeApp = mysqli_real_escape_string($ahk_conn, $applicationNo);
                        $safeMob = mysqli_real_escape_string($ahk_conn, $mobileNumber);
                        $safeOrd = mysqli_real_escape_string($ahk_conn, (string)($data['order_id'] ?? $data['request_id'] ?? ''));
                        $safeRef = mysqli_real_escape_string($ahk_conn, (string)($data['reference_id'] ?? $data['refId'] ?? ''));
                        $safeDl  = mysqli_real_escape_string($ahk_conn, (string)($data['download_url'] ?? $data['pdf_url'] ?? ''));
                        $safeResp = mysqli_real_escape_string($ahk_conn, $response);
                        $displaySC = $rawStatusCode !== null && $rawStatusCode !== '' ? (string)$rawStatusCode : ($_s3_status_digit === 100 ? '100' : (string)$normStatus);
                        $safeStatusTxt = mysqli_real_escape_string($ahk_conn, (string)$normStatus);
                        $safeStatusCode = mysqli_real_escape_string($ahk_conn, $displaySC);
                        $safeMsg = mysqli_real_escape_string($ahk_conn, $message);
                        $safeUser = mysqli_real_escape_string($ahk_conn, $userPhone);

                        $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - {$fee} WHERE phone='{$safeUser}'");
                        if ($walletUpdated) {
                            $ts = date('d/m/Y g:i:s');
                            mysqli_query($ahk_conn, "INSERT INTO mobile_details_s3_requests(application_no, username, mobile_number, order_id, total_count, download_url, result_json, status_text, status_code, reference_id, fee, old_balance, new_balance, message, date) VALUES ('{$safeApp}','{$safeUser}','{$safeMob}','{$safeOrd}',{$totalCount},'{$safeDl}','{$safeResp}','{$safeStatusTxt}','{$safeStatusCode}','{$safeRef}',{$fee},{$balance},{$newBalance},'{$safeMsg}','{$ts}')");
                            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeUser}','{$fee}','{$newBalance}','Mobile Details S3 - {$safeMob}','1','Debit')");

                            $resdata = $data;
                            $resultRecords = $records;
                            $downloadUrl = (string)($data['download_url'] ?? $data['pdf_url'] ?? '');
                            $balance = $newBalance;
                            $pageMessage = $message;
                            $alertScript = "<script>Swal.fire({title:'Success!',text:'Mobile Details S3 — fetched successfully. Total records: " . (int)$totalCount . "',icon:'success',confirmButtonText:'OK'});</script>";
                        } else {
                            $pageMessage = 'API se data mil gaya, lekin wallet balance deduct nahi ho saka.';
                            $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                        }
                    } else {
                        // Failure path: log only, NO wallet debit
                        $applicationNo = 'MOBS3' . rand(100000, 999999);
                        $safeApp = mysqli_real_escape_string($ahk_conn, $applicationNo);
                        $safeMob = mysqli_real_escape_string($ahk_conn, $mobileNumber);
                        $safeOrd = mysqli_real_escape_string($ahk_conn, (string)($data['order_id'] ?? $data['request_id'] ?? ''));
                        $safeRef = mysqli_real_escape_string($ahk_conn, (string)($data['reference_id'] ?? $data['refId'] ?? ''));
                        $safeDl  = mysqli_real_escape_string($ahk_conn, (string)($data['download_url'] ?? $data['pdf_url'] ?? ''));
                        $safeResp = mysqli_real_escape_string($ahk_conn, $response);
                        $displaySC = $rawStatusCode !== null && $rawStatusCode !== '' ? (string)$rawStatusCode : ($_s3_status_digit !== null ? (string)$_s3_status_digit : 'FAILED');
                        $safeStatusTxt = mysqli_real_escape_string($ahk_conn, (string)$normStatus);
                        $safeStatusCode = mysqli_real_escape_string($ahk_conn, $displaySC);
                        $safeMsg = mysqli_real_escape_string($ahk_conn, $message);
                        $safeUser = mysqli_real_escape_string($ahk_conn, $userPhone);
                        $ts = date('d/m/Y g:i:s');
                        @mysqli_query($ahk_conn, "INSERT INTO mobile_details_s3_requests(application_no, username, mobile_number, order_id, total_count, download_url, result_json, status_text, status_code, reference_id, fee, old_balance, new_balance, message, date) VALUES ('{$safeApp}','{$safeUser}','{$safeMob}','{$safeOrd}',{$totalCount},'{$safeDl}','{$safeResp}','{$safeStatusTxt}','{$safeStatusCode}','{$safeRef}',0,{$balance},{$balance},'{$safeMsg}','{$ts}')");

                        $pageMessage = $message !== '' ? $message : 'No details found for this mobile number.';
                        $resdata = $data;
                        $resultRecords = $records;
                        $isWarn = $isSuccess && empty($records);
                        $title = $isWarn ? 'No Records Found' : 'Request Error';
                        $icon  = $isWarn ? 'warning' : 'error';
                        $alertScript = '<script>$(function(){ Swal.fire({title:' . json_encode($title) . ', text:' . json_encode($pageMessage) . ', icon:' . json_encode($icon) . ', confirmButtonColor:"#dc2626" }); });</script>';
                    }
                }
            }
        }
    }
}

// For result rendering: if records not yet populated from submit, try from resdata
if (empty($resultRecords) && !empty($resdata)) {
    if (isset($resdata['data']) && is_array($resdata['data'])) {
        if (isset($resdata['data'][0]) && is_array($resdata['data'][0])) {
            foreach ($resdata['data'] as $r) { if (is_array($r)) $resultRecords[] = $r; }
        } elseif (!isset($resdata['data'][0])) {
            $resultRecords[] = $resdata['data'];
        }
    }
    if (empty($resultRecords)) {
        $candidates = ['owner_name', 'owner', 'name', 'fname'];
        $hasAny = false;
        foreach ($candidates as $c) { if (!empty($resdata[$c])) { $hasAny = true; break; } }
        if ($hasAny) { $resultRecords[] = $resdata; }
    }
}
$totalResults = isset($resdata['total_results']) ? (int)$resdata['total_results'] : (isset($resdata['total_count']) ? (int)$resdata['total_count'] : count($resultRecords));
$displayHeader = $summaryHeader ?: $resdata;
unset($displayHeader['wallet_balance']);
?>
<style>
    :root { --primary-s3: #0d9488; --primary-dark-s3: #0f766e; --success: #10b981; }
    body { background: linear-gradient(135deg, #ecfeff 0%, #ccfbf1 60%, #99f6e4 100%); }
    .service-card { background: rgba(255,255,255,0.98); border: none; border-radius: 2rem; box-shadow: 0 25px 45px -12px rgba(13,148,136,0.18); }
    .fee-chip { background: linear-gradient(135deg, #14b8a6, #0d9488); color: white; font-weight: 700; border-radius: 60px; padding: 0.5rem 1.8rem; font-size: 1.3rem; box-shadow: 0 8px 18px rgba(13,148,136,0.3); }
    .form-input { border-radius: 60px; padding: 0.9rem 1.5rem; border: 2px solid #99f6e4; transition: all 0.2s; font-size: 1rem; }
    .form-input:focus { border-color: var(--primary-s3); box-shadow: 0 0 0 4px rgba(13,148,136,0.15); outline: none; }
    .submit-btn { background: linear-gradient(90deg, var(--primary-s3), var(--primary-dark-s3)); border: none; padding: 0.9rem; font-weight: 700; font-size: 1.1rem; border-radius: 60px; transition: all 0.3s; color: white; }
    .submit-btn:hover { transform: scale(1.02); box-shadow: 0 10px 25px rgba(13,148,136,0.45); color: white; }
    .balance-card { background: white; border-radius: 1.5rem; border-left: 5px solid var(--success); }
    .credit-footer { background: linear-gradient(135deg, #134e4a, #115e59); color: #ccfbf1; border-radius: 1rem; padding: 0.8rem 1.5rem; margin-top: 2rem; }
    .details-card { background: #fff; border-radius: 1.5rem; box-shadow: 0 12px 28px rgba(15,118,110,0.1); }
    .mini-table td, .mini-table th { font-size: 14px; vertical-align: middle; }
    .kv-list { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; }
    .kv-list .item { background:#f0fdfa; border:1px solid #ccfbf1; border-radius:12px; padding: 10px 12px; }
    .kv-list .k { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#0f766e; font-weight:800; margin-bottom:2px; }
    .kv-list .v { font-size: 14px; color:#042f2e; word-break: break-word; font-weight:600; }
    @media (max-width: 640px) { .kv-list { grid-template-columns: 1fr; } }
    .srv-tag { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:#fef7ed; color:#c2410c; border:1px solid #fed7aa; font-size:12px; font-weight:700; }
    .srv-tag.s3 { background:#ccfbf1; color:#0f766e; border-color:#99f6e4; }
</style>
<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-12">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                        <div>
                            <h3 class="fw-bold mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-phone-vibrate fs-2" style="color:#0d9488;"></i>
                                <span>Mobile Details <span class="srv-tag s3">Server 3 (S3)</span></span>
                            </h3>
                            <p class="text-muted mt-1 mb-0">10-digit number → Full details (Owner, Father, Aadhaar ID, Address, Alt Mobile, Circle, Email) via PariPrint S3 slug <code>instant-mobile-info-s3</code>.</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="mobile_details_s3_list" class="btn btn-primary rounded-pill px-3" style="background:#0d9488;border-color:#0f766e;">
                                <i class="bi bi-list-ul"></i> My Requests
                            </a>
                            <a href="mobile_number_details_server2" class="btn btn-outline-secondary rounded-pill px-3" title="Server 2">
                                <i class="bi bi-arrow-left-right"></i> S2
                            </a>
                            <a href="mobile_number_details" class="btn btn-outline-secondary rounded-pill px-3" title="Original Server 1">
                                <i class="bi bi-arrow-left-right"></i> S1
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-5 col-12 mb-4">
                    <div class="card service-card mb-4">
                        <div class="card-body p-4 p-lg-5">
                            <div class="text-center mb-4">
                                <div class="fee-chip">
                                    <i class="bi bi-tag-fill"></i> Rs. <?php echo number_format($fee, 2); ?> / Search
                                </div>
                                <div class="mt-3 d-flex justify-content-center gap-3 flex-wrap">
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-lightning-charge-fill"></i> Instant Delivery</span>
                                    <span class="badge px-3 py-2 rounded-pill" style="background:#0d9488 !important;color:#fff;"><i class="bi bi-hdd-rack-fill"></i> Server 3 (S3)</span>
                                </div>
                            </div>
                            <div class="balance-card p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm">
                                <div>
                                    <i class="bi bi-wallet2 fs-4 text-success"></i>
                                    <span class="fw-semibold ms-2">Available Balance</span>
                                </div>
                                <div class="fs-4 fw-bold">Rs. <?php echo number_format($balance, 2); ?></div>
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2"><i class="bi bi-phone"></i> 10-Digit Mobile Number</label>
                                    <input type="text" name="mobileNumber" class="form-control form-input" placeholder="Example: 9905132590" maxlength="10" required value="<?php echo htmlspecialchars($mobileNumber); ?>">
                                </div>
                                <button type="submit" name="submit_mobile_details_s3" class="btn submit-btn w-100" <?php echo ($balance < $fee) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-search me-2"></i>
                                    <?php echo ($balance >= $fee) ? 'Search & Pay Rs. ' . number_format($fee, 2) : 'Insufficient Balance'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8 col-md-7 col-12 mb-4">
                    <div class="details-card p-4 p-lg-5 h-100">
                        <h4 class="fw-bold mb-3">Mobile Details (S3 Server) — Result</h4>

                        <?php if (!empty($resultRecords) || !empty($displayHeader)): ?>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="alert alert-light border mb-0"><strong>Query Mobile:</strong> <?php echo htmlspecialchars((string)($displayHeader['mobile'] ?? $mobileNumber)); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="alert alert-light border mb-0"><strong>Total Records:</strong> <?php echo $totalResults; ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="alert alert-light border mb-0"><strong>Service:</strong> <span class="badge" style="background:#0d9488;color:#fff;">S3 instant-mobile-info-s3</span></div>
                                </div>
                            </div>
                            <?php if (!empty($resultRecords)): $rIdx = 1; ?>
                                <?php foreach ($resultRecords as $row): ?>
                                    <div class="card border mb-4 shadow-sm">
                                        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div class="fw-bold" style="color:#0f766e;">#<?php echo $rIdx++; ?> — Record</div>
                                            <div><?php if (!empty($row['mobile'])): ?><span class="badge bg-primary"><?php echo htmlspecialchars($row['mobile']); ?></span><?php endif; ?>
                                            <?php if (!empty($row['circle'])): ?><span class="badge bg-info ms-1"><?php echo htmlspecialchars($row['circle']); ?></span><?php endif; ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="kv-list">
                                                <?php
                                                // Support fields: owner_name, fname, id, aadhar, mobile, alt, email, address, circle + extras
                                                $mapLabels = [
                                                    'owner_name' => 'Owner Full Name',
                                                    'name'       => 'Owner Name',
                                                    'owner'      => 'Owner',
                                                    'fname'      => 'Father Name',
                                                    'father_name'=> 'Father Name',
                                                    'id'         => 'Aadhaar Reference / ID',
                                                    'aadhar'     => 'Aadhaar Number',
                                                    'aadhaar'    => 'Aadhaar Number',
                                                    'mobile'     => 'Primary Mobile',
                                                    'alt'        => 'Alternate Mobile',
                                                    'alt_mobile' => 'Alternate Mobile',
                                                    'email'      => 'Email ID',
                                                    'address'    => 'Full Address',
                                                    'circle'     => 'Operator / Circle',
                                                    'operator'   => 'Operator',
                                                ];
                                                foreach ($mapLabels as $fieldKey => $prettyLabel):
                                                    $val = isset($row[$fieldKey]) ? trim((string)$row[$fieldKey]) : '';
                                                    if ($val === '' || $val === null) continue;
                                                    if (strcasecmp($val, 'N/A') === 0) {
                                                        $valClass = 'text-muted fst-italic';
                                                    } else {
                                                        $valClass = '';
                                                    }
                                                ?>
                                                    <div class="item">
                                                        <div class="k"><?php echo $prettyLabel; ?></div>
                                                        <div class="v <?php echo $valClass; ?>"><?php echo htmlspecialchars($val); ?></div>
                                                    </div>
                                                <?php
                                                endforeach;
                                                // Render any extra fields API returned that are not in label map
                                                foreach ($row as $ek => $ev):
                                                    if (array_key_exists($ek, $mapLabels)) continue;
                                                    if ($ev === '' || $ev === null) continue;
                                                    if (is_array($ev) || is_object($ev)) $ev = json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                ?>
                                                    <div class="item">
                                                        <div class="k"><?php echo htmlspecialchars(ucwords(str_replace(['_','-'], ' ', (string)$ek))); ?></div>
                                                        <div class="v"><?php echo htmlspecialchars((string)$ev); ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif (!empty($displayHeader)): ?>
                                <div class="alert alert-info">API responded but no records inside <code>data[]</code>. Summary shown below.</div>
                            <?php endif; ?>
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <?php if ($downloadUrl !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($downloadUrl); ?>" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Open PariPrint Record</a>
                                <?php endif; ?>
                            </div>
                            <details class="mt-3">
                                <summary class="text-muted">Raw API Response (wallet_balance removed)</summary>
                                <pre class="small mb-0 mt-2 bg-light p-3 rounded"><?php echo htmlspecialchars(json_encode($displayHeader, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                            </details>
                        <?php elseif ($pageMessage !== ''): ?>
                            <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($pageMessage); ?></div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-person-lines-fill fs-1" style="color:#0d9488;"></i>
                                <h5 class="text-muted mt-3">Enter 10-Digit Mobile Number</h5>
                                <p class="text-muted mb-0">Server S3 se full details yahaan dikhenge — Owner Name, Father, Aadhaar ID, Address, Alt Mobile, Circle, Email etc.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container pb-4">
    <div class="credit-footer text-center">
        <div class="d-flex flex-wrap justify-content-center gap-4 align-items-center">
            <span><i class="bi bi-cpu"></i> Powered by <strong>goodprintportal.in</strong> — slug: <code>instant-mobile-info-s3</code></span>
            <span class="opacity-75">|</span>
            <span><i class="bi bi-hdd-stack"></i> Table: <strong>mobile_details_s3_requests</strong></span>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.querySelector('input[name="mobileNumber"]');
    if (input) {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }
});
</script>
<?php echo $alertScript; ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include('userFooter.php'); ?>
