<?php include('header.php'); ?>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Pdf Download ORG</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <!-- End Breadcrumb -->

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
                    width: 100%;
                    margin-top: 10px;
                    margin-bottom: 10px;
                }
                .fee-container h5 {
                    color: #ff9800;
                    font-size: 18px;
                    font-weight: 600;
                    margin: 0;
                }
            </style>
        </head>

        <body>
            <?php
            // --- DYNAMIC API CONFIGURATION ---
            // tumne bataya $auth_url = "https://apiadda.in/" hai.
            // Isliye niche hum manually folder name add kar rahe hain.
            // $api_key    = "...";
            // $secret_key = "...";

            // Initialize variables
            $epicNumber = "";
            $stateCd = "";
            $voterName = "";
            $pdf = "";
            $msg = "";

            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                
                // Step 1: Send OTP
                if (isset($_POST['find']) && !empty($_POST['epic_no'])) {
                    $epic_no = trim($_POST['epic_no']);
                    
                    // URL Construction (Fixed for $auth_url = https://apiadda.in/)
                    $url = $auth_url . "VOTER_PDF/send_otp.php?" . http_build_query([
                        'api_key'    => $api_key,
                        'secret_key' => $secret_key,
                        'epic'       => $epic_no
                    ]);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $response = curl_exec($ch);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($response === false || !empty($curlError)) {
                        echo "<script>Swal.fire({ title: 'Error', text: 'Connection Failed: " . addslashes($curlError) . "', icon: 'error', confirmButtonText: 'OK' });</script>";
                    } else {
                        $result = json_decode($response, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            echo "<script>Swal.fire({ title: 'Error', text: 'Invalid JSON response from API.', icon: 'error', confirmButtonText: 'OK' });</script>";
                        } else {
                            $statuss = $result['status'] ?? 'unknown';

                            // Logic requested by user to handle Name and Message
                            $messages = (!empty($result['otp_message'])) ? $result['otp_message'] : 'Data Fetch Successful';
                            $voterName = (!empty($result['full_name'])) ? $result['full_name'] : 'Voter';

                            if ($statuss === "success") {
                                $epicNumber = $epic_no;
                                // Important: Capture state_cd and trim it to avoid format errors
                                $stateCd = isset($result['state_cd']) ? trim($result['state_cd']) : ''; 
                                
                                if(empty($stateCd)) {
                                     echo "<script>Swal.fire({ title: 'API Warning', text: 'State Code missing. Please try again.', icon: 'warning', confirmButtonText: 'OK' });</script>";
                                } else {
                                     echo "<script>Swal.fire({ title: 'Success', text: '$messages', icon: 'success', confirmButtonText: 'OK' });</script>";
                                }
                            } else {
                                $errorMsg = (!empty($result['message'])) ? $result['message'] : 'Unknown Error';
                                echo "<script>Swal.fire({ title: 'Failed', text: '$errorMsg', icon: 'error', confirmButtonText: 'OK' });</script>";
                            }
                        }
                    }
                }
            }

            // Pricing and Balance Logic
            $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='voter_pdf_instant' "));
            $fee = $price['price'];
            $balance = $udata['balance'];
            $mobile = $udata['phone'];

            if ($balance > $fee) {
                // Step 2: Verify OTP & Download
                if (isset($_POST['verify_otp']) && !empty($_POST['otp'])) {
                    $epic = trim($_POST['epic']);
                    $otp = trim($_POST['otp']);
                    $stateCd_post = trim($_POST['state_cd']); 

                    // URL Construction (Fixed for $auth_url = https://apiadda.in/)
                    $apiUrl = $auth_url . "VOTER_PDF/verify_otp.php?" . http_build_query([
                        'api_key'    => $api_key,
                        'secret_key' => $secret_key,
                        'stateCd'    => $stateCd_post,
                        'epic'       => $epic,
                        'otp'        => $otp
                    ]);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $apiResponse = curl_exec($ch);
                    
                    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    curl_close($ch);

                    if ($apiResponse === false) {
                        echo '<div class="alert alert-danger" role="alert">cURL request failed.</div>';
                    } else {
                        // Decode JSON to check for errors
                        $data = json_decode($apiResponse, true);

                        // If valid JSON error response
                        if ($data && isset($data['status']) && $data['status'] == 'error') {
                            $message = $data['message'] ?? 'Verification failed';
                            echo "<script>Swal.fire({ title: 'API Error', text: '$message', icon: 'error', confirmButtonText: 'OK' });</script>";
                        } 
                        // If PDF Binary (Success)
                        elseif (strpos($content_type, 'application/pdf') !== false || substr($apiResponse, 0, 4) === '%PDF') {
                            
                            // Deduct Balance
                            $new_balance = $balance - $fee;
                            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - '$fee' WHERE phone='$mobile'");
                            
                            date_default_timezone_set("Asia/Kolkata");
                            $timestamp = date('d/m/Y g:i:s');
                            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$mobile','$fee','$new_balance','$epic Voter ORG PDF','1','Debit')");

                            $pdf = base64_encode($apiResponse); 
                            $refId = $epic; 
                            
                            echo "<script>Swal.fire({ title: 'Success', text: 'PDF Downloaded Successfully!', icon: 'success', confirmButtonText: 'OK' });</script>";
                            $epicNumber = 0; 
                        } else {
                            // Fallback for weird responses
                            echo "<script>Swal.fire({ title: 'Unexpected Response', text: 'Could not process server response.', icon: 'error', confirmButtonText: 'OK' });</script>";
                        }
                    }
                }
            } else {
                $msg = "<script>Swal.fire({ title:'Balance Low! Recharge Now', text:'Warning!', icon:'warning', confirmButtonText:'OK' }).then((result) => { if (result.isConfirmed) { window.location = 'wallet.php'; } });</script>";
            }
            ?>

            <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <!-- Left Side: Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                               <h3 class="card-title text-dark text-center"><strong>Voter Original PDF Download</strong></h3>
                               
                               <!-- Powered By Section Added Here -->
                               <div class="text-center mt-2 mb-3">
                                    <div style="background:#f8f9fa;padding:8px;border-radius:6px;">
                                        <small style="font-weight:600;">
                                            ⚡ Powered by <span style="color:#0d6efd;">APIADDA.IN</span>
                                        </small>
                                    </div>
                               </div>

                            </div>
                            <div class="card-body">
                                <a href="vote_mob_link" class="btn btn-dark w-100 mb-3">Mobile Number Change Click Here</a>

                                <h6><?php echo $msg ?? ''; ?></h6>

                                <!-- Step 1 Form -->
                                <form id="epic_form" class="user <?php echo ($epicNumber) ? 'hidden' : ''; ?>" method="POST">
                                    <div class="form-group mb-3">
                                        <label for="epic_no">EPIC NO <span class="text-danger">*</span></label>
                                        <input id="epic_no" name="epic_no" class="form-control border-success" type="text" value="<?php echo $epicNumber; ?>" required <?php echo ($epicNumber) ? 'disabled' : ''; ?>>
                                    </div>
                                    <button type="submit" name="find" class="btn btn-primary w-100 mb-3" <?php echo ($epicNumber) ? 'disabled' : ''; ?>>
                                        <i class="fa fa-check-circle"></i> Send OTP
                                    </button>
                                    <div class="col-12 ml-2 d-flex justify-content-center">
                                        <div class="fee-container text-center">
                                            <h5 class="text-warning">Application Fee: 
                                               <?php echo $fee; ?>
                                            </h5>
                                        </div>
                                    </div>
                                </form>

                                <!-- Step 2 Form -->
                                <form id="additional_fields_form" class="user <?php echo ($epicNumber) ? '' : 'hidden'; ?>" method="POST">
                                    <input type="hidden" name="epic" value="<?php echo $epicNumber; ?>">
                                    <input type="hidden" name="state_cd" value="<?php echo $stateCd; ?>">
                                    
                                    <?php if($voterName) { ?>
                                    <div class="alert alert-info py-2">
                                        <small>Voter Name: <strong><?php echo $voterName; ?></strong></small>
                                    </div>
                                    <?php } ?>

                                    <div class="form-group mb-3">
                                        <label for="otp">Enter OTP <span class="text-danger">*</span></label>
                                        <input id="otp" name="otp" class="form-control border-primary" type="number" placeholder="ENTER OTP" required>
                                    </div>
                                    <button type="submit" name="verify_otp" class="btn btn-primary w-100">
                                        <i class="fa fa-check-circle"></i> Verify & Download
                                    </button>
                                </form>

                                <?php if (!empty($pdf)) { ?>
                                    <a href="data:application/pdf;base64,<?php echo $pdf; ?>" download="<?php echo $refId; ?>.pdf" class="btn btn-danger w-100 mt-4">
                                        <img src="v_org.png" alt="PDF" style="height:40px; vertical-align: middle; margin-right: 5px;">
                                        <strong>Download PDF</strong>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: PDF Preview -->
                    <?php if (!empty($pdf)) { ?>
                    <div class="col-lg-7">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Preview Voter PDF</h5>
                            </div>
                            <div class="card-body p-0" style="height: 750px;">
                                <iframe src="data:application/pdf;base64,<?php echo $pdf; ?>" width="100%" height="100%" frameborder="0"></iframe>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
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
<script>
    setTimeout(function () {
        var successAlert = document.getElementById('success-alert');
        if(successAlert) successAlert.style.display = 'none';
    }, 3000);
</script>

</body>
</html>
<?php
include('footer.php');
?>