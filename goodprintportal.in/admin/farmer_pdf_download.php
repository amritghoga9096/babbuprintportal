<?php
include('../includes/session.php');
include('../includes/config.php');

date_default_timezone_set('Asia/Kolkata');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($recordId <= 0) {
    http_response_code(400);
    exit('Invalid request ID.');
}

if (!isset($_SESSION['phone']) || empty($_SESSION['phone'])) {
    http_response_code(403);
    exit('Login required.');
}

$safeUser = mysqli_real_escape_string($ahk_conn, (string)$_SESSION['phone']);
$safeId = mysqli_real_escape_string($ahk_conn, (string)$recordId);

$sql = "SELECT * FROM instant_farmer_pdf_requests WHERE id='{$safeId}' AND username='{$safeUser}' LIMIT 1";
$res = mysqli_query($ahk_conn, $sql);
$record = $res ? mysqli_fetch_assoc($res) : null;

if (!$record) {
    $isAdmin = false;
    if (!empty($udata['type'])) {
        $ut = strtolower((string)$udata['type']);
        $isAdmin = ($ut === 'admin' || $ut === 'administrator' || $ut === 'superadmin');
    }
    if ($isAdmin) {
        $sql2 = "SELECT * FROM instant_farmer_pdf_requests WHERE id='{$safeId}' LIMIT 1";
        $res2 = mysqli_query($ahk_conn, $sql2);
        $record = $res2 ? mysqli_fetch_assoc($res2) : null;
    }
}

if (!$record) {
    http_response_code(404);
    exit('Record not found or access denied.');
}

$fileName = !empty($record['file_name']) ? (string)$record['file_name'] : 'farmer-pdf-' . $recordId . '.pdf';
if (!preg_match('/\.pdf$/i', $fileName)) {
    $fileName .= '.pdf';
}
$fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName);

$json = json_decode((string)($record['result_json'] ?? ''), true);

function fp_find_pdf_value($data, $paths) {
    if (!is_array($data)) {
        return null;
    }
    foreach ($paths as $path) {
        $keys = explode('.', $path);
        $val = $data;
        $found = true;
        foreach ($keys as $k) {
            $altKeys = [$k];
            if (strpos($k, '|') !== false) {
                $altKeys = explode('|', $k);
            }
            $matched = false;
            foreach ($altKeys as $ak) {
                if (is_array($val) && (isset($val[$ak]) || array_key_exists($ak, $val))) {
                    $val = $val[$ak];
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $found = false;
                break;
            }
        }
        if ($found && is_scalar($val) && trim((string)$val) !== '') {
            return (string)$val;
        }
    }
    return null;
}

function fp_is_pdf_base64($v) {
    $v = trim((string)$v);
    if ($v === '' || strlen($v) < 32) {
        return false;
    }
    if (str_starts_with($v, 'data:application/pdf;base64,')) {
        return true;
    }
    return (bool)preg_match('/^JVBERi0[a-zA-Z0-9+\/=\r\n]+$/', $v);
}

function fp_extract_base64_bin($v) {
    $v = trim((string)$v);
    if (str_starts_with($v, 'data:application/pdf;base64,')) {
        $v = substr($v, strlen('data:application/pdf;base64,'));
    }
    $v = preg_replace('/\s+/', '', $v);
    $bin = base64_decode($v ?? '', true);
    if ($bin === false || !str_starts_with($bin, '%PDF')) {
        return null;
    }
    return $bin;
}

$searchPaths = [
    'pdf_base64',
    'pdf',
    'data.pdf_base64',
    'data.pdf',
    'result.pdf_base64',
    'result.pdf',
    'response.pdf_base64',
    'response.pdf',
    'output.pdf_base64',
    'output.pdf',
    'file_data',
    'data.file_data',
    'content',
    'data.content',
];

$pdfBinary = null;

if (is_array($json)) {
    $candidate = fp_find_pdf_value($json, $searchPaths);
    if ($candidate !== null && fp_is_pdf_base64($candidate)) {
        $pdfBinary = fp_extract_base64_bin($candidate);
        if ($pdfBinary !== null) {
            if (!empty($json['filename']) && is_scalar($json['filename'])) {
                $fn = trim((string)$json['filename']);
                if ($fn !== '') {
                    $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fn);
                    if (!preg_match('/\.pdf$/i', $fileName)) $fileName .= '.pdf';
                }
            } elseif (!empty($json['file_name']) && is_scalar($json['file_name'])) {
                $fn = trim((string)$json['file_name']);
                if ($fn !== '') {
                    $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fn);
                    if (!preg_match('/\.pdf$/i', $fileName)) $fileName .= '.pdf';
                }
            }
        }
    }
}

if ($pdfBinary === null) {
    $downloadUrl = trim((string)($record['download_url'] ?? ''));
    if ($downloadUrl === '' && is_array($json)) {
        $urlPaths = ['download_url','download','download_link','pdf_url','url','file_url','result_url','data.download_url','data.url','data.download_link'];
        $downloadUrl = (string)(fp_find_pdf_value($json, $urlPaths) ?? '');
    }

    if ($downloadUrl !== '') {
        $apiKeyForFetch = $pariprint_api_key ?? '';
        if (str_contains($downloadUrl, 'api-service-download.php')) {
            $requestIdRaw = '';
            $parts = parse_url($downloadUrl);
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $qparams);
                $requestIdRaw = isset($qparams['id']) ? (string)$qparams['id'] : '';
            }
            if ($requestIdRaw === '' && !empty($record['order_id'])) {
                $requestIdRaw = (string)$record['order_id'];
            }
            if ($requestIdRaw !== '' && $apiKeyForFetch !== '') {
                $slug = 'instant-farmer-pdf';
                $downloadUrl = rtrim(($CENTRAL_API_BASE ?? 'https://pariprint.in'), '/')
                    . '/api-proxy.php?slug=' . rawurlencode($slug)
                    . '&order_id=' . rawurlencode($requestIdRaw)
                    . '&download=1'
                    . '&api_key=' . rawurlencode($apiKeyForFetch);
            } elseif ($apiKeyForFetch !== '') {
                $sep = str_contains($downloadUrl, '?') ? '&' : '?';
                $downloadUrl .= $sep . 'api_key=' . rawurlencode($apiKeyForFetch);
            }
        } elseif ($apiKeyForFetch !== '' && str_contains($downloadUrl, ($CENTRAL_API_BASE ?? 'https://pariprint.in'))) {
            $sep = str_contains($downloadUrl, '?') ? '&' : '?';
            $downloadUrl .= $sep . 'api_key=' . rawurlencode($apiKeyForFetch);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $downloadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (mansijanseva-farmer-pdf)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/pdf, application/octet-stream, */*'
            ],
        ]);
        $fetched = curl_exec($ch);
        $httpCode = 0;
        $ct = '';
        $disp = '';
        if ($fetched !== false) {
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ct = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
            $disp = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_DISPOSITION));
        }
        curl_close($ch);

        if ($fetched !== false && $httpCode >= 200 && $httpCode < 300) {
            $looksPdf = false;
            if (str_contains($ct, 'pdf') || str_contains($ct, 'octet-stream') || str_contains($disp, 'attachment') || str_contains($disp, '.pdf')) {
                $looksPdf = true;
            }
            if (str_starts_with($fetched, '%PDF')) {
                $looksPdf = true;
            }
            if (!$looksPdf && strlen($fetched) > 200000) {
                $looksPdf = true;
            }
            if ($looksPdf) {
                $pdfBinary = $fetched;
                if (preg_match('/filename\*=UTF-8\'\'([^;&]+)/i', $disp, $m) || preg_match('/filename="?([^";]+)"?/i', $disp, $m)) {
                    $fn = trim(urldecode($m[1]));
                    if ($fn !== '' && preg_match('/\.pdf$/i', $fn)) {
                        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fn);
                    }
                }
            }
        }
    }
}

if ($pdfBinary === null) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>PDF Not Available</title><style>';
    echo 'body{font-family:system-ui,sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1.5rem;}';
    echo '.card{max-width:520px;width:100%;background:white;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.12);padding:2.25rem;}';
    echo 'h1{color:#dc2626;margin:0 0 .75rem;font-size:1.4rem;}p{color:#475569;line-height:1.6;margin:.5rem 0;}';
    echo '.btn{display:inline-block;margin-top:1.25rem;padding:.7rem 1.3rem;background:#4361ee;color:white;text-decoration:none;border-radius:10px;font-weight:600;}';
    echo '</style></head><body><div class="card"><h1>PDF Not Available</h1>';
    echo '<p>PDF data server par stored format me nahi mila. Ya to original response me base64 PDF nahi tha ya remote download fail ho gaya.</p>';
    echo '<p>Try: Original request ka <strong>Raw API Response</strong> check karein ya request dobara submit karein.</p>';
    echo '<a class="btn" href="instant_farmer_pdf_list">Back to List</a>';
    echo '</div></body></html>';
    exit;
}

$size = strlen($pdfBinary);
if (ob_get_level()) {
    while (ob_get_level() > 0) ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
if ($size > 0) {
    header('Content-Length: ' . $size);
}

echo $pdfBinary;
flush();
exit;
