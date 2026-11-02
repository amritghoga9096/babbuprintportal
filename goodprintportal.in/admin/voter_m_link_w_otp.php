<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Utility: mask API key in URLs before showing to users
function mask_api_key_in_url($url) {
    if (!is_string($url) || $url === '') return $url;
    // Replace common api key query params with [REDACTED]
    $u = $url;
    $u = preg_replace('/([?&]api_key=)[^&]*/i', '$1[REDACTED]', $u);
    $u = preg_replace('/([?&]apiKey=)[^&]*/i', '$1[REDACTED]', $u);
    $u = preg_replace('/([?&]key=)[^&]*/i', '$1[REDACTED]', $u);
    return $u;
}
?>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Instant Number Link With Voter</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="vote_mob_link_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Voter Mobile Link List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <!-- End Breadcrumb -->

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>
                .hidden {
                    display: none;
                }
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
            // 1. Fetch Service Pricing
            $price = null;
            $priceChecks = array(
                "SELECT * FROM pricing WHERE service_name='v_mobile_link' LIMIT 1",
                "SELECT * FROM pricing WHERE service='v_mobile_link' LIMIT 1",
                "SELECT fees AS price FROM pricing WHERE service='v_mobile_link' LIMIT 1",
                "SELECT fees AS price FROM pricing WHERE service_name='v_mobile_link' LIMIT 1"
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

            // --- API CONFIGURATION (Dynamic) ---
            // Ensure these variables are defined in your config or header file
            // $auth_url   = "https://apiadda.in/api_menual/";
            // $api_key    = "YOUR_API_KEY";
            // $secret_key = "YOUR_SECRET_KEY";

            if (isset($_POST['submit_mobile']) && !empty($_POST['new_mobile']) && !empty($_POST['epic'])) {
                
                // Check Balance
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

                    // Construct Dynamic URL using http_build_query
                    $apiUrl = $pariprint_instant_voter_endpoint . "?" . http_build_query([
                        'slug'    => 'instant-number-link-with-voter',
                        'epic'    => $epic,
                        'mobile'  => $newMobileNumber,
                        'api_key' => $pariprint_api_key
                    ]);

                                        // Safe version for display: redact api key so it does not appear to end-users
                                        $safeApiUrl = mask_api_key_in_url($apiUrl);

                    $ch = curl_init();
                    curl_setopt_array($ch, array(
                        CURLOPT_URL => $apiUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 90,
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
                                'timeout' => 90,
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
                        mysqli_query($ahk_conn, "INSERT INTO m_link(epic_no, mobile_no, response, userid, date) VALUES ('$epic','$newMobileNumber','$safeFailMsg','$mobile','$timestamp')");

                        $jsDebug = json_encode("Link Failed.\n\nEPIC: $epic\nMobile: $newMobileNumber\n\nReason: No response from API.\n\nDebug Info:\nHTTP Code: $httpCode\ncURL Error: " . ($curlError !== '' ? $curlError : '(none)') . "\n\nURL: $safeApiUrl\n\nRaw: $rawShort", JSON_UNESCAPED_UNICODE);
                        echo "<script>
                            Swal.fire({
                                title:'Error',
                                text: $jsDebug,
                                icon:'error',
                                confirmButtonText:'OK'
                            }).then(function(r){ if(r.isConfirmed) window.location='vote_mob_link_list'; });
                        </script>";
                    } else {
                        $data = json_decode($response, true);
                        $jsonError = json_last_error_msg();
                        
                        // Response Parsing - handle api-proxy.php response structure
                        $status = isset($data['status']) ? $data['status'] : (isset($data['success']) ? $data['success'] : false);
                        $statusData   = (isset($data['data'])   && is_array($data['data'])   && isset($data['data']['status']))   ? $data['data']['status']   : null;
                        $statusResult = (isset($data['result']) && is_array($data['result']) && isset($data['result']['status'])) ? $data['result']['status'] : null;
                        $statusNested = $statusData !== null ? $statusData : $statusResult;
                        $message = isset($data['message']) ? $data['message'] : '';
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

                        // Priority: result_value > message
                        $apiMessage = trim($resultValue) !== '' ? $resultValue : $message;
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

                        $isSuccess = (
                            $status === true ||
                            $status === 1 ||
                            $status === '1' ||
                            $status === 'true' ||
                            $status === 'SUCCESS' ||
                            $status === 'success' ||
                            (is_string($status) && in_array(strtolower($status), array('ok','completed','pass','passed','hit','linked','active','done'), true)) ||
                            $statusNested === true ||
                            $statusNested === 1 ||
                            $statusNested === '1' ||
                            (is_string($statusNested) && in_array(strtolower($statusNested), array('success','ok','completed','pass','passed','hit','linked','active','done'), true))
                        );

                        if ($isSuccess && $apiMessage !== '' && stripos($apiMessage, 'already done') !== false) {
                            $isSuccess = true;
                        }

                        $extraInfo = '';
                        if ($refId !== '')      $extraInfo .= " | refId:$refId";
                        if ($formNumber !== '') $extraInfo .= " | FORM_NUMBER:$formNumber";
                        if ($orderId !== '')    $extraInfo .= " | order_id:$orderId";
                        if ($downloadUrl !== '')$extraInfo .= " | download:$downloadUrl";

                        // Final display message - EPIC + Mobile + status included
                        if ($isSuccess) {
                            $displayMessage = "Voter Mobile Instant Link Successful!\n\nEPIC: $epic\nMobile: $newMobileNumber\n\n" . $apiMessage . ($extraInfo !== '' ? "\n\nExtra Info:\n" . trim(str_replace(' | ', "\n", ltrim($extraInfo, ' |'))) : '');
                            $saveMessage    = "SUCCESS | EPIC: $epic | Mobile: $newMobileNumber | Fee: $fee | Message: " . rtrim($apiMessage, '. ') . " | Result:" . ltrim($extraInfo, ' |');
                        } else {
                            $rawShort = strlen($rawForLog) > 400 ? substr($rawForLog, 0, 400) . '... [truncated]' : $rawForLog;
                            $displayMessage = "Link Failed.\n\nEPIC: $epic\nMobile: $newMobileNumber\n\nReason: " . $apiMessage . "\n\nHTTP: $httpCode\nURL: $safeApiUrl\nRaw: $rawShort";
                            $saveMessage    = "Link Failed. EPIC: $epic, Mobile: $newMobileNumber. Reason: " . rtrim($apiMessage, '. ') . ". HTTP:$httpCode | URL:$safeApiUrl | Raw:$rawShort";
                        }

                        $alertType = $isSuccess ? 'success' : 'error';
                        $jsMessage  = json_encode($displayMessage, JSON_UNESCAPED_UNICODE);
                        
                        // If successful, deduct balance and log history
                        if ($isSuccess) {
                            $new_balance = $balance - $fee;
                            
                            // Update User Balance
                            $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$mobile'");
                            
                            if ($walletUpdated) {
                                // Log History
                                date_default_timezone_set("Asia/Kolkata");
                                $timestamp = date('d/m/Y g:i:s');
                                $safeSaveMessage = mysqli_real_escape_string($ahk_conn, $saveMessage);
                            
                                mysqli_query($ahk_conn, "INSERT INTO m_link(epic_no, mobile_no, response, userid, date) VALUES ('$epic','$newMobileNumber','$safeSaveMessage','$mobile','$timestamp')");
                                mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$mobile','$fee','$new_balance','$epic Instant Number Link With Voter','1','Debit')");
                            } else {
                                $alertType = 'warning';
                                $fallbackMsg = "Number linked ho gaya, lekin wallet se balance deduct nahi ho paya.\n\nEPIC: $epic\nMobile: $newMobileNumber\n\nDebug: " . mysqli_error($ahk_conn);
                                $jsMessage = json_encode($fallbackMsg, JSON_UNESCAPED_UNICODE);
                            }
                        } else {
                            // Even on failure, log the request with error for history
                            date_default_timezone_set("Asia/Kolkata");
                            $timestamp = date('d/m/Y g:i:s');
                            $safeSaveMessage = mysqli_real_escape_string($ahk_conn, $saveMessage);
                            mysqli_query($ahk_conn, "INSERT INTO m_link(epic_no, mobile_no, response, userid, date) VALUES ('$epic','$newMobileNumber','$safeSaveMessage','$mobile','$timestamp')");
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
                                        window.location = 'vote_mob_link_list';
                                    }
                                });
                            })();
                        </script>";
                    }
                }
            } else {
                // If not submitted, display low balance warning immediately if applicable
                if ($balance < $fee) {
                    echo "<div class='alert alert-warning text-center m-3'>Insufficient Balance. <a href='wallet.php' class='alert-link'>Recharge Now</a>.</div>";
                }
            }
            ?>

            <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <!-- Left Side: Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title text-dark text-center"><strong>Instant Number Link With Voter</strong></h3>
                                
                                <!-- Powered By Section -->
                                <div class="text-center mt-2 mb-3">
                                    <div style="background:#f8f9fa;padding:8px;border-radius:6px;">
                                        <small style="font-weight:600;">
                                            ⚡ Powered by <span style="color:#0d6efd;">goodprintportal.in</span>
                                        </small>
                                    </div>
                                </div>

                            </div>
                            <div class="card-body">
                                <a href="voter_pdf" class="btn btn-dark w-100 mb-3">Voter Original PDF Download Here</a>

                                <form method="POST">
                                    <div class="form-group mb-3">
                                        <label for="epic">Enter EPIC Number <span class="text-danger">*</span></label>
                                        <input type="text" name="epic" id="epic" class="form-control border-success" placeholder="EPIC Number" required>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="new_mobile">Enter New Mobile Number <span class="text-danger">*</span></label>
                                        <input type="text" name="new_mobile" id="new_mobile" class="form-control border-success" placeholder="10-digit mobile number" pattern="\d{10}" maxlength="10" minlength="10" title="Please enter exactly 10 digits" required />
                                    </div>

                                    <button type="submit" name="submit_mobile" class="btn btn-primary w-100">
                                        <i class="fa fa-check-circle"></i> Submit Request
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
