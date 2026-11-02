<?php
// ERROR REPORTING OFF FOR AJAX
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// --- CONFIGURATION ---
 $api_key    = "e5742e-4c57fc-d1c4ac-0390b5-8cbd0e"; 
 $secret_key = "e06450a0867d62c"; 
 $fee = 5; 

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean(); 
    $action = $_POST['ajax_action'];
    header('Content-Type: application/json');

    // 1. GENERATE OTP HANDLER
    if ($action === 'generate_otp') {
        $aadhaar = trim($_POST['aadhaar_number']);
        if (!preg_match('/^\d{12}$/', $aadhaar)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid 12-digit Aadhaar Number']);
            exit;
        }
        $api_url = "https://apiadda.in/AADHAR/generate_otp.php?" . http_build_query([
            'aadhaar_number' => $aadhaar, 'api_key' => $api_key, 'secret_key' => $secret_key
        ]);
        $curl = curl_init();
        curl_setopt_array($curl, [CURLOPT_URL => $api_url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
        $res = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) { echo json_encode(['status' => 'error', 'message' => 'Connection Failed: ' . $err]); exit; }
        $data = json_decode($res, true);
        if (isset($data['status']) && $data['status'] == 'success') {
            echo json_encode(['status' => 'success', 'txn_id' => $data['txn_id'], 'message' => $data['message']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $data['message'] ?? 'Server Error']);
        }
        exit;
    }

    // 2. VERIFY OTP HANDLER
    if ($action === 'verify_otp') {
        $aadhaar = trim($_POST['aadhaar_number']);
        $otp = trim($_POST['otp']);
        $txn_id = trim($_POST['txn_id']);
        $phone = $udata['phone'];
        $current_bal = $udata['balance'];
        $debit_fee = $current_bal - $fee;

        if ($current_bal < $fee) {
            echo json_encode(['status' => 'low_balance', 'message' => 'Insufficient Wallet Balance. Required: ₹' . $fee]);
            exit;
        }
        $api_url = "https://apiadda.in/AADHAR/verify_aadhaar.php?" . http_build_query([
            'aadhaar_number' => $aadhaar, 'otp' => $otp, 'txn_id' => $txn_id, 'api_key' => $api_key, 'secret_key' => $secret_key
        ]);
        $curl = curl_init();
        curl_setopt_array($curl, [CURLOPT_URL => $api_url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
        $res = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) { echo json_encode(['status' => 'error', 'message' => 'Connection Failed: ' . $err]); exit; }
        $data = json_decode($res, true);
        if (isset($data['status']) && $data['status'] == 'success') {
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$phone'");
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid,amount,balance,purpose,status,type) VALUES ('$phone','$fee','$debit_fee','Aadhaar Verification $aadhaar','1','Debit')");
            echo json_encode(['status' => 'success', 'data' => $data['data'], 'balance' => $data['balance'] ?? $debit_fee]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $data['message'] ?? 'Invalid OTP']);
        }
        exit;
    }
    echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
    exit;
}
?>

<!-- CSS FOR AADHAAR CARD STYLE -->
<style>
.aadhaar-card-container {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    border: 1px solid #eee;
}

.aadhaar-header {
    background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 4px solid #d35400;
}

.aadhaar-header h3 {
    margin: 0;
    color: #fff;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 1.2rem;
}

.verified-badge {
    background: #27ae60;
    color: #fff;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.aadhaar-body {
    display: flex;
    flex-wrap: wrap;
    padding: 25px;
}

/* Left Column: Photo */
.photo-section {
    flex: 0 0 200px;
    text-align: center;
    padding-right: 20px;
    border-right: 1px dashed #ddd;
}

.photo-frame {
    width: 160px;
    height: 160px;
    margin: 0 auto 10px;
    border: 3px solid #f39c12;
    border-radius: 8px;
    overflow: hidden;
    background: #f9f9f9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-label {
    color: #d35400;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
}

/* Right Column: Details */
.details-section {
    flex: 1;
    padding-left: 20px;
}

.detail-row {
    display: flex;
    margin-bottom: 12px;
    border-bottom: 1px solid #f1f1f1;
    padding-bottom: 8px;
}

.detail-label {
    width: 120px;
    font-weight: 700;
    color: #e67e22; /* Aadhaar Orange */
    font-size: 0.95rem;
    text-transform: uppercase;
}

.detail-value {
    flex: 1;
    color: #2c3e50;
    font-size: 1rem;
    font-weight: 500;
}

.print-btn {
    background: #2c3e50;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: 0.3s;
}

.print-btn:hover { background: #1a252f; }

/* Responsive */
@media (max-width: 600px) {
    .aadhaar-body { flex-direction: column; padding: 15px; }
    .photo-section { border-right: none; border-bottom: 1px dashed #ddd; padding-right: 0; padding-bottom: 15px; margin-bottom: 15px; }
    .photo-frame { width: 120px; height: 120px; }
    .details-section { padding-left: 0; }
    .detail-row { flex-direction: column; margin-bottom: 15px; border-bottom: none; }
}
</style>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Aadhaar Verification</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <!-- Form Section -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title text-dark text-center"><strong>Aadhaar Verification</strong></h3>
                            <p class="text-center text-muted small">Charge: ₹<?php echo $fee; ?> (Success Only)</p>
                        </div>
                        <div class="card-body">
                            <form id="aadhaarForm">
                                <input type="hidden" id="txn_id" name="txn_id">
                                
                                <div class="form-group mb-3">
                                    <label for="aadhaar_number">Enter 12 Digit Aadhaar Number</label>
                                    <div class="input-group">
                                        <input type="number" id="aadhaar_number" name="aadhaar_number" class="form-control" placeholder="1234 5678 9012" required>
                                        <button class="btn btn-primary" type="button" id="btnGenerateOTP" onclick="generateOTP()">Get OTP</button>
                                    </div>
                                </div>

                                <div id="otpSection" style="display: none;" class="fade-in">
                                    <div class="alert alert-success small text-center">OTP sent on registered mobile.</div>
                                    <div class="form-group mb-3">
                                        <label for="otp">Enter OTP</label>
                                        <input type="number" id="otp" name="otp" class="form-control text-center" style="letter-spacing: 5px; font-size: 1.2em;" placeholder="------" required>
                                    </div>
                                    <div class="d-grid">
                                        <button class="btn btn-success btn-lg" type="button" id="btnVerify" onclick="verifyAadhaar()">
                                            <i class="fa fa-shield-alt me-2"></i> Verify & Submit
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Response Section (New Aadhaar Card Design) -->
                <div class="col-lg-8" id="resultContainer" style="display: none;">
                    <div class="aadhaar-card-container" id="printSection">
                        <!-- Card Header -->
                        <div class="aadhaar-header">
                            <div class="d-flex align-items-center">
                                <span style="font-size: 1.5rem; margin-right: 10px;">🇮🇳</span>
                                <div>
                                    <h3 style="margin:0; font-size: 1rem;">Government of India</h3>
                                    <h3 style="margin:0;">Verification Result</h3>
                                </div>
                            </div>
                            <span class="verified-badge">VERIFIED</span>
                        </div>

                        <!-- Card Body -->
                        <div class="aadhaar-body">
                            <!-- Photo Section -->
                            <div class="photo-section">
                                <div class="photo-frame">
                                    <img id="resImage" src="">
                                </div>
                                <div class="photo-label">Photo</div>
                            </div>
                            
                            <!-- Details Section -->
                            <div class="details-section">
                                <div class="detail-row">
                                    <div class="detail-label">Name</div>
                                    <div class="detail-value" id="resName"></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">DOB</div>
                                    <div class="detail-value" id="resDob"></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Gender</div>
                                    <div class="detail-value" id="resGender"></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Address</div>
                                    <div class="detail-value" id="resAddress"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="card-footer bg-white border-0 text-end pb-3 pe-4">
                            <button onclick="window.print()" class="print-btn">
                                <i class="fa fa-print"></i> Print Result
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateOTP() {
    const aadhaar = document.getElementById('aadhaar_number').value;
    const btn = document.getElementById('btnGenerateOTP');
    if(aadhaar.length !== 12) { Swal.fire('Warning', 'Please enter valid 12 digit Aadhaar Number', 'warning'); return; }
    
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('ajax_action', 'generate_otp');
    formData.append('aadhaar_number', aadhaar);

    fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('txn_id').value = data.txn_id;
            document.getElementById('otpSection').style.display = 'block';
            document.getElementById('aadhaar_number').readOnly = true; 
            btn.classList.add('d-none');
        } else {
            Swal.fire('Error', data.message, 'error');
            btn.innerHTML = 'Get OTP'; btn.disabled = false;
        }
    })
    .catch(error => { Swal.fire('Error', 'Network Error', 'error'); btn.innerHTML = 'Get OTP'; btn.disabled = false; });
}

function verifyAadhaar() {
    const aadhaar = document.getElementById('aadhaar_number').value;
    const otp = document.getElementById('otp').value;
    const txn_id = document.getElementById('txn_id').value;
    const btn = document.getElementById('btnVerify');

    if(otp.length < 4) { Swal.fire('Warning', 'Please enter valid OTP', 'warning'); return; }

    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Verifying...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('ajax_action', 'verify_otp');
    formData.append('aadhaar_number', aadhaar);
    formData.append('otp', otp);
    formData.append('txn_id', txn_id);

    fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('resName').innerText = data.data.name;
            document.getElementById('resDob').innerText = data.data.dob;
            document.getElementById('resGender').innerText = data.data.gender;
            document.getElementById('resAddress').innerText = data.data.address;
            document.getElementById('resImage').src = data.data.image_src;
            document.getElementById('resultContainer').style.display = 'block';
            Swal.fire({ title: 'Verified!', text: 'Data Fetched Successfully.', icon: 'success', timer: 1500, showConfirmButton: false });
        } else if (data.status === 'low_balance') {
            Swal.fire({ title: 'Low Balance', text: data.message, icon: 'error', confirmButtonText: 'Recharge Now' }).then((result) => { if(result.isConfirmed) window.location.href = 'wallet.php'; });
        } else {
            Swal.fire('Failed', data.message, 'error');
            btn.disabled = false; btn.innerHTML = '<i class="fa fa-shield-alt me-2"></i> Verify & Submit';
        }
    })
    .catch(error => { Swal.fire('Error', 'Something went wrong', 'error'); btn.disabled = false; btn.innerHTML = '<i class="fa fa-shield-alt me-2"></i> Verify & Submit'; });
}
</script>

<?php include('footer.php'); ?>