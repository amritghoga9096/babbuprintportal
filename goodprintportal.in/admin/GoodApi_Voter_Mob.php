<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

/* ================= CHECK LOGIN ================= */
if (!isset($udata) || empty($udata)) {
    header("Location: ../login.php");
    exit();
}

$userid = $udata['id'];
$old_wallet = (float)$udata['balance'];
$voter_mobile_fee = (float)($price['voter_mobile_fee'] ?? 50);
$UserMob = preg_replace('/\D/', '', $udata['phone']);
$UserName = $udata['name'] ?? '';
$referPhone = $udata['refer_phone'] ?? '';

/* ================= API DETAILS ================= */
$purpose = "VOTER_MOBILE_LINK_NEW";
$timestamp = date("Y-m-d H:i:s");

$error = "";
$success = "";
$showStep2 = false;
$epic = $_SESSION['epic'] ?? '';
$mobile = $_SESSION['mobile'] ?? '';
$refId = $_SESSION['refId'] ?? '';
$tracking_id = $_SESSION['tracking_id'] ?? '';
$esignUrl = $_SESSION['esignUrl'] ?? '';

/* ================= FIXED API CONFIG ================= */
$apiKey = '9e67717e0b5467ab9aebdd56292446e922837152c9835a5ae249469fbaaed356';

/* =========================================================
   STEP 1 : EPIC + MOBILE → GENERATE REF ID
========================================================= */
if(isset($_POST['generate_ref'])){
    $epic = strtoupper(trim($_POST['epic']));
    $mobile = trim($_POST['mobile']);

    if(empty($epic) || empty($mobile)){
        $error = "EPIC & Mobile Required.";
    }else{
        if($old_wallet < $voter_mobile_fee){
            $error = "Insufficient Wallet Balance. Need ₹$voter_mobile_fee";
        }else{
            // DIRECT API URL - NO VARIABLES
            $apiUrl = "https://goodapi.in/api_services/V1/voterServices/voter_mobileUpdate.php?epic=$epic&mobile=$mobile&apiKey=$apiKey";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if($curlError){
                $error = "CURL Error: " . $curlError;
            } elseif($httpCode != 200){
                $error = "API Connection Failed. HTTP Code: $httpCode";
            } else {
                $res = json_decode($response, true);
                
                // Debug logging
                error_log("Voter Mobile API Response: " . print_r($res, true));

                if(isset($res['Status']) && $res['Status'] == "Success"){
                    
                    if(isset($res['data']['refId'])) {
                        $_SESSION['refId'] = $res['data']['refId'];
                        $_SESSION['epic'] = $epic;
                        $_SESSION['mobile'] = $mobile;
                        $_SESSION['tracking_id'] = $res['tracking_id'] ?? '';
                        $_SESSION['esignUrl'] = $res['data']['esignUrl'] ?? '';
                        
                        $refId = $res['data']['refId'];
                        $tracking_id = $res['tracking_id'] ?? '';
                        $esignUrl = $res['data']['esignUrl'] ?? '';
                        $showStep2 = true;
                        $success = "Reference ID generated successfully! Please enter your Aadhaar name.";
                    } else {
                        $error = "API Response missing refId: " . json_encode($res);
                    }
                    
                } else {
                    $error = $res['message'] ?? "API Error. Please try again.";
                }
            }
        }
    }
}

/* =========================================================
   STEP 2 : ESIGN SUBMISSION
========================================================= */
if(isset($_POST['esign_submit'])){
    $refId = $_SESSION['refId'] ?? '';
    $epic = $_SESSION['epic'] ?? '';
    $mobile = $_SESSION['mobile'] ?? '';
    $tracking_id = $_SESSION['tracking_id'] ?? '';
    $esignUrl = $_SESSION['esignUrl'] ?? '';
    $signerName = trim($_POST['signerName']);

    if(empty($refId) || empty($epic) || empty($mobile)){
        $error = "Session Expired. Please start again.";
    }
    elseif(empty($signerName)){
        $error = "Please enter your Aadhaar name.";
    }
    elseif($old_wallet < $voter_mobile_fee){
        $error = "Insufficient Wallet Balance. Need ₹$voter_mobile_fee";
    }
    else{
        $Traking_No = "VOTERMOB" . uniqid() . rand(100,999);
        
        mysqli_begin_transaction($ahk_conn);
        
        try {
            // Wallet Deduct
            $new_wallet = $old_wallet - $voter_mobile_fee;
            
            // Update user balance
            $update = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $voter_mobile_fee WHERE id='$userid'");
            if(!$update) throw new Exception("Wallet update failed");

            // SUMMARY INSERT
            $summary_query = "INSERT INTO card_summary(Requst_Name, Requst_No, Purpose, Traking_No, Status, fee, type, old_balance, new_balance, date, username)
                VALUES ('$mobile', '$epic', '$purpose', '$Traking_No', 'Pending eSign', '$voter_mobile_fee', 'Debit', '$old_wallet', '$new_wallet', '$timestamp', '$userid')";
            
            if(!mysqli_query($ahk_conn, $summary_query)) throw new Exception("Summary insert failed");
            
            // Service Table Insert
            $service = mysqli_query($ahk_conn, "INSERT INTO voter_services
                (user_id, user_mobile, transaction_id, epic_number, voter_name, new_mobile, reference_id, purpose, fee, created_at, status, payment_status)
                VALUES ('$userid', '$UserMob', '$Traking_No', '$epic', '$signerName', '$mobile', '$refId', '$purpose', '$voter_mobile_fee', '$timestamp', 'Pending eSign', 'Paid')");
            if(!$service) throw new Exception("Service insert failed: " . mysqli_error($ahk_conn));
            
            mysqli_commit($ahk_conn);
            
            // DIRECT ESIGN REDIRECT URL - NO VARIABLES
            $redirectUrl = "https://goodapi.in/api_services/V1/voterServices/voter_esign_redirect.php?refId=$refId&signerName=".urlencode($signerName);
            
            // Clear session data
            unset($_SESSION['refId']);
            unset($_SESSION['epic']);
            unset($_SESSION['mobile']);
            unset($_SESSION['tracking_id']);
            unset($_SESSION['esignUrl']);
            
            // Redirect to eSign portal
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Payment Successful!',
                    text: 'Redirecting to eSign portal...',
                    showConfirmButton: false,
                    timer: 2000
                }).then(function() {
                    window.location.href = '$redirectUrl';
                });
            </script>";
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($ahk_conn);
            $error = "Transaction failed: " . $e->getMessage();
        }
    }
}

// Clear session if user wants to start over
if(isset($_GET['new'])){
    unset($_SESSION['refId']);
    unset($_SESSION['epic']);
    unset($_SESSION['mobile']);
    unset($_SESSION['tracking_id']);
    unset($_SESSION['esignUrl']);
    header("Location: GoodApi_Voter_Mob.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Mobile Link - <?php echo SITE_TITLE; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Verdana, sans-serif; 
        }
        .main-wrapper {
            padding: 30px 15px;
        }
        .verification-card { 
            border-radius: 15px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.15); 
            background: #fff; 
            border: none;
            overflow: hidden;
        }
        .card-header { 
            padding: 20px 25px;
            border-bottom: none;
        }
        .card-header.bg-primary { 
            background: linear-gradient(45deg, #667eea, #764ba2) !important; 
        }
        .wallet-info { 
            background: linear-gradient(45deg, #2d3748, #4a5568); 
            color: white; 
            border-radius: 15px; 
            padding: 20px 25px; 
            margin-bottom: 30px;
        }
        .form-control, .form-control:focus {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 15px;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #667eea;
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: linear-gradient(45deg, #48bb78, #38a169);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
        }
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: #e2e8f0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .step.active .step-number {
            background: #667eea;
            color: white;
        }
        .step.completed .step-number {
            background: #48bb78;
            color: white;
        }
        .step-label {
            font-size: 12px;
            color: #718096;
        }
        .step.active .step-label {
            color: #667eea;
            font-weight: bold;
        }
        .fee-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #ffc107;
            color: #000;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <div class="container">
        <div class="text-center mb-4">
            <h1 class="fw-bold text-white"><i class="fas fa-id-card me-2"></i><?php echo SITE_TITLE; ?> - Voter Mobile Link</h1>
            <p class="text-white-50">Link your mobile number with eSign verification</p>
        </div>
        
        <!-- Step Indicator -->
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <div class="step-indicator">
                    <div class="step <?php echo $showStep2 ? 'completed' : 'active'; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Enter Details</div>
                    </div>
                    <div class="step <?php echo $showStep2 ? 'active' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">eSign Verification</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Wallet Balance -->
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <div class="wallet-info">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Your Wallet Balance</h5>
                        </div>
                        <div class="col-auto">
                            <h3 class="mb-0">₹<?php echo number_format($old_wallet, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <!-- Form Section -->
            <div class="col-md-8 mb-4">
                <div class="card verification-card">
                    <div class="card-header bg-primary text-white position-relative">
                        <h4 class="mb-0">
                            <i class="fas fa-mobile-alt me-2"></i>
                            <?php echo $showStep2 ? 'Step 2: eSign Verification' : 'Step 1: Enter Voter Details'; ?>
                        </h4>
                        <div class="fee-badge">
                            <i class="fas fa-rupee-sign me-1"></i> <?php echo number_format($voter_mobile_fee, 2); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <!-- STEP 1 FORM -->
                        <?php if(!$showStep2): ?>
                        <form method="POST" id="step1Form">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-id-card text-primary me-1"></i>Voter ID Number (EPIC)
                                </label>
                                <input type="text"
                                       class="form-control text-uppercase"
                                       name="epic"
                                       placeholder="Enter Voter ID Number (e.g., DEL0986331)"
                                       value="<?php echo htmlspecialchars($epic); ?>"
                                       required>
                                <small class="text-muted">Example: DEL0986331</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-phone text-success me-1"></i>Mobile Number
                                </label>
                                <input type="tel"
                                       class="form-control"
                                       name="mobile"
                                       placeholder="Enter 10 Digit Mobile Number"
                                       pattern="[0-9]{10}"
                                       maxlength="10"
                                       value="<?php echo htmlspecialchars($mobile); ?>"
                                       required>
                                <small class="text-muted">10-digit mobile number only</small>
                            </div>

                            <div class="info-box">
                                <p class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i><strong>Process:</strong></p>
                                <ol class="mb-0 ps-3">
                                    <li class="mb-1">Enter your Voter ID and mobile number</li>
                                    <li class="mb-1">System will generate Reference ID</li>
                                    <li class="mb-1">Enter Aadhaar name for eSign</li>
                                    <li class="mb-1">Complete OTP verification on government portal</li>
                                    <li class="mb-1">Mobile number will be linked to voter ID</li>
                                </ol>
                            </div>

                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Service Fee:</strong> ₹<?php echo number_format($voter_mobile_fee, 2); ?> will be deducted from your wallet.
                            </div>

                            <button type="submit"
                                    name="generate_ref"
                                    class="btn btn-primary w-100 py-3"
                                    id="generateBtn">
                                <i class="fas fa-paper-plane me-2"></i>
                                Generate Reference ID (₹<?php echo number_format($voter_mobile_fee, 2); ?>)
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- STEP 2 FORM -->
                        <?php if($showStep2): ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Reference ID:</strong> <?php echo htmlspecialchars($refId); ?><br>
                            <strong>Tracking ID:</strong> <?php echo htmlspecialchars($tracking_id); ?>
                        </div>

                        <form method="POST" id="step2Form">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-user text-warning me-1"></i>Aadhaar Name (As on Aadhaar Card)
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="signerName"
                                       placeholder="Enter name exactly as on Aadhaar"
                                       value="<?php echo htmlspecialchars($UserName); ?>"
                                       required>
                                <small class="text-muted">Enter your full name as it appears on your Aadhaar card</small>
                            </div>

                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Note:</strong> ₹<?php echo number_format($voter_mobile_fee, 2); ?> will be deducted from your wallet.
                                <br>After submission, you'll be redirected to the government eSign portal for OTP verification.
                            </div>

                            <button type="submit"
                                    name="esign_submit"
                                    class="btn btn-success w-100 py-3"
                                    id="esignBtn">
                                <i class="fas fa-shield-alt me-2"></i>
                                Proceed to eSign (OTP Verification)
                            </button>
                            
                            <a href="?new=1" class="btn btn-outline-secondary w-100 mt-3">
                                <i class="fas fa-redo me-2"></i>Start Over
                            </a>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation and loading states
document.getElementById('step1Form')?.addEventListener('submit', function(e){
    const epic = document.querySelector('[name="epic"]').value;
    const mobile = document.querySelector('[name="mobile"]').value;
    
    if(!epic || epic.length < 10){
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid EPIC Number',
            text: 'Please enter a valid Voter ID (EPIC) number'
        });
        return false;
    }
    
    if(!/^\d{10}$/.test(mobile)){
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid Mobile Number',
            text: 'Please enter a valid 10-digit mobile number'
        });
        return false;
    }
    
    // Show loading
    const btn = document.getElementById('generateBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    btn.disabled = true;
});

document.getElementById('step2Form')?.addEventListener('submit', function(e){
    const signerName = document.querySelector('[name="signerName"]').value;
    
    if(!signerName || signerName.trim().length < 3){
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid Name',
            text: 'Please enter your full name as on Aadhaar card'
        });
        return false;
    }
    
    // Show loading
    const btn = document.getElementById('esignBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Redirecting to eSign...';
    btn.disabled = true;
});

// Auto-format mobile
document.querySelector('[name="mobile"]')?.addEventListener('input', function(){
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

// Auto-format epic to uppercase
document.querySelector('[name="epic"]')?.addEventListener('input', function(){
    this.value = this.value.toUpperCase();
});

// Display any PHP errors/success with SweetAlert
<?php if($error && !$showStep2): ?>
Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: '<?php echo addslashes($error); ?>',
    confirmButtonColor: '#667eea'
});
<?php endif; ?>

<?php if($success && !$showStep2): ?>
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?php echo addslashes($success); ?>',
    confirmButtonColor: '#667eea'
});
<?php endif; ?>
</script>

<?php include('../template/ahkweb/footer.php'); ?>