<?php
@ini_set('display_errors', '0');
@error_reporting(0);
@ini_set('log_errors', '0');
while (@ob_get_level()) { @ob_end_clean(); }
@ob_start();

// ============================================================
// PHP < 8.0 POLYFILLS (server par agar PHP 7.x hai to errors nahi aaye)
// ============================================================
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return (string)$needle !== '' && strpos((string)$haystack, (string)$needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp((string)$haystack, (string)$needle, strlen((string)$needle)) === 0;
    }
}

// ============================================================
// Minimal error UI helper (defined BEFORE use)
// ============================================================
function rcnc_fail($msg) {
    while (ob_get_level()) { @ob_end_clean(); }
    $safe = htmlspecialchars($msg);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>PDF Not Available</title><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:system-ui,Arial,sans-serif;background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
    .card{max-width:520px;width:100%;background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(15,23,42,.1);padding:32px 28px;}
    .card h1{color:#dc2626;font-size:22px;margin-bottom:10px;font-weight:800;}
    .card p{color:#475569;line-height:1.6;font-size:14px;}
    .btnrow{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px;}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:10px;font-weight:700;font-size:13px;text-decoration:none;border:0;cursor:pointer;}
    .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;}
    .btn-secondary{background:#e2e8f0;color:#0f172a;}
    </style></head><body><div class="card">
    <h1>&#10060; PDF Not Available</h1>
    <p>'.$safe.'</p>
    <div class="btnrow">
    <a class="btn btn-primary" href="rc_get_nochip_list">&larr; Back to List</a>
    <a class="btn btn-secondary" href="rc_get_nochip">+ Generate New</a>
    </div>
    </div></body></html>';
    exit;
}

// ============================================================
// Boot
// ============================================================
include('../includes/session.php');
include('../includes/config.php');
date_default_timezone_set('Asia/Kolkata');
@header('X-Content-Type-Options: nosniff');

$udata = is_array($udata) ? $udata : [];

$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userPhone = (string)(isset($_SESSION['phone']) ? $_SESSION['phone'] : '');

if ($recordId <= 0 || $userPhone === '') {
    http_response_code(400);
    rcnc_fail('Invalid request. Please login and try again.');
}

// ============================================================
// DB record fetch
// ============================================================
$safeUser = mysqli_real_escape_string($ahk_conn, $userPhone);
$safeId = mysqli_real_escape_string($ahk_conn, (string)$recordId);

$sql = "SELECT * FROM rc_vehical_nochip WHERE id='{$safeId}' AND username='{$safeUser}' LIMIT 1";
$res = mysqli_query($ahk_conn, $sql);
$record = $res ? mysqli_fetch_assoc($res) : null;

// Admin fallback
$isAdmin = false;
if (is_array($udata) && !empty($udata['type'])) {
    $ut = strtolower((string)$udata['type']);
    $isAdmin = ($ut === 'admin' || $ut === 'administrator' || $ut === 'superadmin');
}
if (!$record && $isAdmin) {
    $sql2 = "SELECT * FROM rc_vehical_nochip WHERE id='{$safeId}' LIMIT 1";
    $res2 = mysqli_query($ahk_conn, $sql2);
    $record = $res2 ? mysqli_fetch_assoc($res2) : null;
}

if (!$record) {
    http_response_code(404);
    rcnc_fail('Record not found or access denied.');
}

// ============================================================
// Columns + filename
// ============================================================
$rcNoDb = isset($record['rc_vehical_no']) ? (string)$record['rc_vehical_no'] : '';
$fileDb = isset($record['file_name']) ? (string)$record['file_name'] : '';
$pdfDb  = isset($record['pdf']) ? (string)$record['pdf'] : '';

$rcClean = preg_replace('/[^A-Za-z0-9]/', '', $rcNoDb);
if ($rcClean === '') $rcClean = 'RC-' . $recordId;
$fileName = $fileDb !== '' ? $fileDb : ('RC_' . $rcClean . '_NoChip.pdf');
if (!preg_match('/\.pdf$/i', $fileName)) $fileName .= '.pdf';
$fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName);

// ============================================================
// PDF type detection
// ============================================================
$pdfTrim = trim($pdfDb);
$isDataUrl = stripos($pdfTrim, 'data:application/pdf;base64,') === 0;
$isHttpUrl = !$isDataUrl && (filter_var($pdfTrim, FILTER_VALIDATE_URL) || (!$isDataUrl && preg_match('#^https?://#i', $pdfTrim)));
$isBase64Like = !$isDataUrl && !$isHttpUrl && preg_match('#^[A-Za-z0-9+/=\s]+$#', $pdfTrim) && strlen($pdfTrim) > 200;

$pdfBinary = null;
$apiKey = isset($pariprint_api_key) ? (string)$pariprint_api_key : '';

// ============================================================
// 1) Data URL (base64)
// ============================================================
if ($isDataUrl) {
    $raw = substr($pdfTrim, strlen('data:application/pdf;base64,'));
    $raw = preg_replace('/\s+/', '', $raw);
    $bin = @base64_decode($raw, true);
    if ($bin !== false && str_starts_with($bin, '%PDF')) {
        $pdfBinary = $bin;
    }
}

// ============================================================
// 2) Raw base64
// ============================================================
if ($pdfBinary === null && $isBase64Like) {
    $raw = preg_replace('/\s+/', '', $pdfTrim);
    $bin = @base64_decode($raw, true);
    if ($bin !== false && str_starts_with($bin, '%PDF')) {
        $pdfBinary = $bin;
    }
}

// ============================================================
// 3) External HTTP URL (cURL passthrough)
// ============================================================
if ($pdfBinary === null && $isHttpUrl && function_exists('curl_init')) {
    $downloadUrl = $pdfTrim;
    if ((str_contains($downloadUrl, 'api-service-download') || str_contains($downloadUrl, 'api-service-d') || str_contains($downloadUrl, 'pariprint.in')) && $apiKey !== '') {
        $sep = str_contains($downloadUrl, '?') ? '&' : '?';
        $downloadUrl = $downloadUrl . $sep . 'api_key=' . rawurlencode($apiKey);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 12);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (GoodPrintPortal-RCNoChip)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/pdf, application/octet-stream, text/html, */*',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    ));
    $fetched = curl_exec($ch);
    $httpCode = 0;
    $ct = '';
    $disp = '';
    if ($fetched !== false) {
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $disp = (string)curl_getinfo($ch, CURLINFO_CONTENT_DISPOSITION);
    }
    curl_close($ch);

    if ($fetched !== false && $httpCode >= 200 && $httpCode < 300) {
        $ctLow = strtolower($ct);
        $dispLow = strtolower($disp);
        $looksPdf = (str_contains($ctLow, 'pdf') || str_contains($ctLow, 'octet-stream') || str_contains($dispLow, 'attachment') || str_contains($dispLow, '.pdf'));
        if (is_string($fetched) && str_starts_with($fetched, '%PDF')) $looksPdf = true;
        if (!$looksPdf && is_string($fetched) && strlen($fetched) > 200000) $looksPdf = true;

        if ($looksPdf) {
            $pdfBinary = $fetched;
            if (preg_match('/filename\*=UTF-8\'\'([^;&]+)/i', $disp, $m) || preg_match('/filename="?([^";]+)"?/i', $disp, $m)) {
                $fn = trim(urldecode($m[1]));
                if ($fn !== '' && preg_match('/\.pdf$/i', $fn)) $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fn);
            }
        }
    }
}

// ============================================================
// Final: serve or fail
// ============================================================
if ($pdfBinary !== null) {
    while (ob_get_level()) { @ob_end_clean(); }
    $size = strlen($pdfBinary);
    /* COOKIE signal for JS poll: set cookie 60s valid path=/ httponly=false */
    $dlCookieName = 'rcnc_dl_done_' . $recordId;
    @setcookie($dlCookieName, '1', time() + 60, '/', '', false, false);
    @header('Set-Cookie: ' . $dlCookieName . '=1; Max-Age=60; Path=/; SameSite=Lax', false);
    @header('Content-Description: File Transfer');
    @header('Content-Type: application/pdf');
    @header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
    @header('Content-Transfer-Encoding: binary');
    @header('Accept-Ranges: bytes');
    @header('Expires: 0');
    @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    @header('Pragma: public');
    if ($size > 0) @header('Content-Length: ' . $size);
    echo $pdfBinary;
    flush();
    exit;
}

http_response_code(404);
rcnc_fail('PDF not available for this record. Please generate a new RC PDF (No Chip) request and try again.');
