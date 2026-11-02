<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Start session for OTP flow (Check if already started to avoid warnings)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cancel action: clear session and redirect to clean URL
if (isset($_GET['cancel']) && $_GET['cancel'] == 1) {
    unset($_SESSION['voter_link']);
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Mobile Link – One Click API</li>
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
                .hidden { display: none; }
                .otp-section {
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                    border-radius: 16px;
                    padding: 25px;
                    margin-top: 20px;
                    border: 1px solid rgba(0,0,0,0.05);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                }
                .otp-input {
                    text-align: center;
                    letter-spacing: 8px;
                    font-size: 28px;
                    font-weight: bold;
                    font-family: monospace;
                }
                .fee-container {
                    background-color: #fff3e0;
                    border-radius: 12px;
                    padding: 10px 20px;
                    max-width: 280px;
                    margin: 15px auto 0;
                    border: 1px solid #ffe0b3;
                }
                .fee-container h5 {
                    color: #ff9800;
                    font-size: 18px;
                    font-weight: 700;
                    margin: 0;
                }
                .powered-by {
                    background: linear-gradient(135deg, #0d6efd10 0%, #0d6efd20 100%);
                    padding: 10px;
                    border-radius: 40px;
                    font-weight: 600;
                    display: inline-block;
                    width: auto;
                    margin: 0 auto;
                }
                .promo-banner {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 12px;
                    padding: 15px;
                    color: white;
                    margin-top: 20px;
                }
                .card {
                    border: none;
                    border-radius: 20px;
                    transition: transform 0.2s;
                }
                .card:hover {
                    transform: translateY(-5px);
                }
                .btn-primary {
                    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
                    border: none;
                    transition: all 0.3s;
                }
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(13,110,253,0.3);
                }
                .btn-success {
                    background: linear-gradient(135deg, #198754, #157347);
                    border: none;
                }
                .form-control:focus {
                    border-color: #0d6efd;
                    box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25);
                }
            </style>
        </head>

        <body>
            <?php
            // --- DYNAMIC API CONFIGURATION ---
            // Ensure these variables are defined in your config or header file
            // $auth_url   = "https://apiadda.in/"; 
            // $api_key    = "...";
            // $secret_key = "...";

            // Pricing and Balance Logic
            $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='v_mobile_link' "));
            $fee = $price['price'];
            $balance = $udata['balance'];
            $mobile = $udata['phone'];

            // Helper function for API errors
            function showApiError($title, $message, $rawResponse = null) {
                $errorMsg = $message;
                if ($rawResponse && strpos($rawResponse, '{') !== false) {
                    $errorMsg .= "<br><small class='text-muted'>API Response: " . htmlspecialchars(substr($rawResponse, 0, 500)) . "</small>";
                }
                echo "<script>
                    Swal.fire({
                        title: '" . addslashes($title) . "',
                        html: '" . addslashes($errorMsg) . "',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                </script>";
            }

            // Initialize session variable if not set
            if (!isset($_SESSION['voter_link'])) {
                $_SESSION['voter_link'] = [];
            }

            // Handle Initiate Request
            if (isset($_POST['submit_mobile']) && !empty($_POST['epic']) && !empty($_POST['new_mobile']) && !empty($_POST['aadhaar_name']) && !empty($_POST['aadhar'])) {
                // Check balance again
                if ($balance <= $fee) {
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
                    exit;
                }

                // Get inputs (Do NOT urlencode here, http_build_query handles it)
                $epic = $_POST['epic'];
                $newMobile = $_POST['new_mobile'];
                $aadhaar_name = $_POST['aadhaar_name'];
                $aadhar = $_POST['aadhar'];

                // Step 1: Initiate API
                // Construct URL dynamically
                $initiateUrl = $auth_url . "api_service_v5/epic_mob_link.php?" . http_build_query([
                    'action'       => 'initiate',
                    'api_key'      => $api_key,
                    'secret_key'   => $secret_key,
                    'epic'         => $epic,
                    'mobile'       => $newMobile,
                    'aadhar'       => $aadhar,
                    'aadhaar_name' => $aadhaar_name
                ]);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $initiateUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response === false || $httpCode != 200) {
                    showApiError("Connection Error", "cURL failed: " . $curlError, $response);
                } else {
                    $data = json_decode($response, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        showApiError("Invalid API Response", "Could not parse response", $response);
                    } elseif (isset($data['status_code']) && $data['status_code'] == 100 && $data['status'] == 'initiated') {
                        // Store details in session for OTP verification
                        $_SESSION['voter_link'] = [
                            'uniq_apiadda_id' => $data['uniq_apiadda_id'],
                            'reference_id' => $data['reference_id'],
                            'epic' => $_POST['epic'],
                            'mobile' => $_POST['new_mobile'],
                            'aadhar' => $_POST['aadhar'],
                            'aadhaar_name' => $_POST['aadhaar_name'],
                            'message' => $data['message']
                        ];
                        echo "<script>
                            Swal.fire({
                                title: 'OTP Sent!',
                                text: '{$data['message']}',
                                icon: 'info',
                                confirmButtonText: 'Proceed to Verify'
                            }).then(() => {
                                window.location = window.location.href;
                            });
                        </script>";
                    } else {
                        $errorMsg = isset($data['message']) ? $data['message'] : 'Unknown error';
                        if (isset($data['field'])) {
                            $errorMsg .= " (Field: " . $data['field'] . ")";
                        }
                        showApiError("Initiate Failed", $errorMsg, $response);
                    }
                }
            }

            // Handle OTP Verification
            if (isset($_POST['verify_otp']) && !empty($_POST['otp'])) {
                if (empty($_SESSION['voter_link']['uniq_apiadda_id'])) {
                    echo "<script>
                        Swal.fire({
                            title: 'Session Expired',
                            text: 'Please start the process again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location = window.location.href;
                        });
                    </script>";
                    exit;
                }

                $uniq_id = $_SESSION['voter_link']['uniq_apiadda_id'];
                $otp = $_POST['otp'];

                // Step 2: Verify API
                // Construct URL dynamically
                $verifyUrl = $auth_url . "api_service_v5/epic_mob_link.php?" . http_build_query([
                    'action'          => 'verify',
                    'api_key'         => $api_key,
                    'secret_key'      => $secret_key,
                    'uniq_apiadda_id' => $uniq_id,
                    'otp'             => $otp
                ]);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $verifyUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response === false || $httpCode != 200) {
                    showApiError("Verification Error", "cURL failed: " . $curlError, $response);
                } else {
                    $data = json_decode($response, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        showApiError("Invalid API Response", "Could not parse verification response", $response);
                    } elseif (isset($data['status_code']) && $data['status_code'] == 1000 && $data['status'] == 'success') {
                        // Success! Deduct balance and log
                        $new_balance = $balance - $fee;
                        mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$mobile'");

                        date_default_timezone_set("Asia/Kolkata");
                        $timestamp = date('d/m/Y g:i:s');

                        $epic = mysqli_real_escape_string($ahk_conn, $_SESSION['voter_link']['epic']);
                        $mobile_number = mysqli_real_escape_string($ahk_conn, $_SESSION['voter_link']['mobile']);
                        $final_message = mysqli_real_escape_string($ahk_conn, $data['message'] . " " . ($data['note'] ?? ''));

                        // Insert into m_link
                        mysqli_query($ahk_conn, "INSERT INTO m_link(epic_no, mobile_no, response, userid, date) VALUES ('$epic','$mobile_number','$final_message','$mobile','$timestamp')");
                        mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$mobile','$fee','$new_balance','$epic Voter Mobile Link Without OTP','1','Debit')");

                        // Clear session
                        unset($_SESSION['voter_link']);

                        echo "<script>
                            Swal.fire({
                                title: 'Success!',
                                text: '{$data['message']} {$data['note']}',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location = 'Apiadda_Voter_Mob';
                                }
                            });
                        </script>";
                    } else {
                        $errorMsg = isset($data['message']) ? $data['message'] : 'Invalid OTP or verification failed';
                        showApiError("Verification Failed", $errorMsg, $response);
                    }
                }
            }

            // Check if we are in OTP stage
            $showOtpForm = !empty($_SESSION['voter_link']['uniq_apiadda_id']);
            ?>

            <div class="container-fluid pt-4 px-4">
                <div class="row justify-content-center">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-lg border-0 rounded-4">
                            <div class="card-body p-4">
                                <h3 class="card-title text-dark text-center fw-bold mb-2">Voter Mobile Link – One Click API</h3>
                                <p class="text-center text-success fw-semibold">🚀 Fast | Easy | Instant</p>
                                
                                <!-- Promo Banner -->
                                <div class="promo-banner text-center mb-4">
                                    <i class="bi bi-megaphone fs-4"></i>
                                    <strong>📢 You can enter any Aadhaar number, but OTP will go to the mobile number linked with that Aadhaar ✅</strong>
                                </div>

                                <!-- Powered By Section -->
                                <div class="text-center mt-2 mb-3">
                                    <div class="powered-by">
                                        <small>⚡ Powered by <strong>PARIPRINT.IN</strong></small>
                                    </div>
                                </div>

                                <hr>

                                <?php if (!$showOtpForm): ?>
                                    <!-- Main Form: EPIC → Mobile → Name → Aadhaar -->
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Voter EPIC Number <span class="text-danger">*</span></label>
                                            <input type="text" name="epic" class="form-control border-success" placeholder="Enter EPIC Number" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">New Mobile Number <span class="text-danger">*</span></label>
                                            <input type="text" name="new_mobile" class="form-control border-success" placeholder="10-digit mobile number" pattern="\d{10}" maxlength="10" minlength="10" required>
                                            <small class="text-muted">This number will be linked to the EPIC after verification</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Aadhaar Name <span class="text-danger">*</span></label>
                                            <input type="text" name="aadhaar_name" class="form-control border-success" placeholder="Name as per Aadhaar" required>
                                            <small class="text-warning">⚠️ If Aadhaar and Voter ID names differ, enter Aadhaar name.</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Aadhaar Number <span class="text-danger">*</span></label>
                                            <input type="text" name="aadhar" class="form-control border-success" placeholder="12-digit Aadhaar" pattern="\d{12}" maxlength="12" minlength="12" required>
                                            <small class="text-muted">Aadhaar number linked to the mobile where OTP will be sent</small>
                                        </div>

                                        <button type="submit" name="submit_mobile" class="btn btn-primary w-100 py-2 fw-bold">
                                            <i class="fa fa-check-circle"></i> Send OTP & Proceed
                                        </button>

                                        <div class="text-center mt-3">
                                            <div class="fee-container">
                                                <h5>Application Fee: ₹<?php echo $fee; ?></h5>
                                            </div>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <!-- OTP Verification Form (unchanged) -->
                                    <div class="otp-section">
                                        <h5 class="text-center mb-3">Enter OTP Sent to Aadhaar-linked Mobile</h5>
                                        <p class="text-center text-muted small"><?php echo htmlspecialchars($_SESSION['voter_link']['message']); ?></p>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">OTP <span class="text-danger">*</span></label>
                                                <input type="text" name="otp" class="form-control otp-input" placeholder="------" pattern="\d{6}" maxlength="6" required autofocus>
                                            </div>
                                            <button type="submit" name="verify_otp" class="btn btn-success w-100 py-2 fw-bold">
                                                <i class="fa fa-check-circle"></i> Verify OTP & Link Mobile
                                            </button>
                                            <div class="text-center mt-3">
                                                <a href="?cancel=1" class="btn btn-link text-danger">Cancel & Start Over</a>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <div class="alert alert-info mt-4 text-center small">
                                    💻 Powered by PARI PRINT &nbsp;|&nbsp; 🚀 Developed by LALA WEB DEVELOPER<br>
                                    🎯 Any Voter ID can be eSigned with any Aadhaar
                                </div>
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
<?php include('userFooter.php'); ?>