<?php
@ini_set('display_errors', '0');
@error_reporting(0);
@ob_end_clean();
@ob_clean();
while (ob_get_level() > 0) { @ob_end_clean(); }
header_remove();
// includes/links.php (via config.php) prints a <script> tag. Buffer it so it is discarded before the
// PDF is sent; without this the headers are already sent and the PDF shows as raw text.
@ob_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    @header('HTTP/1.1 400 Bad Request');
    @header('Content-Type: text/plain; charset=utf-8');
    die('Invalid request.');
}

include('../includes/config.php');
include('../includes/session.php');

$s_phone = $udata['phone'] ?? '';
if ($s_phone === '') {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    @header('HTTP/1.1 401 Unauthorized');
    @header('Content-Type: text/plain; charset=utf-8');
    die('Login required.');
}

$safeUser = mysqli_real_escape_string($ahk_conn, $s_phone);
$row = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM rc_vehical_s5 WHERE id=$id AND username='$safeUser' LIMIT 1"));

if (!$row) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    @header('HTTP/1.1 404 Not Found');
    @header('Content-Type: text/plain; charset=utf-8');
    die('PDF record not found.');
}

$pp_key = '';
$_r1 = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
if (is_array($_r1)) {
    if (isset($_r1['pariprint_api_key']) && is_string($_r1['pariprint_api_key']) && strlen($_r1['pariprint_api_key']) >= 40 && substr($_r1['pariprint_api_key'], 0, 4) === 'phk_') {
        $pp_key = trim($_r1['pariprint_api_key']);
    } else {
        foreach ($_r1 as $_kk => $_vv) {
            if (!is_string($_vv) || strlen($_vv) < 40) continue;
            if (substr($_vv, 0, 4) === 'phk_' || ((stripos($_kk, 'pari') !== false) && (stripos($_kk, 'key') !== false))) {
                $pp_key = trim($_vv);
                break;
            }
        }
    }
}
if ($pp_key === '' && !empty($GLOBALS['pariprint_api_key']) && is_string($GLOBALS['pariprint_api_key']) && strlen($GLOBALS['pariprint_api_key']) >= 40 && substr($GLOBALS['pariprint_api_key'], 0, 4) === 'phk_') {
    $pp_key = $GLOBALS['pariprint_api_key'];
}
if ($pp_key === '' && !empty($pariprint_api_key) && is_string($pariprint_api_key) && strlen($pariprint_api_key) >= 40 && substr($pariprint_api_key, 0, 4) === 'phk_') {
    $pp_key = $pariprint_api_key;
}

if ($pp_key === '') {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    @header('HTTP/1.1 500 Internal Server Error');
    @header('Content-Type: text/plain; charset=utf-8');
    die('PariPrint API key not configured in Settings (settings.php). Save pariprint phk_... key first.');
}

$apiBase = (defined('CENTRAL_API_BASE') ? rtrim(CENTRAL_API_BASE, '/') : 'https://pariprint.in');

$fbRequestId = 0;
$fbOrderId = 0;
$cols = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM rc_vehical_s5 LIKE 'request_id'");
if ($cols && mysqli_num_rows($cols) > 0 && isset($row['request_id']) && ctype_digit((string)$row['request_id']) && (int)$row['request_id'] > 0) {
    $fbRequestId = (int)$row['request_id'];
}
$cols2 = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM rc_vehical_s5 LIKE 'order_id'");
if ($cols2 && mysqli_num_rows($cols2) > 0 && isset($row['order_id']) && ctype_digit((string)$row['order_id']) && (int)$row['order_id'] > 0) {
    $fbOrderId = (int)$row['order_id'];
}
$fbId = $fbRequestId > 0 ? $fbRequestId : $fbOrderId;

if ($fbId <= 0) {
    $rcNo = trim((string)($row['rc_vehical_no'] ?? ''));
    $typeVal = trim((string)($row['type'] ?? ''));
    if ($rcNo !== '' && ctype_digit(str_replace(['-',' '], '', $rcNo)) !== false && $typeVal !== '' && in_array($typeVal, ['1','2','3','4'], true) && function_exists('curl_init')) {
        $reuseUrl = $apiBase . '/api-proxy.php?' . http_build_query([
            'slug' => 'instant-rc-pdf-s5',
            'vehicalNo' => $rcNo,
            'type' => $typeVal,
            'apiKey' => $pp_key,
        ]);
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $reuseUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch2, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 GoodPrintPortal');
        $resp = curl_exec($ch2);
        $http2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        if (is_string($resp) && $resp !== '' && $http2 >= 200 && $http2 < 400) {
            $dec = json_decode($resp, true);
            if (is_array($dec)) {
                $new_rid = 0; $new_oid = 0;
                if (isset($dec['request_id']) && ctype_digit((string)$dec['request_id'])) $new_rid = (int)$dec['request_id'];
                if (isset($dec['order_id']) && ctype_digit((string)$dec['order_id'])) $new_oid = (int)$dec['order_id'];
                if ($new_rid > 0 || $new_oid > 0) {
                    $safe_rid = $new_rid > 0 ? (string)$new_rid : 'NULL';
                    $safe_oid = $new_oid > 0 ? (string)$new_oid : 'NULL';
                    if ($cols && mysqli_num_rows($cols) > 0) {
                        @mysqli_query($ahk_conn, "UPDATE rc_vehical_s5 SET request_id = $safe_rid WHERE id = $id");
                    }
                    if ($cols2 && mysqli_num_rows($cols2) > 0) {
                        @mysqli_query($ahk_conn, "UPDATE rc_vehical_s5 SET order_id = $safe_oid WHERE id = $id");
                    }
                    $fbId = $new_rid > 0 ? $new_rid : $new_oid;
                }
            }
        }
    }
}

if ($fbId > 0) {
    // Fetch the PDF here (server side) and stream it. Earlier the browser was redirected to
    // PariPrint with &api_key=... in the URL, which exposed the key to the user.
    require_once __DIR__ . '/../includes/pp_secure.php';
    $fetchUrl = $apiBase . '/api-service-download.php?source=reseller&id=' . $fbId . '&api_key=' . rawurlencode($pp_key);
    $r = pp_http($fetchUrl, 'GET', [], 120);
    if (pp_is_pdf($r)) {
        header_remove();
        $rcName = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($row['rc_vehical_no'] ?? '')) ?: ('rc-' . $fbId);
        pp_send_pdf($r, 'RC_' . $rcName . '.pdf');
    }
    $msg = '';
    $j = pp_json($r['body']);
    if (is_array($j)) $msg = trim((string)($j['message'] ?? $j['msg'] ?? $j['error'] ?? ''));
    header_remove();
    while (ob_get_level() > 0) { @ob_end_clean(); }
    @header('HTTP/1.1 404 Not Found');
    @header('Content-Type: text/plain; charset=utf-8');
    @header('Cache-Control: private, no-store, no-cache, max-age=0');
    die($msg !== '' ? pp_scrub($msg) : 'Unable to fetch the PDF right now. Please try again in a moment.');
}

while (ob_get_level() > 0) { @ob_end_clean(); }
@header('HTTP/1.1 404 Not Found');
@header('Content-Type: text/plain; charset=utf-8');
die('Unable to fetch the PDF. Regenerate the RC document from rc_get_s5 page to register a new transaction ID, or ensure Settings has PariPrint phk_ API key configured correctly.');
