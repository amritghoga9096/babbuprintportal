<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

@mysqli_report(MYSQLI_REPORT_OFF);

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `m_link_s2` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `epic_no` varchar(50) DEFAULT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `response` longtext DEFAULT NULL,
  `userid` varchar(50) DEFAULT NULL,
  `date` varchar(100) DEFAULT NULL,
  `status_code` varchar(20) DEFAULT NULL,
  `reference_id` varchar(255) DEFAULT NULL,
  `charge_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_mls2_user` (`userid`),
  KEY `idx_mls2_epic` (`epic_no`),
  KEY `idx_mls2_mobile` (`mobile_no`),
  KEY `idx_mls2_status` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@mysqli_query($ahk_conn, "ALTER TABLE `m_link_s2` ADD COLUMN IF NOT EXISTS `status_code` varchar(20) DEFAULT NULL AFTER `date`");
@mysqli_query($ahk_conn, "ALTER TABLE `m_link_s2` ADD COLUMN IF NOT EXISTS `reference_id` varchar(255) DEFAULT NULL AFTER `status_code`");
@mysqli_query($ahk_conn, "ALTER TABLE `m_link_s2` ADD COLUMN IF NOT EXISTS `charge_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `reference_id`");
@mysqli_query($ahk_conn, "ALTER TABLE `m_link_s2` ADD KEY IF NOT EXISTS `idx_mls2_user` (`userid`)");
@mysqli_query($ahk_conn, "ALTER TABLE `m_link_s2` ADD KEY IF NOT EXISTS `idx_mls2_epic` (`epic_no`)");
@mysqli_query($ahk_conn, "ALTER TABLE `m_link_s2` ADD KEY IF NOT EXISTS `idx_mls2_mobile` (`mobile_no`)");
@mysqli_query($ahk_conn, "ALTER TABLE `m_link_s2` ADD KEY IF NOT EXISTS `idx_mls2_status` (`status_code`)");

$prExists = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT id FROM pricing WHERE service_name='v_mobile_link_s2' LIMIT 1"));
if (!$prExists) {
    @mysqli_query($ahk_conn, "INSERT INTO pricing (service_name, price, description) VALUES ('v_mobile_link_s2', '40', 'Instant Number Link With Voter S2 Service Charge')");
}

function mask_api_key_in_url_s2($url) {
    if (!is_string($url) || $url === '') return $url;
    $u = $url;
    $u = preg_replace('/([?&]api_key=)[^&]*/i', '$1[REDACTED]', $u);
    $u = preg_replace('/([?&]apiKey=)[^&]*/i', '$1[REDACTED]', $u);
    $u = preg_replace('/([?&]key=)[^&]*/i', '$1[REDACTED]', $u);
    return $u;
}
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Instant Number Link With Voter S2</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="vote_mob_link_s2_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Voter Mobile Link S2 List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>
                .hidden { display: none; }
                .fee-container {
                    background-color: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    padding: 10px 15px;
                    max-width: 300px;
                    margin: 10px auto;
                }
                .fee-container h5 {
                    color: #ff9800;
                    font-size: 18px;
                    font-weight: 600;
                }
            </style>
        </head>

        <body>
            <?php
            $price = null;
            $priceChecks = array(
                "SELECT * FROM pricing WHERE service_name='v_mobile_link_s2' LIMIT 1",
                "SELECT * FROM pricing WHERE service='v_mobile_link_s2' LIMIT 1",
                "SELECT fees AS price FROM pricing WHERE service='v_mobile_link_s2' LIMIT 1",
                "SELECT fees AS price FROM pricing WHERE service_name='v_mobile_link_s2' LIMIT 1"
            );
            foreach ($priceChecks as $_pQ) {
                $pRes = @mysqli_query($ahk_conn, $_pQ);
                if ($pRes && ($pRow = mysqli_fetch_assoc($pRes))) {
                    $price = $pRow;
                    break;
                }
            }
            if (!$price) {
                $price = array('price' => 40, 'fees' => 40);
            }
            $fee = isset($price['price']) ? (float)$price['price'] : (isset($price['fees']) ? (float)$price['fees'] : 40);
            if ($fee <= 0) $fee = 40;
            $balance = $udata['balance'];
            $mobile = $udata['phone'];

            if (isset($_POST['submit_mobile']) && !empty($_POST['new_mobile']) && !empty($_POST['epic'])) {

                if ($balance < $fee) {
                    echo "<script>
                        Swal.fire({
                            title:'Balance Low! Recharge Now',
                            text:'Insufficient Balance',
                            icon:'warning',
                            confirmButtonText:'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location = 'wallet.php';
                            }
                        });
                    </script>";
                } else {
                    $newMobileNumber = $_POST['new_mobile'];
                    $epic = $_POST['epic'];

                    $pp_send_api_key = ''; $pp_key_source = 'NOT FOUND';
                    if (is_object($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
                        $_cx = $GLOBALS['ahk_conn'] ?? $ahk_conn;
                        $_r = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT pariprint_api_key FROM settings WHERE id=1 LIMIT 1"));
                        if (is_array($_r) && !empty($_r['pariprint_api_key']) && is_string($_r['pariprint_api_key'])) {
                            $pp_send_api_key = trim((string)$_r['pariprint_api_key']);
                            $pp_key_source = 'EXACT settings.pariprint_api_key';
                        }
                        if (strlen($pp_send_api_key) < 40 || substr($pp_send_api_key,0,4) !== 'phk_') {
                            $_r2 = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
                            if (is_array($_r2)) {
                                foreach ($_r2 as $_cc => $_vv) {
                                    if (!is_string($_vv) || strlen($_vv) < 40) continue;
                                    if (substr($_vv,0,4)==='phk_' || (strpos(strtolower($_cc),'pari')!==false && strpos(strtolower($_cc),'key')!==false)) {
                                        $pp_send_api_key = trim($_vv); $pp_key_source = 'fallback col: '.$_cc; break;
                                    }
                                }
                            }
                        }
                        if (strlen($pp_send_api_key) < 40) {
                            $_ex = (string)($GLOBALS['pariprint_api_key'] ?? $pariprint_api_key ?? '');
                            if (strlen($_ex) >= 40) { $pp_send_api_key = $_ex; $pp_key_source = 'existing global'; }
                        }
                    }
                    $GLOBALS['pariprint_api_key'] = $pp_send_api_key;
                    $pariprint_api_key_s2 = $pp_send_api_key;

                    $pp_ok = (strlen($pp_send_api_key) >= 40 && substr($pp_send_api_key,0,4) === 'phk_');
                    if (!$pp_ok) {
                        $eh  = '<div style="text-align:left;font-size:13px;line-height:1.6;">';
                        $eh .= '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;">';
                        $eh .= '<div style="font-weight:800;color:#991b1b;margin-bottom:6px;">&#128308; PARIPRINT KEY INVALID / EMPTY — STOPPED BEFORE HITTING PARIPRINT.</div>';
                        $eh .= '<div style="margin-top:4px;"><b>Fix:</b> Open → <a href="settings.php" target="_blank" style="color:#1d4ed8;text-decoration:underline;font-weight:700;">Admin → Settings → PariPrint API Key</a> field me <code>phk_XXXXXXXXXXXX... (44 chars)</code> paste karke SAVE karo.</div>';
                        $eh .= '</div><div style="margin-top:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;display:grid;grid-template-columns:max-content 1fr;row-gap:3px;column-gap:14px;">';
                        $eh .= '<div style="color:#6b7280;">Read Source:</div><div style="font-family:ui-monospace,Menlo,Consolas,monospace;">'.htmlspecialchars($pp_key_source).'</div>';
                        $eh .= '<div style="color:#6b7280;">Actual Value:</div><div style="font-family:ui-monospace,Menlo,Consolas,monospace;color:#'.(strlen($pp_send_api_key)>=4?'059669':'dc2626').';">'.strlen($pp_send_api_key).' chars / prefix='.(strlen($pp_send_api_key)>=4?htmlspecialchars(substr($pp_send_api_key,0,4)):'----').'.. / last4=..'.(strlen($pp_send_api_key)>=4?htmlspecialchars(substr($pp_send_api_key,-4)):'----').'</div>';
                        $eh .= '<div style="color:#6b7280;">Expected:</div><div style="font-family:ui-monospace,Menlo,Consolas,monospace;">phk_… (len 44)</div>';
                        $eh .= '</div></div>';
                        ?>
                        <script>$(function(){ Swal.fire({title:'PariPrint Key Missing / Invalid', html: <?php echo json_encode($eh); ?>, icon:'error', confirmButtonColor:'#dc2626', width: 680}); });</script>
                        <?php
                    } else {

                    $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : (defined('CENTRAL_API_BASE') ? rtrim(CENTRAL_API_BASE,'/') : 'https://pariprint.in'));
                    $apiUrl = $apiBase . "/api-proxy.php?" . http_build_query([
                        'slug'    => 'instant-voter-number-link-s2',
                        'epic'    => $epic,
                        'mobile'  => $newMobileNumber,
                        'apiKey'  => $pp_send_api_key
                    ]);

                    $safeApiUrl = mask_api_key_in_url_s2($apiUrl);

                    $ch = curl_init();
                    curl_setopt_array($ch, array(
                        CURLOPT_URL => $apiUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 120,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 0,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
                        CURLOPT_HTTPHEADER => array(
                            'Accept: application/json, text/plain, */*',
                            'Accept-Language: en-IN,en;q=0.9,hi;q=0.8',
                            'Cache-Control: no-cache',
                            'Pragma: no-cache',
                            'Referer: https://pariprint.in/'
                        ),
                    ));

                    $response  = curl_exec($ch);
                    $curlError = curl_error($ch);
                    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($response === false || $response === '' || $response === null) {
                        $ctx = stream_context_create(array(
                            'http' => array(
                                'method'  => 'GET',
                                'header'  => "Accept: application/json\r\nAccept-Language: en-IN,en;q=0.9,hi;q=0.8\r\nCache-Control: no-cache\r\nPragma: no-cache\r\nReferer: https://pariprint.in/\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36\r\n",
                                'timeout' => 120,
                                'ignore_errors' => true
                            ),
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        ));
                        $fallbackResponse = @file_get_contents($apiUrl, false, $ctx);
                        if ($fallbackResponse !== false && $fallbackResponse !== '' && $fallbackResponse !== null) {
                            $response = $fallbackResponse;
                            if ((int)$httpCode === 0) $httpCode = 200;
                        }
                        unset($fallbackResponse);
                    }

                    $rawForLog = is_string($response) ? $response : '';

                    if ($response === false || $response === '' || $response === null) {
                        $rawShort = $rawForLog !== '' ? $rawForLog : 'cURL: ' . $curlError;
                        if (strlen($rawShort) > 400) $rawShort = substr($rawShort, 0, 400) . '... [truncated]';
                        $failDebug = "HTTP:$httpCode | cURL Err: $curlError | URL: $safeApiUrl | Raw: $rawShort";
                        echo '<div class="alert alert-danger" role="alert"><b>API Connection Failed</b><br>' . htmlspecialchars($failDebug) . '</div>';

                        date_default_timezone_set("Asia/Kolkata");
                        $timestamp = date('d/m/Y g:i:s');
                        $safeFailMsg = mysqli_real_escape_string($ahk_conn, "Link Failed. EPIC: $epic, Mobile: $newMobileNumber. Reason: PariPrint API unreachable. $failDebug");
                        mysqli_query($ahk_conn, "INSERT INTO m_link_s2(epic_no, mobile_no, response, userid, date, status_code) VALUES ('$epic','$newMobileNumber','$safeFailMsg','$mobile','$timestamp','API_ERROR')");

                        $jsDebug = json_encode("Link Failed.\n\nEPIC: $epic\nMobile: $newMobileNumber\n\nReason: No response from API.\n\nDebug Info:\nHTTP Code: $httpCode\ncURL Error: " . ($curlError !== '' ? $curlError : '(none)') . "\n\nURL: $safeApiUrl\n\nRaw: $rawShort", JSON_UNESCAPED_UNICODE);
                        echo "<script>
                            Swal.fire({
                                title:'Error',
                                text: $jsDebug,
                                icon:'error',
                                confirmButtonText:'OK'
                            }).then(function(r){ if(r.isConfirmed) window.location='vote_mob_link_s2_list'; });
                        </script>";
                    } else {
                        $data = json_decode($response, true);
                        $jsonError = json_last_error_msg();

                        $status = isset($data['status']) ? $data['status'] : (isset($data['success']) ? $data['success'] : false);
                        $statusMessage = isset($data['statusMessage']) ? $data['statusMessage'] : (isset($data['status_message']) ? $data['status_message'] : '');
                        $statusCode = isset($data['status_code']) ? $data['status_code'] : (isset($data['statusCode']) ? $data['statusCode'] : null);
                        if ($statusCode === null || $statusCode === '') {
                            if (is_string($status) && (ctype_digit($status) || is_numeric($status))) {
                                $statusCode = $status;
                            } elseif (is_int($status) || is_numeric($status)) {
                                $statusCode = (string)$status;
                            }
                        }
                        $statusData   = (isset($data['data'])   && is_array($data['data'])   && isset($data['data']['status']))   ? $data['data']['status']   : null;
                        $statusResult = (isset($data['result']) && is_array($data['result']) && isset($data['result']['status'])) ? $data['result']['status'] : null;
                        $statusCodeData = (isset($data['data']) && is_array($data['data']) && isset($data['data']['status_code'])) ? $data['data']['status_code'] : null;
                        if ($statusCodeData === null && isset($data['data']) && is_array($data['data']) && isset($data['data']['status'])) {
                            if (is_string($data['data']['status']) && ctype_digit($data['data']['status'])) $statusCodeData = $data['data']['status'];
                        }
                        $statusNested = $statusData !== null ? $statusData : $statusResult;
                        $message = isset($data['message']) ? $data['message'] : '';
                        $linkStatus = isset($data['link_status']) ? $data['link_status'] : '';
                        $resultValue = isset($data['result_value']) ? $data['result_value'] : '';
                        $chargeAmount = isset($data['charge_amount']) ? (float) $data['charge_amount'] : 0;

                        $refId      = isset($data['refId'])       ? $data['refId']       : (isset($data['reference_id']) ? $data['reference_id'] : (isset($data['ref_id']) ? $data['ref_id'] : ''));
                        $formNumber = isset($data['form_number']) ? $data['form_number'] : (isset($data['formNo']) ? $data['formNo'] : (isset($data['application_no']) ? $data['application_no'] : ''));
                        $orderId    = isset($data['order_id'])    ? $data['order_id']    : (isset($data['orderId']) ? $data['orderId'] : (isset($data['requestId']) ? $data['requestId'] : (isset($data['request_id']) ? $data['request_id'] : '')));
                        $downloadUrl= isset($data['download_url'])? $data['download_url']:
                                      (isset($data['download'])     ? $data['download']     :
                                      (isset($data['download_link'])? $data['download_link']:
                                      (isset($data['pdf_url'])      ? $data['pdf_url']      :
                                      (isset($data['file_url'])     ? $data['file_url']     :
                                      (isset($data['result_url'])   ? $data['result_url']   : '')))));
                        if ($downloadUrl === '' && isset($data['result']) && is_string($data['result']) && preg_match('#^https?://#i', $data['result'])) {
                            $downloadUrl = $data['result'];
                        }

                        $apiMessage = trim($linkStatus) !== '' ? $linkStatus : (trim($resultValue) !== '' ? $resultValue : $message);
                        if ($apiMessage === '' && trim($statusMessage) !== '') $apiMessage = trim($statusMessage);
                        if ($apiMessage === '') {
                            if (is_array($data) && isset($data['data']) && is_array($data['data']) && isset($data['data']['message'])) {
                                $apiMessage = trim((string)$data['data']['message']);
                            } elseif (is_array($data) && isset($data['result']) && is_array($data['result']) && isset($data['result']['message'])) {
                                $apiMessage = trim((string)$data['result']['message']);
                            }
                        }
                        if ($apiMessage === '' && $jsonError !== 'No error') {
                            $rawShort = strlen($rawForLog) > 400 ? substr($rawForLog, 0, 400) . '... [truncated]' : $rawForLog;
                            $apiMessage = "Invalid JSON ($jsonError). HTTP: $httpCode. Raw: $rawShort";
                        }
                        if ($apiMessage === '') {
                            $apiMessage = 'No response from API.';
                        }

                        $_statusLoose = null;
                        if (is_string($status) && ctype_digit($status)) $_statusLoose = (int)$status;
                        elseif (is_numeric($status)) $_statusLoose = (int)$status;
                        $_statusCodeLoose = null;
                        if ($statusCode !== null && is_string($statusCode) && ctype_digit($statusCode)) $_statusCodeLoose = (int)$statusCode;
                        elseif (is_numeric($statusCode)) $_statusCodeLoose = (int)$statusCode;
                        $_statusCodeDataLoose = null;
                        if ($statusCodeData !== null && is_string($statusCodeData) && ctype_digit($statusCodeData)) $_statusCodeDataLoose = (int)$statusCodeData;
                        elseif (is_numeric($statusCodeData)) $_statusCodeDataLoose = (int)$statusCodeData;

                        $_statusMsgLower = strtolower(trim((string)$statusMessage));

                        $isSuccess = (
                            $status === true ||
                            $status === 1 ||
                            $status === '1' ||
                            $status === 'true' ||
                            $status === '100' ||
                            (is_numeric($status) && (int)$status === 100) ||
                            $_statusLoose === 100 ||
                            $statusCode === 100 ||
                            $statusCode === '100' ||
                            $_statusCodeLoose === 100 ||
                            $statusCodeData === 100 ||
                            $statusCodeData === '100' ||
                            $_statusCodeDataLoose === 100 ||
                            $statusNested === true ||
                            $statusNested === 1 ||
                            $statusNested === '1' ||
                            $statusNested === '100' ||
                            $_statusMsgLower === 'success' ||
                            $_statusMsgLower === 'successful' ||
                            $_statusMsgLower === 'completed' ||
                            $_statusMsgLower === 'done' ||
                            $_statusMsgLower === 'linked'
                        );

                        if (
                            $statusCode === 200 || $statusCode === '200' || $_statusCodeLoose === 200 ||
                            $statusCodeData === 200 || $statusCodeData === '200' || $_statusCodeDataLoose === 200 ||
                            (is_string($status) && $status === '200') ||
                            (is_numeric($status) && (int)$status === 200) ||
                            $_statusLoose === 200
                        ) {
                            $isSuccess = false;
                        }

                        $displayStatusCode = $statusCode !== null && $statusCode !== '' ? (string)$statusCode : (is_string($status) && $status !== '' ? $status : '');

                        $extraInfo = '';
                        if ($refId !== '')      $extraInfo .= " | refId:$refId";
                        if ($formNumber !== '') $extraInfo .= " | FORM_NUMBER:$formNumber";
                        if ($orderId !== '')    $extraInfo .= " | order_id:$orderId";
                        if ($downloadUrl !== '')$extraInfo .= " | download:$downloadUrl";
                        if ($displayStatusCode !== '') $extraInfo .= " | status_code:$displayStatusCode";

                        if ($isSuccess) {
                            $displayMessage = "Voter Mobile Instant Link S2 Successful!\n\nEPIC: $epic\nMobile: $newMobileNumber\n\n" . $apiMessage . ($extraInfo !== '' ? "\n\nExtra Info:\n" . trim(str_replace(' | ', "\n", ltrim($extraInfo, ' |'))) : '');
                            $saveMessage    = "SUCCESS | EPIC: $epic | Mobile: $newMobileNumber | Fee: $fee | Message: " . rtrim($apiMessage, '. ') . " | Result:" . ltrim($extraInfo, ' |');
                            $saveStatusCode = $displayStatusCode !== '' ? $displayStatusCode : ($status === true ? '100' : 'SUCCESS');
                        } else {
                            $rawShort = strlen($rawForLog) > 400 ? substr($rawForLog, 0, 400) . '... [truncated]' : $rawForLog;
                            $displayMessage = "Link Failed.\n\nEPIC: $epic\nMobile: $newMobileNumber\n\nReason: " . $apiMessage . "\n\nHTTP: $httpCode\nURL: $safeApiUrl\nRaw: $rawShort";
                            $saveMessage    = "Link Failed. EPIC: $epic, Mobile: $newMobileNumber. Reason: " . rtrim($apiMessage, '. ') . ". HTTP:$httpCode | URL:$safeApiUrl | Raw:$rawShort";
                            $saveStatusCode = $displayStatusCode !== '' ? $displayStatusCode : 'FAILED';
                        }

                        $alertType = $isSuccess ? 'success' : 'error';
                        $jsMessage  = json_encode($displayMessage, JSON_UNESCAPED_UNICODE);

                        if ($isSuccess) {
                            $new_balance = $balance - $fee;

                            $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$mobile'");

                            if ($walletUpdated) {
                                date_default_timezone_set("Asia/Kolkata");
                                $timestamp = date('d/m/Y g:i:s');
                                $safeSaveMessage = mysqli_real_escape_string($ahk_conn, $saveMessage);
                                $safeRefId = mysqli_real_escape_string($ahk_conn, $refId);

                                mysqli_query($ahk_conn, "INSERT INTO m_link_s2(epic_no, mobile_no, response, userid, date, status_code, reference_id, charge_amount) VALUES ('$epic','$newMobileNumber','$safeSaveMessage','$mobile','$timestamp','$saveStatusCode','$safeRefId','$fee')");
                                mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$mobile','$fee','$new_balance','$epic Instant Number Link With Voter S2','1','Debit')");
                            } else {
                                $alertType = 'warning';
                                $fallbackMsg = "Number linked ho gaya, lekin wallet se balance deduct nahi ho paya.\n\nEPIC: $epic\nMobile: $newMobileNumber\n\nDebug: " . mysqli_error($ahk_conn);
                                $jsMessage = json_encode($fallbackMsg, JSON_UNESCAPED_UNICODE);
                            }
                        } else {
                            date_default_timezone_set("Asia/Kolkata");
                            $timestamp = date('d/m/Y g:i:s');
                            $safeSaveMessage = mysqli_real_escape_string($ahk_conn, $saveMessage);
                            $safeRefId = mysqli_real_escape_string($ahk_conn, $refId);
                            mysqli_query($ahk_conn, "INSERT INTO m_link_s2(epic_no, mobile_no, response, userid, date, status_code, reference_id, charge_amount) VALUES ('$epic','$newMobileNumber','$safeSaveMessage','$mobile','$timestamp','$saveStatusCode','$safeRefId','0')");
                        }

                        echo "<script>
                            (function(){
                                var voterLinkMessage = $jsMessage;
                                var dl = " . ($downloadUrl !== '' ? json_encode($downloadUrl) : 'null') . ";
                                var opts = {
                                    title: '" . ($alertType == 'success' ? 'Success' : ($alertType == 'warning' ? 'Warning' : 'Error')) . "',
                                    text: voterLinkMessage,
                                    icon: '$alertType',
                                    confirmButtonText: 'Go to List'
                                };
                                if (dl && '$alertType' === 'success') {
                                    opts.showDenyButton = true;
                                    opts.denyButtonText = 'Download File';
                                }
                                Swal.fire(opts).then(function(result){
                                    if (result.isDenied && dl) {
                                        window.open(dl, '_blank');
                                        return;
                                    }
                                    if (result.isConfirmed) {
                                        window.location = 'vote_mob_link_s2_list';
                                    }
                                });
                            })();
                        </script>";
                    }
                    }
                }
            } else {
                if ($balance < $fee) {
                    echo "<div class='alert alert-warning text-center m-3'>Insufficient Balance. <a href='wallet.php' class='alert-link'>Recharge Now</a>.</div>";
                }
            }
            ?>

            <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title text-dark text-center"><strong>Instant Number Link With Voter S2</strong></h3>

                                <div class="text-center mt-2 mb-3">
                                    <div style="background:#e7f7ff;padding:8px;border-radius:6px;">
                                        <small style="font-weight:600;">
                                            ⚡ Powered by <span style="color:#0d6efd;">goodprintportal.in</span> &nbsp;|&nbsp; S2 Server
                                        </small>
                                    </div>
                                </div>

                            </div>
                            <div class="card-body">
                                <a href="voter_pdf" class="btn btn-dark w-100 mb-3">Voter Original PDF Download Here</a>

                                <form method="POST">
                                    <div class="form-group mb-3">
                                        <label for="epic">Enter EPIC Number <span class="text-danger">*</span></label>
                                        <input type="text" name="epic" id="epic" class="form-control border-success" placeholder="EPIC Number (Ex: ACZ0858391)" required>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="new_mobile">Enter New Mobile Number <span class="text-danger">*</span></label>
                                        <input type="text" name="new_mobile" id="new_mobile" class="form-control border-success" placeholder="10-digit mobile number (Ex: 8143951393)" pattern="\d{10}" maxlength="10" minlength="10" title="Please enter exactly 10 digits" required />
                                    </div>

                                    <button type="submit" name="submit_mobile" class="btn btn-primary w-100">
                                        <i class="fa fa-check-circle"></i> Submit Request S2
                                    </button>

                                    <div class="text-center mt-3">
                                        <div class="fee-container text-center">
                                            <h5 class="text-warning">Application Fee: ₹<?php echo $fee; ?></h5>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
    </div>
</div>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

</body>
</html>
<?php
include('userFooter.php');
?>
