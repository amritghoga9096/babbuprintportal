<?php 
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Fixed fee (from API response shows 40)
define('LL_EXAM_FEE', 80);
define('LL_EXAM_SERVICE_SLUG', 'll-exam-request');

$balance = $udata['balance'];
$mobile = $udata['phone'];
$user_full_name = $udata['name'] ?? $udata['full_name'] ?? '';
$user_email = $udata['email'] ?? '';

if (isset($_POST['submit_exam']) && !empty($_POST['application_no']) && !empty($_POST['dob']) && !empty($_POST['password']) && !empty($_POST['state'])) {
    
    if ($balance < LL_EXAM_FEE) {
        echo "<script>
            Swal.fire({
                title:'Balance Low! Recharge Now',
                text:'Insufficient Balance',
                icon:'warning',
                confirmButtonText:'OK'
            }).then((result) => { if(result.isConfirmed) window.location='wallet.php'; });
        </script>";
    } else {
        $application_no = trim($_POST['application_no']);
        $applicant_name = trim((string)($_POST['applicant_name'] ?? ''));
        if ($applicant_name === '') {
            $applicant_name = $user_full_name !== '' ? $user_full_name : ($user_email !== '' ? $user_email : 'Unknown');
        }
        $dob_raw = trim($_POST['dob']);
        $password = trim($_POST['password']);
        $exam_pin = trim((string)($_POST['exam_pin'] ?? ''));
        $state = trim($_POST['state']);

        $dob = $dob_raw;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob_raw, $m)) {
            $dob = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in');
        $apiUrl = $apiBase . "/api-proxy.php";
        
        $postParams = [
            'slug'         => LL_EXAM_SERVICE_SLUG,
            'app_no'       => $application_no,
            'application_no' => $application_no,
            'dob'          => $dob,
            'password'     => $password,
            'exam_pin'     => $exam_pin,
            'state'        => $state,
            'applicant_name' => $applicant_name,
            'api_key'      => $pariprint_api_key
        ];

        function ll_exam_run_curl_GPP(string $url, string $method, array $postBody = []): array
        {
            $ch = curl_init();
            $realUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';
            $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in');
            $headers = [
                'Accept: application/json, text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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
                CURLOPT_TIMEOUT => 120,
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
            curl_close($ch);
            if ($resp === false) { $resp = ''; }
            return ['response' => $resp, 'curl_error' => $err, 'http_code' => $code];
        }

        $attempts = [];
        $bestPacket = null;

        $run1 = ll_exam_run_curl_GPP($apiUrl, 'POST', $postParams);
        $attempts[] = ['method' => 'POST (api-proxy)', 'http_code' => $run1['http_code']];

        // FIX: No GET fallback retry! Each HTTP hit creates NEW duplicate record on server.
        // Also HTTP 2xx means server processed (even if response decode failed due to encoding)
        if ($run1['http_code'] >= 200 && $run1['http_code'] < 400) {
            $bestPacket = $run1;
        }
        if (!$bestPacket) {
            // ONLY retry if server error, not on success (prevents double insert)
            if ($run1['http_code'] >= 500 || $run1['http_code'] === 0) {
                $run2 = ll_exam_run_curl_GPP($apiUrl, 'POST', $postParams);
                $attempts[] = ['method' => 'POST-Retry (api-proxy)', 'http_code' => $run2['http_code']];
                if ($run2['http_code'] >= 200 && $run2['http_code'] < 400) {
                    $bestPacket = $run2;
                }
            }
        }
        if (!$bestPacket) {
            $bestPacket = $run1;
        }

        $response = $bestPacket['response'];
        $curlError = $bestPacket['curl_error'];
        $httpCode = $bestPacket['http_code'];

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

        $requestId = '';
        $status = false;
        $message = '';
        if (is_array($data)) {
            $requestId = (string) ($data['request_id'] ?? $data['order_id'] ?? $data['id'] ?? $data['ref_id'] ?? '');
            $status = $data['status'] ?? $data['success'] ?? false;
            $message = (string) ($data['message'] ?? '');
        }

        $booleanOk = false;
        if (is_bool($status)) { $booleanOk = $status; }
        elseif (is_numeric($status)) { $booleanOk = (int)$status === 1; }
        elseif (is_string($status)) {
            $low = strtolower(trim($status));
            $booleanOk = in_array($low, ['true', 'success', 'ok', '1', 'processed', 'accepted', 'pending', 'queued'], true);
        }

        $httpLooksOk = $httpCode >= 200 && $httpCode < 400;
        $requestIdLooksOk = is_array($data) && $requestId !== '' && empty($data['error']);

        // FIX: HTTP 2xx means server has PROCESSED request (backend saved record) even if
        // we could not decode response body (due to encoding / Brotli / libcurl issue etc.)
        // We MUST treat as success and NOT show user error, otherwise user resubmits + double-charges.
        $hasServerError = is_array($data) && !empty($data['error']) && $httpLooksOk === false;
        $isSuccess = $booleanOk || ($httpLooksOk && !$hasServerError);

        if ($isSuccess && $requestId === '' && is_array($data)) {
            preg_match_all('/[0-9]{3,10}/', json_encode($data, JSON_UNESCAPED_UNICODE), $m);
            if (!empty($m[0][0])) {
                $requestId = (string) $m[0][0];
            }
        }
        // FIX (request_id fallback): If server returned HTTP 2xx but JSON decode failed
        // (common case - encoding error in screenshot) we still need a tracking id for
        // our local DB record. Generate an idempotent local tracking ID (app_no + date hash)
        // so user can see record in list and later Check Status poll finds by app_id anyway.
        if ($isSuccess && $requestId === '') {
            $candidate = '';
            if (is_string($rawResponse) && $rawResponse !== '') {
                preg_match_all('/\b[0-9]{3,15}\b/', $rawResponse, $mn);
                if (!empty($mn[0][0])) $candidate = (string) $mn[0][0];
            }
            if ($candidate !== '') {
                $requestId = $candidate;
            } else {
                $requestId = 'LL' . date('dmYHis') . substr(str_pad(dechex(crc32($application_no . $mobile . microtime(true))), 8, '0', STR_PAD_LEFT), 0, 8);
            }
            if ($message === '' && $httpLooksOk) {
                $message = 'Request save ho gaya hai (Server HTTP ' . $httpCode . ' accept kiya). Response decode issue tha, admin se request process hogi.';
            }
        }

        if ($isSuccess && $requestId !== '') {
            $fee = LL_EXAM_FEE;
            $new_balance_calc = $balance - $fee;

            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - " . $fee . " WHERE phone='$mobile'");
            
            date_default_timezone_set("Asia/Kolkata");
            $timestamp = date('d/m/Y g:i:s');
            
            $statusForDb = 'pending';
            $msgLower = strtolower($message);
            if (strpos($msgLower, 'pass') !== false || strpos($msgLower, 'successful') !== false) {
                $statusForDb = 'passed';
            } elseif (strpos($msgLower, 'fail') !== false || strpos($msgLower, 'reject') !== false) {
                $statusForDb = 'rejected';
            }

            $safe_app_no = mysqli_real_escape_string($ahk_conn, $application_no);
            $safe_app_name = mysqli_real_escape_string($ahk_conn, $applicant_name);
            $safe_dob = mysqli_real_escape_string($ahk_conn, $dob_raw);
            $safe_pwd = mysqli_real_escape_string($ahk_conn, $password);
            $safe_exam_pin = mysqli_real_escape_string($ahk_conn, $exam_pin);
            $safe_state = mysqli_real_escape_string($ahk_conn, $state);
            $safe_tracking = mysqli_real_escape_string($ahk_conn, $requestId);
            $safe_api_txn = mysqli_real_escape_string($ahk_conn, $requestId);
            $safe_status = mysqli_real_escape_string($ahk_conn, $statusForDb);
            $safe_mobile = mysqli_real_escape_string($ahk_conn, $mobile);
            $safe_date = mysqli_real_escape_string($ahk_conn, $timestamp);
            $safe_fee = mysqli_real_escape_string($ahk_conn, $fee);

            $insert = mysqli_query($ahk_conn, "INSERT INTO ll_exam_list(
                application_no, 
                applicant_name, 
                dob, 
                password,
                exam_pin,
                state,
                tracking_id, 
                api_transaction_id, 
                status, 
                userid, 
                date, 
                amount
            ) VALUES (
                '$safe_app_no', 
                '$safe_app_name', 
                '$safe_dob', 
                '$safe_pwd',
                '$safe_exam_pin',
                '$safe_state',
                '$safe_tracking', 
                '$safe_api_txn', 
                '$safe_status', 
                '$safe_mobile', 
                '$safe_date', 
                '$safe_fee'
            )");
            
            $safe_new_bal = mysqli_real_escape_string($ahk_conn, $new_balance_calc);
            $safe_purpose = mysqli_real_escape_string($ahk_conn, 'LL Exam - ' . $applicant_name);
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) 
                VALUES ('$safe_mobile', '$safe_fee', '$safe_new_bal', '$safe_purpose', '1', 'Debit')");
            
            $displayMsg = "LL Exam Request Submitted Successfully!\n\nApp No: $application_no\nName: $applicant_name\nDOB: $dob\nState: $state\n\nOrder ID: $requestId\n\nAdmin abhi request process karega.";
            $displayMsgJs = json_encode($displayMsg, JSON_UNESCAPED_UNICODE);

            echo "<script>
                Swal.fire({
                    title:'Success!',
                    text: $displayMsgJs,
                    icon:'success',
                    confirmButtonText:'Go to LL Exam List'
                }).then(() => { window.location='ll_request_list.php'; });
            </script>";
        } else {
            $rawSnippet = '';
            if (is_string($rawResponse) && $rawResponse !== '' && strlen($rawResponse) > 600) {
                $rawSnippet = substr($rawResponse, 0, 600) . "\n... [truncated]";
            } else {
                $rawSnippet = $rawResponse;
            }

            $lines = [];
            $lines[] = '<b style="color:#fff;">HTTP Code:</b> <span style="font-family:monospace;">' . $httpCode . '</span>';
            if (!empty($curlError)) {
                $lines[] = '<div style="margin-top:6px;"><b style="color:#fca5a5;">cURL Error:</b> ' . htmlspecialchars($curlError) . '</div>';
            }
            if ($message !== '') {
                $lines[] = '<div style="margin-top:8px;"><b style="color:#fff;">Server message:</b></div><div style="background:rgba(255,255,255,0.08);padding:8px 10px;border-radius:8px;margin-top:4px;color:#fde68a;font-family:monospace;white-space:pre-wrap;word-break:break-word;">' . htmlspecialchars($message) . '</div>';
            }
            if ($rawSnippet !== '') {
                $lines[] = '<div style="margin-top:8px;"><b style="color:#fff;">Raw Response:</b></div>'
                    . '<div style="background:rgba(0,0,0,0.35);padding:10px;border-radius:10px;margin-top:5px;max-height:260px;overflow:auto;color:#e5e7eb;font-family:ui-monospace,monospace;font-size:12px;line-height:1.5;white-space:pre-wrap;word-break:break-word;border:1px solid rgba(255,255,255,0.12);">'
                    . htmlspecialchars($rawSnippet)
                    . '</div>';
            }
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Licence Exam | Apply Online</title>
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
        .exam-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(0px);
            border: none;
            border-radius: 2rem;
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .exam-card:hover {
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
        .form-input {
            border-radius: 60px;
            padding: 0.9rem 1.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.2s;
            font-size: 1rem;
        }
        .form-input:focus {
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

        /* ====== LL EXAM 2-STEP NOTICE ====== */
        .ll-step-notice {
            margin: 0 0 28px 0;
            border-radius: 1.2rem;
            background: linear-gradient(180deg, #fffbeb 0%, #fff7ed 100%);
            border: 1.5px solid rgba(245, 158, 11, 0.28);
            box-shadow: 0 10px 30px -12px rgba(245, 158, 11, 0.25),
                        0 2px 6px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            position: relative;
        }
        .ll-step-notice::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f59e0b, #ef4444, #f59e0b);
            background-size: 200% 100%;
            animation: ll-bar-sweep 3.5s linear infinite;
        }
        @keyframes ll-bar-sweep {
            0%   { background-position: 0% 0; }
            100% { background-position: 200% 0; }
        }

        .ll-step-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(239, 68, 68, 0.1));
            border-bottom: 1px dashed rgba(245, 158, 11, 0.35);
            font-weight: 800;
            color: #92400e;
            font-size: 14.5px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .ll-step-header i {
            color: #f59e0b;
            font-size: 20px;
            animation: ll-wiggle 2s ease-in-out infinite;
        }
        @keyframes ll-wiggle {
            0%,100% { transform: rotate(0deg); }
            25%     { transform: rotate(-10deg); }
            75%     { transform: rotate(10deg); }
        }

        .ll-step-list {
            padding: 18px 20px 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .ll-step {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .ll-step-no {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 17px;
            color: #fff;
            flex-shrink: 0;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.35),
                        inset 0 1px 0 rgba(255,255,255,0.25);
        }
        .ll-step:nth-child(2) .ll-step-no {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.35),
                        inset 0 1px 0 rgba(255,255,255,0.25);
        }
        .ll-step-body {
            flex: 1;
        }
        .ll-step-title {
            font-weight: 800;
            font-size: 15.5px;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .ll-step-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.55;
            margin-bottom: 10px;
        }
        .ll-step-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            color: #fff;
            text-decoration: none !important;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .ll-step-action-blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .ll-step-action-blue:hover {
            transform: translateY(-2px);
            color: #fff;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.42);
        }
        .ll-step-action-red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.32);
        }
        .ll-step-action-red:hover {
            transform: translateY(-2px);
            color: #fff;
            box-shadow: 0 8px 18px rgba(239, 68, 68, 0.44);
        }

        .ll-step-footer {
            padding: 0 20px 18px 20px;
        }
        .ll-step-footer-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 13.5px;
            color: #065f46;
            font-weight: 600;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px dashed rgba(16, 185, 129, 0.45);
        }
        .ll-step-footer-bar i {
            font-size: 18px;
        }
        @media (max-width: 480px) {
            .ll-step-footer-bar { font-size: 12.5px; text-align: center; }
            .ll-step-header { font-size: 12.5px; }
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
                                <i class="bi bi-mortarboard-fill text-primary fs-2"></i> 
                                <span>Learning Licence Exam</span>
                            </h3>
                            <p class="text-muted mt-1">Apply for Learning Licence Exam - Results in 3-24 hours</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="ll_request_list.php" class="btn btn-success rounded-pill px-3">
                                <i class="bi bi-list-ul"></i> My Applications
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>

                    <!-- Balance Alert if low -->
                    <?php if ($balance < LL_EXAM_FEE): ?>
                        <div class="alert alert-warning d-flex align-items-center rounded-4 border-0 shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                            <div>Insufficient balance. <a href="wallet.php" class="alert-link fw-bold">Recharge now</a> to continue.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Main Card -->
                    <div class="card exam-card mb-4">
                        <div class="card-body p-4 p-lg-5">
                            <!-- Powered by badge -->
                            <div class="text-center mb-3">
                                <span class="badge bg-light text-dark px-3 py-2 rounded-pill shadow-sm">
                                    <i class="bi bi-lightning-charge-fill text-warning"></i> Powered by <strong class="text-primary">goodprintportal.in</strong>
                                </span>
                            </div>
                            
                            <div class="text-center mb-4">
                                <div class="fee-chip">
                                    <i class="bi bi-tag-fill"></i> ₹<?php echo LL_EXAM_FEE; ?> / Application
                                </div>
                                <div class="mt-3 d-flex justify-content-center gap-3 flex-wrap">
                                    <span class="badge-delivery"><i class="bi bi-clock-history"></i> 3-24 hours delivery</span>
                                    <span class="badge-delivery"><i class="bi bi-check-circle-fill text-success"></i> Instant Confirmation</span>
                                </div>
                            </div>
                            
                            <!-- Balance Card -->
                            <div class="balance-card p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm">
                                <div>
                                    <i class="bi bi-wallet2 fs-4 text-success"></i>
                                    <span class="fw-semibold ms-2">Available Balance</span>
                                </div>
                                <div class="fs-4 fw-bold">₹<?php echo number_format($balance, 2); ?></div>
                                <?php if ($balance >= LL_EXAM_FEE): ?>
                                    <a href="wallet.php" class="btn btn-sm btn-outline-secondary rounded-pill">Recharge</a>
                                <?php else: ?>
                                    <a href="wallet.php" class="btn btn-sm btn-warning rounded-pill">Add Money</a>
                                <?php endif; ?>
                            </div>

                            <!-- ====== IMPORTANT 2 STEP NOTICE (MANDATORY BEFORE REQUEST) ====== -->
                            <div class="ll-step-notice">
                                <div class="ll-step-header">
                                    <i class="bi bi-exclamation-diamond-fill"></i>
                                    <span>IMPORTANT — 2 MANDATORY STEPS</span>
                                </div>

                                <div class="ll-step-list">
                                    <div class="ll-step">
                                        <div class="ll-step-no">1</div>
                                        <div class="ll-step-body">
                                            <div class="ll-step-title">Schedule An Exam</div>
                                        </div>
                                    </div>

                                    <div class="ll-step">
                                        <div class="ll-step-no">2</div>
                                        <div class="ll-step-body">
                                            <div class="ll-step-title">Watch Full Tutorial Video</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="ll-step-footer">
                                    <div class="ll-step-footer-bar">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <span><strong>Done both steps 1 & 2?</strong> Then Place Request 👇</span>
                                    </div>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-upc-scan"></i> Application Number
                                    </label>
                                    <input type="text" 
                                           name="application_no" 
                                           class="form-control form-input" 
                                           placeholder="Enter your Application Number" 
                                           required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-person-fill"></i> Applicant Name
                                    </label>
                                    <input type="text" 
                                           name="applicant_name" 
                                           class="form-control form-input" 
                                           value="<?php echo htmlspecialchars($user_full_name !== '' ? $user_full_name : ($user_email !== '' ? $user_email : '')); ?>"
                                           placeholder="Enter Full Name">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-calendar-fill"></i> Date of Birth
                                    </label>
                                    <input type="date" 
                                           name="dob" 
                                           class="form-control form-input" 
                                           required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-key-fill"></i> Password
                                    </label>
                                    <input type="password" 
                                           name="password" 
                                           class="form-control form-input" 
                                           placeholder="Enter Password" 
                                           required>
                                    <div class="form-text mt-2">
                                        <i class="bi bi-info-circle"></i> This password will be used for exam login.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-shield-lock-fill"></i> Exam Pin <span class="text-muted small">(Optional)</span>
                                    </label>
                                    <input type="text" 
                                           name="exam_pin" 
                                           class="form-control form-input" 
                                           placeholder="Enter Exam Pin (if available)">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-geo-alt-fill"></i> State
                                    </label>
                                    <input type="text" 
                                           name="state" 
                                           class="form-control form-input" 
                                           placeholder="e.g., Uttar Pradesh, Maharashtra, Delhi" 
                                           required>
                                </div>
                                
                                <button type="submit" name="submit_exam" class="btn submit-btn w-100 text-white" <?php echo ($balance < LL_EXAM_FEE) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-cloud-arrow-up-fill me-2"></i>
                                    <?php echo ($balance >= LL_EXAM_FEE) ? 'Submit & Pay ₹'.LL_EXAM_FEE : 'Insufficient Balance'; ?>
                                </button>
                            </form>

                            <!-- Features row -->
                            <div class="row mt-4 g-3 text-center">
                                <div class="col-4">
                                    <div class="feature-icon mx-auto mb-2"><i class="bi bi-shield-check text-primary fs-5"></i></div>
                                    <small class="text-muted">Secure & Verified</small>
                                </div>
                                <div class="col-4">
                                    <div class="feature-icon mx-auto mb-2"><i class="bi bi-hourglass-split text-warning fs-5"></i></div>
                                    <small class="text-muted">3-24 Hours Result</small>
                                </div>
                                <div class="col-4">
                                    <div class="feature-icon mx-auto mb-2"><i class="bi bi-headset text-success fs-5"></i></div>
                                    <small class="text-muted">24/7 Support</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Developer & Powered by Footer -->
                    <div class="credit-footer text-center">
                        <div class="d-flex flex-wrap justify-content-center gap-4 align-items-center">
                            <span><i class="bi bi-cpu"></i> Powered by <strong>goodprintportal.in</strong></span>
                            <span class="text-muted">|</span>
                            <span><i class="bi bi-code-slash"></i> Developed by <strong class="text-warning">Lala Web Developer</strong></span>
                        </div>
                        <div class="mt-2 small opacity-75">
                            <i class="bi bi-patch-check-fill text-success"></i> Official LL Exam Service
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
<?php include('userFooter.php'); ?>