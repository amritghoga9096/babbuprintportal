<?php
@ini_set('display_errors', '0');
@error_reporting(0);
ob_start(); // capture any stray output from includes so PDF headers can still be sent

function dl_s3_send_pdf(string $bin, string $fileName): void
{
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        header_remove();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: private, no-store, no-cache, max-age=0');
        echo $bin;
        exit;
    }
    // Headers already sent by an include: trigger the download from the browser instead
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Downloading PDF…</title></head>'
        . '<body style="font-family:Arial,sans-serif;text-align:center;padding:40px;">'
        . '<h3>Your PDF download is starting…</h3><a id="dl" href="#">Click here if download does not start</a>'
        . '<script>(function(){var b=atob(' . json_encode(base64_encode($bin)) . ');var u=new Uint8Array(b.length);'
        . 'for(var i=0;i<b.length;i++)u[i]=b.charCodeAt(i);var url=URL.createObjectURL(new Blob([u],{type:"application/pdf"}));'
        . 'var a=document.getElementById("dl");a.href=url;a.download=' . json_encode($fileName) . ';a.click();})();</script>'
        . '</body></html>';
    exit;
}

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
    @header('HTTP/1.1 401 Unauthorized');
    @header('Content-Type: text/plain; charset=utf-8');
    die('Login required.');
}

$safeUser = mysqli_real_escape_string($ahk_conn, (string)$s_phone);
$row = @mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM dl_pdf_s3 WHERE id=$id AND username='$safeUser' LIMIT 1"));
if (!$row) {
    @header('HTTP/1.1 404 Not Found');
    @header('Content-Type: text/plain; charset=utf-8');
    die('PDF record not found.');
}

$fileName = trim((string)($row['file_name'] ?? ''));
if ($fileName === '') {
    $fileName = 'DL_' . preg_replace('/[^A-Za-z0-9]/', '', (string)$row['dl_no']) . '_S3.pdf';
}
$fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);

// 1) Serve the PDF stored locally at generation time
$stored = trim((string)($row['pdf'] ?? ''));
if ($stored !== '') {
    $b64 = $stored;
    if (preg_match('#^data:[a-z0-9.+-]+/[a-z0-9.+-]+;base64,(.*)$#is', $stored, $m)) {
        $b64 = (string)$m[1];
    }
    $bin = @base64_decode(preg_replace('/\s+/', '', $b64), true);
    if (is_string($bin) && strncmp($bin, '%PDF', 4) === 0) {
        dl_s3_send_pdf($bin, $fileName);
    }
}

// 2) Fallback: fetch from pariprint by request id (same as RC S5 download)
$reqId = (isset($row['request_id']) && ctype_digit((string)$row['request_id'])) ? (int)$row['request_id'] : 0;
if ($reqId > 0) {
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
    if ($pp_key !== '' && function_exists('curl_init')) {
        // Fetch server-side so the PariPrint key is never exposed to the browser
        $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in');
        $fetchUrl = $apiBase . '/api-service-download.php?source=reseller&id=' . $reqId . '&api_key=' . rawurlencode($pp_key);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $fetchUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $bin = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($bin) && $code === 200 && strncmp($bin, '%PDF', 4) === 0) {
            @mysqli_query($ahk_conn, "UPDATE dl_pdf_s3 SET pdf='" . mysqli_real_escape_string($ahk_conn, 'data:application/pdf;base64,' . base64_encode($bin)) . "' WHERE id=$id AND username='$safeUser'");
            dl_s3_send_pdf($bin, $fileName);
        }
    }
}

@header('HTTP/1.1 404 Not Found');
@header('Content-Type: text/plain; charset=utf-8');
die('Unable to fetch the PDF. Please regenerate from Instant DL Pdf Server 3 page.');
