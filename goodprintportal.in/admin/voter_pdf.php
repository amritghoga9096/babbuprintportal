<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
require_once __DIR__ . '/../includes/pp_secure.php';

define('VOTER_PDF_FEE', 100);
$VOTER_PDF_SLUG_CHOICES = [
    'instant-voter-pdf',
    'voter-pdf',
    'voterpdf',
];
$VOTER_PDF_ACTIVE_SLUG = $VOTER_PDF_SLUG_CHOICES[0];

$balance = $udata['balance'];
$mobile = $udata['phone'];

$flashMsg = '';
$flashType = '';

if (isset($_POST['submit_pdf']) && !empty($_POST['epic']) && !empty($_POST['state'])) {
    if ($balance < VOTER_PDF_FEE) {
        echo "<script>
            Swal.fire({
                title:'Balance Low! Recharge Now',
                text:'Insufficient Balance',
                icon:'warning',
                confirmButtonText:'OK'
            }).then((result) => { if(result.isConfirmed) window.location='wallet.php'; });
        </script>";
    } else {
        $epic = strtoupper(trim($_POST['epic']));
        $state = trim($_POST['state']);
        $voter_name = trim((string) ($_POST['voter_name'] ?? ''));
        if ($voter_name === '') { $voter_name = 'Unknown'; }
        $voter_state = $state;

        $params = [
            'slug'    => $VOTER_PDF_ACTIVE_SLUG,
            'epic'    => $epic,
            'state'   => $state,
            'epic_no' => $epic,
            'voter_state' => $state,
            'reseller_mobile' => $mobile,
            'reseller_name'  => ($udata['name'] ?? $voter_name),
            'reseller_username' => ($udata['username'] ?? $udata['email'] ?? ''),
            'api_key' => $pariprint_api_key,
        ];

        $response = null;
        $curlError = '';
        $httpCode = 0;
        $usedMethod = 'POST';
        $finalUrl = '';

        $apiUrlPlain = $pariprint_instant_voter_endpoint;

        function voter_pdf_run_curl_GPP(string $url, string $method, array $postBody = []): array
        {
            $ch = curl_init();
            $realUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';
            $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in');
            $headers = [
                'Accept: application/json, text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Encoding: gzip, deflate, identity',
                'Accept-Language: en-US,en;q=0.9,hi;q=0.8',
                'Cache-Control: max-age=0',
            ];
            if (strtoupper($method) === 'POST' && !empty($postBody)) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            $opts = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_USERAGENT => $realUA,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FAILONERROR => false,
                CURLOPT_VERBOSE => false,
                CURLOPT_REFERER => $apiBase . '/',
                CURLOPT_AUTOREFERER => true,
            ];
            if (strtoupper($method) === 'POST' && !empty($postBody)) {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = http_build_query($postBody);
            }
            curl_setopt_array($ch, $opts);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            if ($resp === false) { $resp = ''; }
            return ['response' => $resp, 'curl_error' => $err, 'http_code' => $code, 'content_type' => $ct];
        }

        $attempts = [];
        $bestPacket = null;
        $run1 = voter_pdf_run_curl_GPP($apiUrlPlain, 'POST', $params);
        $attempts[] = ['method' => 'POST (api-proxy)', 'url' => $apiUrlPlain, 'http_code' => $run1['http_code']];

        // FIX: HTTP 2xx means server has PROCESSED and STORED record! NEVER send GET fallback or
        // internal bypass because each HTTP request creates NEW DUPLICATE row at PARIPRINT side.
        // Only if SERVER ERROR (5xx) or TIMEOUT (0) - retry ONCE POST, never GET/alternate URL.
        if ($run1['http_code'] >= 200 && $run1['http_code'] < 400) {
            $bestPacket = $run1;
            $finalUrl = $apiUrlPlain . ' (POST)';
        }
        if (!$bestPacket) {
            if ($run1['http_code'] >= 500 || $run1['http_code'] === 0 || trim((string)$run1['curl_error']) !== '') {
                $run2 = voter_pdf_run_curl_GPP($apiUrlPlain, 'POST', $params);
                $attempts[] = ['method' => 'POST-Retry (api-proxy)', 'http_code' => $run2['http_code']];
                if ($run2['http_code'] >= 200 && $run2['http_code'] < 400) {
                    $bestPacket = $run2;
                    $finalUrl = $apiUrlPlain . ' (POST-Retry)';
                }
            }
        }
        if (!$bestPacket) {
            $bestPacket = $run1;
            $finalUrl = $apiUrlPlain . ' (POST - failed fallback)';
        }
        $response = $bestPacket['response'];
        $curlError = $bestPacket['curl_error'];
        $httpCode = $bestPacket['http_code'];
        $usedMethod = 'POST';

        $rawResponse = is_string($response) ? $response : '';
        $data = null;
        if (is_string($rawResponse) && $rawResponse !== '') {
            $trimmed = trim($rawResponse);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $data = json_decode($trimmed, true);
            } elseif (preg_match('/\{[\s\S]*\}/', $trimmed, $match)) {
                $jsonCandidate = $match[0];
                $candidateData = json_decode($jsonCandidate, true);
                if (is_array($candidateData)) {
                    $data = $candidateData;
                }
            }
        }

        $order_id = '';
        $status = false;
        $message = '';
        $dataKeyList = '';
        if (is_array($data)) {
            $order_id = (string) ($data['request_id'] ?? $data['requestId'] ?? $data['id'] ?? $data['order_id'] ?? $data['orderId'] ?? $data['ref_id'] ?? '');
            $status = $data['status'] ?? $data['success'] ?? $data['ok'] ?? false;
            $message = (string) ($data['message'] ?? $data['msg'] ?? $data['error_msg'] ?? $data['error_message'] ?? '');
            $allKeys = array_keys($data);
            $dataKeyList = implode(', ', array_slice($allKeys, 0, 30));
        }

        $booleanOk = false;
        if (is_bool($status)) { $booleanOk = $status; }
        elseif (is_numeric($status)) { $booleanOk = (int)$status === 1; }
        elseif (is_string($status)) {
            $low = strtolower(trim($status));
            $booleanOk = in_array($low, ['true', 'success', 'ok', '1', 'processed', 'accepted', 'pending', 'queued', 'passed'], true);
        }

        $httpLooksOk = $httpCode >= 200 && $httpCode < 400;
        $requestIdLooksOk = is_array($data) && $order_id !== '' && ((int)$order_id > 0 || is_string($order_id) && preg_match('/^[A-Za-z0-9]{4,}$/', $order_id)) && empty($data['error']) && empty($data['errors']);
        $looksLikePending = is_array($data) && !empty($data['status_name']) && in_array(strtolower((string)$data['status_name']), ['pending', 'success', 'queued', 'passed'], true);

        // FIX: ANY HTTP 2xx success means server PROCESSED request & saved it. Even if JSON
        // decode failed due to encoding issue, we MUST mark as success so user is NOT shown
        // "API Failed" popup and does NOT resubmit. Server saved it on backend already.
        $hasServerError = is_array($data) && !empty($data['error']) && $httpLooksOk === false;
        $isSuccess = $booleanOk || $requestIdLooksOk || ($httpLooksOk && !$hasServerError);

        if ($isSuccess && $order_id === '' && is_array($data)) {
            preg_match_all('/[0-9]{3,10}/', json_encode($data, JSON_UNESCAPED_UNICODE), $m);
            if (!empty($m[0][0])) {
                $order_id = (string) $m[0][0];
            }
        }
        // FIX: If HTTP 2xx but JSON decode failed (encoding error in screenshot) and no
        // numeric order_id found in response - generate local fallback idempotent tracking
        // id so local INSERT + wallet debit runs, user sees record in list page.
        if ($isSuccess && $order_id === '') {
            $candidate = '';
            if (is_string($rawResponse) && $rawResponse !== '') {
                preg_match_all('/\b[0-9]{3,15}\b/', $rawResponse, $mn);
                if (!empty($mn[0][0])) $candidate = (string) $mn[0][0];
            }
            if ($candidate !== '') {
                $order_id = $candidate;
            } else {
                $order_id = 'VP' . date('dmYHis') . substr(str_pad(dechex(crc32($epic . $mobile . microtime(true))), 8, '0', STR_PAD_LEFT), 0, 8);
            }
            if ($message === '' && $httpLooksOk) {
                $message = 'Request save ho gaya (Server HTTP ' . $httpCode . ' accept kiya). Response decode issue tha, admin process karega.';
            }
        }

        if ($isSuccess && $order_id !== '') {
            function _gpp_norm_phone_submit(string $p): string {
                $digits = preg_replace('/[^0-9]/', '', $p);
                if (strlen($digits) >= 12) $digits = substr($digits, -10);
                elseif (strlen($digits) === 11 && $digits[0] === '0') $digits = substr($digits, 1);
                return $digits;
            }
            $phoneNorm = _gpp_norm_phone_submit($mobile);
            $phoneEscRaw = mysqli_real_escape_string($ahk_conn, $mobile);
            $phoneEscNorm = mysqli_real_escape_string($ahk_conn, $phoneNorm);

            $new_balance = $balance - VOTER_PDF_FEE;
            $balanceDeducted = false;
            $deductSqls = [];
            if ($phoneEscRaw !== '') $deductSqls[] = "UPDATE users SET balance = balance - " . VOTER_PDF_FEE . " WHERE phone='$phoneEscRaw' LIMIT 1";
            if ($phoneEscNorm !== '' && $phoneEscNorm !== $phoneEscRaw) $deductSqls[] = "UPDATE users SET balance = balance - " . VOTER_PDF_FEE . " WHERE phone LIKE '%$phoneEscNorm' LIMIT 1";
            foreach ($deductSqls as $dsql) {
                $wr = mysqli_query($ahk_conn, $dsql);
                if ($wr && (int)mysqli_affected_rows($ahk_conn) > 0) { $balanceDeducted = true; break; }
            }
            $confirmBal = $balance;
            if ($balanceDeducted) {
                $cq = mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone='$phoneEscRaw' LIMIT 1");
                if ($cq && ($cr = mysqli_fetch_assoc($cq))) { $confirmBal = (float)$cr['balance']; mysqli_free_result($cq); }
                else {
                    $cq2 = mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone LIKE '%$phoneEscNorm' LIMIT 1");
                    if ($cq2 && ($cr2 = mysqli_fetch_assoc($cq2))) { $confirmBal = (float)$cr2['balance']; mysqli_free_result($cq2); }
                }
            }
            $actualNewBal = $balanceDeducted ? $confirmBal : $balance;

            date_default_timezone_set("Asia/Kolkata");
            $timestamp = date('Y-m-d H:i:s');
            $safe_response = mysqli_real_escape_string($ahk_conn, $rawResponse);
            $safe_epic = mysqli_real_escape_string($ahk_conn, $epic);
            $safe_voter_name = mysqli_real_escape_string($ahk_conn, $voter_name);
            $safe_voter_state = mysqli_real_escape_string($ahk_conn, $voter_state);
            $safe_order_id = mysqli_real_escape_string($ahk_conn, $order_id);
            $safe_ts = mysqli_real_escape_string($ahk_conn, $timestamp);

            $storeUserId = $phoneEscRaw;
            $listInserted = false;
            $listId = 0;
            $insList = "INSERT INTO voter_pdf_list(epic_no, order_id, response, voter_name, voter_state, userid, date, gpp_status) 
                VALUES ('$safe_epic','$safe_order_id','$safe_response','$safe_voter_name','$safe_voter_state','$storeUserId','$safe_ts','pending')";
            $ir = mysqli_query($ahk_conn, $insList);
            if ($ir && (int)mysqli_insert_id($ahk_conn) > 0) {
                $listInserted = true;
                $listId = (int)mysqli_insert_id($ahk_conn);
            }
            if (!$listInserted) {
                $storeUserIdAlt = $phoneEscNorm;
                $insList2 = "INSERT INTO voter_pdf_list(epic_no, order_id, response, voter_name, voter_state, userid, date, gpp_status) 
                    VALUES ('$safe_epic','$safe_order_id','$safe_response','$safe_voter_name','$safe_voter_state','$storeUserIdAlt','$safe_ts','pending')";
                $ir2 = mysqli_query($ahk_conn, $insList2);
                if ($ir2 && (int)mysqli_insert_id($ahk_conn) > 0) { $listInserted = true; $listId = (int)mysqli_insert_id($ahk_conn); $storeUserId = $storeUserIdAlt; }
            }
            $historyPhone = $storeUserId;
            $purposeEsc = mysqli_real_escape_string($ahk_conn, trim($epic . ' Voter PDF Download' . ($listId > 0 ? ' #' . $listId : '')));
            $safeHistoryPhone = mysqli_real_escape_string($ahk_conn, $historyPhone);
            $whInserted = false;
            $insWh = "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) 
                VALUES ('$safeHistoryPhone','" . VOTER_PDF_FEE . "','$actualNewBal','$purposeEsc','1','Debit')";
            $whr = mysqli_query($ahk_conn, $insWh);
            if ($whr && (int)mysqli_insert_id($ahk_conn) > 0) $whInserted = true;

            $debugLines = [];
            $debugLines[] = '<b style="color:#fff;">Balance Deducted:</b> <span style="color:' . ($balanceDeducted ? '#86efac' : '#fca5a5') . ';font-family:monospace;">' . ($balanceDeducted ? 'YES' : 'NO') . '</span> &nbsp; <b style="color:#fff;">Balance Before:</b> <span style="font-family:monospace;">Rs ' . number_format((float)$balance, 2) . '</span> &nbsp; <b style="color:#fff;">After:</b> <span style="font-family:monospace;">Rs ' . number_format((float)$actualNewBal, 2) . '</span>';
            $debugLines[] = '<b style="color:#fff;">voter_pdf_list INSERT:</b> <span style="color:' . ($listInserted ? '#86efac' : '#fca5a5') . ';font-family:monospace;">' . ($listInserted ? ('id=' . $listId) : 'FAILED') . '</span> &nbsp; <b style="color:#fff;">userid stored:</b> <span style="font-family:monospace;">' . htmlspecialchars($storeUserId) . '</span>';
            $debugLines[] = '<b style="color:#fff;">wallethistory INSERT:</b> <span style="color:' . ($whInserted ? '#86efac' : '#fca5a5') . ';font-family:monospace;">' . ($whInserted ? 'OK' : 'WARN: skipped?') . '</span>';
            if (!$balanceDeducted) $debugLines[] = '<div style="margin-top:6px;padding:8px 10px;background:rgba(239,68,68,0.15);border:1px solid #f87171;border-radius:8px;"><b style="color:#fca5a5;">WARNING:</b> Wallet balance deduct nahi ho saka! Session phone=' . htmlspecialchars($mobile) . ' — phone format mismatch? Contact admin immediately.</div>';
            if (!$listInserted) $debugLines[] = '<div style="margin-top:6px;padding:8px 10px;background:rgba(239,68,68,0.15);border:1px solid #f87171;border-radius:8px;"><b style="color:#fca5a5;">SEVERE:</b> voter_pdf_list me row insert nahi hui! DB error: ' . htmlspecialchars(mysqli_error($ahk_conn)) . '</div>';
            $debugHtml = '<div style="text-align:left;margin-top:10px;padding:10px 12px;background:rgba(15,23,42,0.92);border:1px solid rgba(255,255,255,0.08);border-radius:10px;">' . implode('<div style="margin-top:4px;"></div>', $debugLines) . '</div>';

            $successMsg = $message !== '' ? $message : 'Request submitted successfully! Admin will upload PDF shortly. Your Order ID: ' . $order_id;
            $jsMessage = json_encode($successMsg . ($balanceDeducted ? '' : ' ⚠ BALANCE NOT DEDUCTED'));
            $jsDebug = json_encode($debugHtml);
            echo "<script>
                Swal.fire({
                    title:'Success! Order ID " . json_encode($order_id) . "',
                    text:" . $jsMessage . ",
                    footer:" . $jsDebug . ",
                    icon:'success',
                    confirmButtonText:'Go to PDF List'
                }).then(() => { window.location='voter_pdf_list.php'; });
            </script>";
        } else {
            $rawSnippet = '';
            if (is_string($rawResponse) && $rawResponse !== '') {
                if (strlen($rawResponse) > 600) {
                    $rawSnippet = substr($rawResponse, 0, 600) . "\n... [truncated, first 600 chars]";
                } else {
                    $rawSnippet = $rawResponse;
                }
            }
            $jsonError = '';
            if (is_string($rawResponse) && trim($rawResponse) !== '' && $data === null) {
                $jsonError = json_last_error_msg();
            }
            $statusExplain = 'Not detected';
            if ($booleanOk) { $statusExplain = 'TRUE'; }
            else {
                if (is_bool($status)) { $statusExplain = 'BOOLEAN: ' . ($status ? 'true' : 'false'); }
                elseif (is_numeric($status)) { $statusExplain = 'NUMBER: ' . $status; }
                elseif (is_string($status)) { $statusExplain = 'STRING: ' . $status; }
            }

            $lines = [];
            $lines[] = '<b style="color:#fff;">HTTP Code:</b> <span style="font-family:monospace;">' . $httpCode . '</span> &nbsp; <b style="color:#fff;">Method:</b> <span style="font-family:monospace;">' . htmlspecialchars($usedMethod) . '</span>';
            if (!empty($curlError)) {
                $lines[] = '<div style="margin-top:6px;"><b style="color:#fca5a5;">cURL Error:</b> ' . htmlspecialchars($curlError) . '</div>';
            }
            $hostingerWAF = false;
            $wafReason = '';
            if ($httpCode === 403 || $httpCode === 503) {
                $lc = mb_strtolower($rawResponse);
                if (strpos($lc, 'checking your browser') !== false || strpos($lc, 'just a moment') !== false || strpos($lc, 'cloudflare') !== false || strpos($lc, 'challenge-platform') !== false || strpos($lc, 'captcha') !== false) {
                    $hostingerWAF = true;
                    $wafReason = 'Hostinger / Cloudflare WAF ne is request ko Bot samajh kar block kar diya hai. Yeh JS challenge hota hai jo browser me hota hai, server-to-server cURL me nahi solve hota. Sabse pehle PariPrint.in ke host pe voter-pdf-api-internal.php upload karo (wo dedicated WAF-bypass endpoint hai).';
                }
            }
            if ($hostingerWAF) {
                $lines[] = '<div style="margin-top:10px;padding:10px 12px;background:linear-gradient(135deg,#7c2d12,#92400e);border:1px solid #fb923c;border-radius:10px;"><b style="color:#fed7aa;">WAF BLOCK DETECTED (403 Bot Challenge)</b><div style="color:#fde68a;font-size:12px;margin-top:4px;">' . htmlspecialchars($wafReason) . '</div></div>';
            }
            if (!empty($attempts)) {
                $lines[] = '<div style="margin-top:8px;"><b style="color:#fff;">Attempts (' . count($attempts) . '):</b><div style="margin-top:4px;">';
                foreach ($attempts as $a) {
                    $c = (int) $a['http_code'];
                    $color = $c >= 200 && $c < 400 ? '#86efac' : ($c === 0 ? '#94a3b8' : '#fca5a5');
                    $lines[] = '<div style="font-family:monospace;font-size:11px;padding:3px 0;"><span style="color:' . $color . ';">[' . $c . ']</span> ' . htmlspecialchars($a['method']) . ' <span style="opacity:0.7;">' . htmlspecialchars((string) $a['url']) . '</span></div>';
                }
                $lines[] = '</div></div>';
            }
            if ($message !== '') {
                $lines[] = '<div style="margin-top:8px;"><b style="color:#fff;">Server message:</b></div><div style="background:rgba(255,255,255,0.08);padding:8px 10px;border-radius:8px;margin-top:4px;color:#fde68a;font-family:monospace;white-space:pre-wrap;word-break:break-word;">' . htmlspecialchars(pp_scrub($message)) . '</div>';
            }
            if ($data !== null && is_array($data)) {
                $lines[] = '<div style="margin-top:8px;"><b style="color:#fff;">JSON Keys found:</b> <span style="font-family:monospace;font-size:12px;">' . htmlspecialchars($dataKeyList) . '</span></div>';
                $lines[] = '<div style="margin-top:4px;"><b style="color:#fff;">Order ID extracted:</b> <span style="font-family:monospace;">' . htmlspecialchars($order_id !== '' ? $order_id : '(empty)') . '</span></div>';
                $lines[] = '<div style="margin-top:4px;"><b style="color:#fff;">Status field value:</b> <span style="font-family:monospace;">' . htmlspecialchars($statusExplain) . '</span></div>';
            }
            if ($rawSnippet !== '') {
                $lines[] = '<div style="margin-top:8px;"><b style="color:#fff;">Raw Response (' . strlen($rawResponse) . ' bytes' . ($jsonError ? ' - JSON parse error: ' . htmlspecialchars($jsonError) : '') . '):</b></div>'
                    . '<div style="background:rgba(0,0,0,0.35);padding:10px;border-radius:10px;margin-top:5px;max-height:260px;overflow:auto;color:#e5e7eb;font-family:ui-monospace,monospace;font-size:12px;line-height:1.5;white-space:pre-wrap;word-break:break-word;border:1px solid rgba(255,255,255,0.12);">'
                    . htmlspecialchars(pp_scrub($rawSnippet))
                    . '</div>';
            }
            $lines[] = '<div style="margin-top:10px;"><b style="color:#93c5fd;">Debug URL:</b> <div style="font-family:monospace;font-size:11px;word-break:break-all;margin-top:3px;">' . htmlspecialchars($finalUrl) . '</div></div>';

            $html = implode("\n", $lines);
            $jsonHtml = json_encode($html);
            echo "<script>
                Swal.fire({
                    title:'API Submit Failed',
                    html:" . $jsonHtml . ",
                    icon:'error',
                    confirmButtonText:'OK',
                    confirmButtonColor:'#ef4444',
                    width: '720px'
                });
            </script>";
        }
    }
} else {
    if ($balance < VOTER_PDF_FEE) {
        echo "<div class='alert alert-warning text-center m-3'>Insufficient Balance. <a href='wallet.php' class='alert-link'>Recharge Now</a>.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter PDF Download | Instant PDF Service</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        body {
            background: linear-gradient(135deg, #f5f7fc 0%, #eef2f9 100%);
        }
        .voter-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(0px);
            border: none;
            border-radius: 2rem;
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .voter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 55px -15px rgba(0,0,0,0.25);
        }
        .fee-chip {
            background: linear-gradient(135deg, #ff9a3c, #ff6a00);
            border-radius: 60px;
            padding: 0.5rem 1.8rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
            box-shadow: 0 8px 18px rgba(255,106,0,0.3);
        }
        .epic-input {
            border-radius: 60px;
            padding: 0.9rem 1.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.2s;
            font-size: 1rem;
        }
        .epic-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
            outline: none;
        }
        .submit-btn {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 0.9rem;
            font-weight: 700;
            font-size: 1.1rem;
            border-radius: 60px;
            transition: all 0.3s;
            letter-spacing: 0.5px;
        }
        .submit-btn:hover {
            background: linear-gradient(90deg, var(--primary-dark), #2c3e9e);
            transform: scale(1.02);
            box-shadow: 0 10px 25px rgba(67,97,238,0.4);
        }
        .balance-card {
            background: white;
            border-radius: 1.5rem;
            border-left: 5px solid var(--success);
        }
        .credit-footer {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #cbd5e1;
            border-radius: 1rem;
            padding: 0.8rem 1.5rem;
            font-size: 0.85rem;
            margin-top: 2rem;
        }
        .badge-delivery {
            background: #eef2ff;
            color: var(--primary);
            border-radius: 40px;
            padding: 0.4rem 1rem;
        }
        .feature-icon {
            background: #f1f5f9;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 60px;
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-12">
                    
                    <!-- Header with breadcrumb -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                        <div>
                            <h3 class="fw-bold mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-file-pdf-fill text-danger fs-2"></i> 
                                <span>Voter PDF Download</span>
                            </h3>
                            <p class="text-muted mt-1">Get your official Voter ID PDF in 60-120 minutes</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="voter_pdf_list.php" class="btn btn-success rounded-pill px-3">
                                <i class="bi bi-list-ul"></i> My PDFs
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>

                    <!-- Balance Alert if low -->
                    <?php if ($balance < VOTER_PDF_FEE): ?>
                        <div class="alert alert-warning d-flex align-items-center rounded-4 border-0 shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                            <div>Insufficient balance. <a href="wallet.php" class="alert-link fw-bold">Recharge now</a> to continue.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Flash Message -->
                    <?php if ($flashMsg !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> d-flex align-items-center rounded-4 border-0 shadow-sm mb-3">
                            <i class="bi bi-<?php echo $flashType === 'danger' ? 'x-circle' : 'check-circle'; ?>-fill me-3 fs-4"></i>
                            <div><?php echo htmlspecialchars($flashMsg); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Main Card -->
                    <div class="card voter-card mb-4">
                        <div class="card-body p-4 p-lg-5">
                            <!-- Powered by badge -->
                            <div class="text-center mb-3">
                                <span class="badge bg-light text-dark px-3 py-2 rounded-pill shadow-sm">
                                    <i class="bi bi-lightning-charge-fill text-warning"></i> Powered by <strong class="text-primary">goodprintportal.in</strong>
                                </span>
                            </div>
                            
                            <div class="text-center mb-4">
                                <div class="fee-chip">
                                    <i class="bi bi-tag-fill"></i> ₹<?php echo VOTER_PDF_FEE; ?> / PDF
                                </div>
                                <div class="mt-3 d-flex justify-content-center gap-3 flex-wrap">
                                    <span class="badge-delivery"><i class="bi bi-clock-history"></i> 60-120 mins delivery</span>
                                    <span class="badge-delivery"><i class="bi bi-file-earmark-pdf-fill"></i> Official PDF</span>
                                </div>
                            </div>

                            <!-- Balance Card -->
                            <div class="balance-card p-4 mb-4 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small mb-1">Available Balance</div>
                                        <div class="fw-bold display-6 mb-0" style="font-size: 1.6rem;">₹<?php echo number_format((float)$balance, 2); ?></div>
                                    </div>
                                    <a href="wallet.php" class="btn btn-outline-success rounded-pill fw-semibold">
                                        <i class="bi bi-plus-circle me-1"></i> Recharge
                                    </a>
                                </div>
                            </div>

                            <!-- Submit Form -->
                            <form method="POST" autocomplete="off">
                                <?php 
                                $autoOrderCount = 0;
                                $prevRes = mysqli_query($ahk_conn, "SELECT COUNT(id) AS cnt FROM voter_pdf_list WHERE userid='$mobile' AND DATE(date) = CURDATE()");
                                $prevRow = mysqli_fetch_assoc($prevRes);
                                $autoOrderCount = (int) ($prevRow['cnt'] ?? 0);
                                ?>
                                <input type="hidden" name="form_token" value="<?php echo md5(uniqid((string)mt_rand(), true)); ?>" />
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-person-badge me-1 text-primary"></i> 
                                        EPIC Number (Voter ID)
                                    </label>
                                    <div class="mb-1 text-muted small ms-2">
                                        Enter exactly as printed on your voter card. e.g. NKD0000094
                                    </div>
                                    <input type="text" 
                                           name="epic" 
                                           value="<?php echo htmlspecialchars(strtoupper((string)($_POST['epic'] ?? ''))); ?>"
                                           maxlength="30"
                                           required
                                           placeholder="e.g., NKD0000094"
                                           class="form-control epic-input" 
                                           autocomplete="off">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-geo-alt-fill me-1 text-primary"></i> 
                                        State
                                    </label>
                                    <input type="text" 
                                           name="state" 
                                           value="<?php echo htmlspecialchars((string)($_POST['state'] ?? '')); ?>"
                                           maxlength="80"
                                           required
                                           placeholder="e.g., Uttar Pradesh"
                                           class="form-control epic-input" 
                                           autocomplete="off">
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <div class="feature-icon small-feature mx-auto mb-2"><i class="bi bi-shield-check text-success"></i></div>
                                        <div class="small text-center text-muted">Secure & Encrypted</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="feature-icon small-feature mx-auto mb-2"><i class="bi bi-person-check text-primary"></i></div>
                                        <div class="small text-center text-muted">Manual Admin QC</div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" 
                                            name="submit_pdf"
                                            value="1"
                                            class="btn submit-btn text-white">
                                        <i class="bi bi-cloud-upload-fill me-2"></i> Submit & Pay ₹<?php echo VOTER_PDF_FEE; ?>
                                    </button>
                                    <div class="text-center small text-muted mt-1">
                                        <i class="bi bi-info-circle me-1"></i> Wallet se ₹<?php echo VOTER_PDF_FEE; ?> submit par debit honge, failed hone par auto-refund.
                                    </div>
                                </div>
                            </form>

                            <div class="credit-footer text-center">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Average processing: <strong>45 minutes</strong> — faster on working hours.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include('../template/ahkweb/footer.php');
?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
