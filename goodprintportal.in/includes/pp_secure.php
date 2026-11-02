<?php
// ============================================================================
// PariPrint secure helpers
// The PariPrint API key (phk_...) and the voter-PDF internal secret are used ONLY
// here on the server. Browser never gets them: status / download requests go to
// our own PHP files, which call PariPrint server-to-server, scrub the key from the
// response, and stream the PDF back to the logged-in owner.
// ============================================================================

if (!defined('PP_VPDF_INTERNAL_SECRET')) {
    define('PP_VPDF_INTERNAL_SECRET', 'gpp-pp-vpdf-2026-x9k2');
}

if (!function_exists('pp_api_base')) {

function pp_api_base(): string {
    if (function_exists('get_central_api_domain')) {
        $b = rtrim((string)get_central_api_domain(), '/');
        if ($b !== '') return $b;
    }
    if (defined('CENTRAL_API_BASE')) return rtrim((string)CENTRAL_API_BASE, '/');
    return 'https://pariprint.in';
}

/** PariPrint key: settings.pariprint_api_key (phk_...) first, then the global from config.php. */
function pp_api_key(): string {
    global $ahk_conn;
    if (!empty($ahk_conn)) {
        $r = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
        if (is_array($r)) {
            $v = isset($r['pariprint_api_key']) && is_string($r['pariprint_api_key']) ? trim($r['pariprint_api_key']) : '';
            if (strlen($v) >= 40 && substr($v, 0, 4) === 'phk_') return $v;
            foreach ($r as $k => $vv) {
                if (!is_string($vv) || strlen($vv) < 40) continue;
                if (substr($vv, 0, 4) === 'phk_' || (stripos((string)$k, 'pari') !== false && stripos((string)$k, 'key') !== false)) return trim($vv);
            }
        }
    }
    $g = $GLOBALS['pariprint_api_key'] ?? '';
    return is_string($g) ? trim($g) : '';
}

function pp_norm_phone(string $p): string {
    $d = preg_replace('/[^0-9]/', '', $p);
    if (strlen($d) >= 12) $d = substr($d, -10);
    elseif (strlen($d) === 11 && $d[0] === '0') $d = substr($d, 1);
    return $d;
}

/** Remove the key / secret from anything that is about to reach the browser. */
function pp_scrub($v) {
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $x) {
            if (is_string($k) && in_array(strtolower($k), ['api_key', 'apikey', 'secret', 'access_key', '_status_check_url'], true)) continue;
            $out[$k] = pp_scrub($x);
        }
        return $out;
    }
    if (!is_string($v) || $v === '') return $v;
    $key = pp_api_key();
    if ($key !== '') $v = str_replace([$key, rawurlencode($key)], '***', $v);
    $v = str_replace(PP_VPDF_INTERNAL_SECRET, '***', $v);
    return (string)preg_replace('/((?:api_?key|apikey|access_key|secret)=)[^&\s"\'<>]+/i', '$1***', $v);
}

/** True if the URL points to PariPrint (or carries a key) and must not be given to the browser. */
function pp_is_private_url(string $url): bool {
    if ($url === '') return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $base = strtolower((string)parse_url(pp_api_base(), PHP_URL_HOST));
    if ($host !== '' && ($host === $base || $host === 'pariprint.in' || $host === 'www.pariprint.in')) return true;
    return (bool)preg_match('/(api_?key|apikey|access_key|secret)=/i', $url);
}

/** Add the key to a PariPrint URL that has none (server side only). */
function pp_with_key(string $url): string {
    if ($url === '' || preg_match('/(api_?key|apikey)=/i', $url)) return $url;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $base = strtolower((string)parse_url(pp_api_base(), PHP_URL_HOST));
    if ($host === '' || ($host !== $base && $host !== 'pariprint.in' && $host !== 'www.pariprint.in')) return $url;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'api_key=' . rawurlencode(pp_api_key());
}

/** Server-to-server HTTP call. */
function pp_http(string $url, string $method = 'GET', array $body = [], int $timeout = 90): array {
    $hdrDisp = '';
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (GoodPrintPortal)',
        CURLOPT_HTTPHEADER => ['Accept: application/pdf, application/json, application/octet-stream, text/html, */*', 'Cache-Control: no-cache'],
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$hdrDisp) {
            if (stripos($h, 'Content-Disposition:') === 0) $hdrDisp = trim(substr($h, 20));
            return strlen($h);
        },
    ];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $out = [
        'body' => is_string($resp) ? $resp : '',
        'code' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'type' => strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE)),
        'disposition' => $hdrDisp,
    ];
    curl_close($ch);
    return $out;
}

function pp_is_pdf(array $r): bool {
    if ($r['code'] < 200 || $r['code'] >= 400 || $r['body'] === '') return false;
    if (strncmp(ltrim(substr($r['body'], 0, 1024)), '%PDF', 4) === 0) return true;
    return str_contains($r['type'], 'application/pdf') || str_contains($r['type'], 'octet-stream') || str_contains($r['type'], 'force-download');
}

/** Decode JSON (also when wrapped in other text). */
function pp_json(string $body): ?array {
    $t = trim($body);
    if ($t === '') return null;
    $d = json_decode($t, true);
    if (is_array($d)) return $d;
    if (preg_match('/\{[\s\S]*\}/', $t, $m)) {
        $d = json_decode($m[0], true);
        if (is_array($d)) return $d;
    }
    return null;
}

/** Download URL candidates found in a PariPrint JSON response. */
function pp_pick_urls(array $data): array {
    $keys = ['download_url','downloadUrl','download_link','download','pdf_url','pdfUrl','pdf_link','pdf','file_url','fileUrl','file_link','file','result_url','resultUrl','result_link','output_url','pdf_download_url','pdf_download','file_download_url','download_direct','file_download_direct','pdf_download_direct','direct_download_url','simple_download_url','download_no_slug_url'];
    $out = [];
    foreach ($keys as $k) {
        if (!empty($data[$k]) && is_string($data[$k]) && stripos(trim($data[$k]), 'http') === 0) $out[] = trim($data[$k]);
    }
    return array_values(array_unique($out));
}

/** Send PDF bytes to the browser and stop. */
function pp_send_pdf(array $r, string $fallbackName): void {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    $name = $fallbackName;
    if ($r['disposition'] !== '' && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $r['disposition'], $m)) {
        $name = rawurldecode(trim($m[1]));
    }
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: $fallbackName;
    if (!preg_match('/\.pdf$/i', $name)) $name .= '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($r['body']));
    header('Cache-Control: private, no-store, no-cache, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $r['body'];
    exit;
}

/** Simple message page (no key, no PariPrint links). */
function pp_fail_page(int $code, string $msg): void {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store, no-cache, max-age=0');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PDF</title></head>'
        . '<body style="font-family:system-ui,Arial;padding:40px;text-align:center;background:#f8fafc">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;padding:28px;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.08)">'
        . '<h3 style="margin-top:0">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</h3>'
        . '<a href="javascript:history.back()" style="display:inline-block;margin-top:10px;padding:10px 18px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none">Back</a>'
        . '</div></body></html>';
    exit;
}

}
