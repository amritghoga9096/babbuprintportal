<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

$resdata = [];
$mobile = "";
$step = 1;
$apiadda_uniq_id = "";
$otp_sent = false;
$otp_error = "";
$pan_result = false;

// Fetch current pricing for Aadhaar to PAN find service
$price_query = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Aadhaar_To_Pan_find'");
$price = mysqli_fetch_assoc($price_query);
$fee = $price['price'] ?? 0;

// ------------------------------------------------------------------
// NEW API V2.0 ENDPOINT - NO OTP REQUIRED
// ------------------------------------------------------------------
// This version works with name + mobile to fetch PAN directly.
// No separate send_otp / verify_otp steps needed.
// ------------------------------------------------------------------

// Helper: Call new PAN API v2.0
function callPanApiV2($name, $mobile, $api_key, $secret_key) {
    $url = "https://apiadda.in/PANDATA/panfind_v2.0.php?" . http_build_query([
        'api_key' => $api_key,
        'secret_key' => $secret_key,
        'name' => $name,
        'mobile' => $mobile
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false, // only if needed, ideally true in production
    ]);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return ['error' => "Connection Error: " . $curl_error];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => "Invalid API response format"];
    }
    
    return $data;
}

// ------------------------------------------------------------------
// STEP 1: Show form to enter Name + Mobile (NEW)
// ------------------------------------------------------------------
if ($step === 1 && isset($_POST['action']) && $_POST['action'] === "fetch_pan") {
    $name = trim(mysqli_real_escape_string($ahk_conn, $_POST['full_name'] ?? ''));
    $mobile = trim(mysqli_real_escape_string($ahk_conn, $_POST['mobile_no'] ?? ''));
    
    // Validation
    if (empty($name)) {
        $otp_error = "Please enter the full name as per PAN card";
    } elseif (strlen($mobile) != 10 || !ctype_digit($mobile)) {
        $otp_error = "Please enter a valid 10-digit Mobile Number";
    } else {
        // Call NEW API directly
        $apiResponse = callPanApiV2($name, $mobile, $api_key, $secret_key);
        
        if (isset($apiResponse['error'])) {
            $otp_error = $apiResponse['error'];
        } 
        elseif (isset($apiResponse['status']) && $apiResponse['status'] === "success" && isset($apiResponse['data']['pan_number'])) {
            // Success: PAN data retrieved
            $pan_number = mysqli_real_escape_string($ahk_conn, $apiResponse['data']['pan_number']);
            $pan_name = mysqli_real_escape_string($ahk_conn, $apiResponse['data']['name'] ?? $name);
            $response_message = mysqli_real_escape_string($ahk_conn, $apiResponse['message'] ?? '');
            $api_response_json = mysqli_real_escape_string($ahk_conn, json_encode($apiResponse));
            
            // Generate application number
            $application_no = "PANF" . rand(100000, 999999);
            $username = $udata['phone'];
            $appliedby = $udata['phone'];
            $wallet_amount = $udata['balance'];
            $debit_fee = $wallet_amount - $fee;
            
            // Check wallet balance
            if ($wallet_amount >= $fee) {
                // Debit fee from user wallet
                $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                if ($debit) {
                    $new_balance = $wallet_amount - $fee;
                    mysqli_query($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$appliedby', '$fee', '$new_balance', 'PAN Number Find', '1', 'Debit')");
                    
                    // Insert record into database
                    $sql_insert = "INSERT INTO instantpan_find 
                        (application_no, username, aadhaar_no, pan_no, status, status_code, fee, old_balance, new_balance, message, full_name) 
                        VALUES 
                        ('$application_no', '$username', '$mobile', '$pan_number', '100', '$api_response_json', '$fee', '$wallet_amount', '$new_balance', '$response_message', '$pan_name')";
                    mysqli_query($ahk_conn, $sql_insert);
                    
                    // Prepare result data
                    $resdata = [
                        'data' => [
                            'pan_number' => $pan_number,
                            'name' => $pan_name,
                            'mobile' => $mobile,
                            'reference_id' => $apiResponse['api']['reference_id'] ?? $application_no,
                            'timestamp' => $apiResponse['api']['timestamp'] ?? date('Y-m-d H:i:s')
                        ]
                    ];
                    $pan_result = true;
                    $step = 3;
                } else {
                    $otp_error = "Transaction failed. Please try again.";
                }
            } else {
                $otp_error = "Insufficient wallet balance. Please recharge. Required: ₹$fee, Available: ₹$wallet_amount";
            }
        } 
        else {
            // API returned error
            $error_msg = $apiResponse['message'] ?? "PAN not found. Please check name and mobile number.";
            $otp_error = $error_msg;
            
            // Optionally store failed attempt
            $application_no = "PANF" . rand(100000, 999999);
            $username = $udata['phone'];
            $api_response_json = mysqli_real_escape_string($ahk_conn, json_encode($apiResponse));
            $sql_insert = "INSERT INTO instantpan_find 
                (application_no, username, aadhaar_no, pan_no, status, status_code, fee, old_balance, new_balance, message, full_name) 
                VALUES 
                ('$application_no', '$username', '$mobile', '', '400', '$api_response_json', '0', '{$udata['balance']}', '{$udata['balance']}', '$error_msg', '$name')";
            mysqli_query($ahk_conn, $sql_insert);
        }
    }
}

// ------------------------------------------------------------------
// Keep compatibility for old OTP flow if needed (optional - can be removed)
// But to avoid breaking existing references, we'll keep but redirect to step1 if old step2 accessed without session
// ------------------------------------------------------------------
if ($step === 2) {
    // If someone tries to access step2 directly (old flow), redirect to step1
    // Because new API doesn't use OTP
    $step = 1;
    unset($_SESSION['pan_find_uniq_id']);
    unset($_SESSION['pan_find_mobile']);
    $otp_error = "New version uses direct PAN fetch. Please enter name and mobile.";
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f8fafc;color:#1e293b;min-height:100vh;-webkit-font-smoothing:antialiased}

/* ---- Page Shell ---- */
.page-shell{position:relative;overflow:hidden}
.page-shell::before{content:'';position:absolute;top:-180px;right:-120px;width:500px;height:500px;background:radial-gradient(circle,rgba(99,102,241,0.08),transparent 70%);border-radius:50%;pointer-events:none}
.page-shell::after{content:'';position:absolute;bottom:-100px;left:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(14,165,233,0.06),transparent 70%);border-radius:50%;pointer-events:none}

.container-x{position:relative;z-index:1;max-width:540px;margin:0 auto;padding:32px 20px 60px}

/* ---- Top Nav ---- */
.top-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:40px}
.nav-back{width:42px;height:42px;border-radius:12px;border:1.5px solid #e2e8f0;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;color:#475569;font-size:18px;text-decoration:none}
.nav-back:hover{background:#f1f5f9;border-color:#cbd5e1;transform:translateX(-2px)}
.nav-right{display:flex;gap:8px}
.nav-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:50px;border:1.5px solid #e2e8f0;background:#fff;font-size:13px;font-weight:600;color:#475569;cursor:pointer;transition:all .2s;text-decoration:none;font-family:inherit}
.nav-pill:hover{border-color:#6366f1;color:#6366f1;background:#fafafe}

/* ---- Stepper (updated for 2-step process) ---- */
.stepper{display:flex;align-items:center;justify-content:center;margin-bottom:36px;position:relative}
.stp{display:flex;flex-direction:column;align-items:center;gap:8px;position:relative;z-index:2}
.stp-dot{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;border:2.5px solid #e2e8f0;color:#94a3b8;background:#fff;transition:all .45s cubic-bezier(.4,0,.2,1)}
.stp.active .stp-dot{border-color:#6366f1;background:#6366f1;color:#fff;box-shadow:0 0 0 6px rgba(99,102,241,.12),0 4px 16px rgba(99,102,241,.2)}
.stp.done .stp-dot{border-color:#22c55e;background:#22c55e;color:#fff;box-shadow:0 0 0 6px rgba(34,197,94,.1)}
.stp-dot i{font-size:18px}
.stp-text{font-size:12px;font-weight:600;color:#94a3b8;transition:color .3s;letter-spacing:.2px}
.stp.active .stp-text{color:#6366f1}
.stp.done .stp-text{color:#22c55e}
.stp-line{width:72px;height:2px;background:#e2e8f0;border-radius:2px;margin:0 8px;margin-bottom:28px;position:relative;overflow:hidden}
.stp-line::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,#22c55e,#6366f1);border-radius:2px;transform:scaleX(0);transform-origin:left;transition:transform .5s cubic-bezier(.4,0,.2,1)}
.stp-line.fill::after{transform:scaleX(1)}

/* ---- Main Card ---- */
.main-card{background:#fff;border-radius:20px;border:1px solid #e8ecf1;box-shadow:0 1px 2px rgba(0,0,0,.04),0 8px 32px rgba(0,0,0,.04);overflow:hidden}
.card-top{padding:28px 28px 0;display:flex;align-items:center;gap:16px}
.card-ico{width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.card-ico.blue{background:linear-gradient(135deg,#eef2ff,#e0e7ff);color:#6366f1}
.card-ico.violet{background:linear-gradient(135deg,#f5f3ff,#ede9fe);color:#8b5cf6}
.card-ico.green{background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#22c55e}
.card-top h2{font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;color:#0f172a;letter-spacing:-.3px}
.card-top p{font-size:13px;color:#94a3b8;margin-top:2px;font-weight:500}
.card-body{padding:28px}

/* ---- Illustration area ---- */
.illust{display:flex;justify-content:center;margin-bottom:24px}
.illust-circle{width:120px;height:120px;border-radius:50%;position:relative;display:flex;align-items:center;justify-content:center}
.illust-circle::before{content:'';position:absolute;inset:-8px;border-radius:50%;border:2px dashed #e2e8f0;animation:spin 30s linear infinite}
.illust-circle::after{content:'';position:absolute;inset:-20px;border-radius:50%;border:2px dashed #f1f5f9;animation:spin 45s linear infinite reverse}
@keyframes spin{to{transform:rotate(360deg)}}
.illust-inner{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:34px}
.illust-inner.blue{background:linear-gradient(135deg,#6366f1,#818cf8);color:#fff;box-shadow:0 8px 24px rgba(99,102,241,.25)}
.illust-inner.violet{background:linear-gradient(135deg,#8b5cf6,#a78bfa);color:#fff;box-shadow:0 8px 24px rgba(139,92,246,.25)}
.illust-inner.green{background:linear-gradient(135deg,#22c55e,#4ade80);color:#fff;box-shadow:0 8px 24px rgba(34,197,94,.25);animation:popBounce .6s cubic-bezier(.175,.885,.32,1.275)}
@keyframes popBounce{0%{transform:scale(0)}60%{transform:scale(1.15)}100%{transform:scale(1)}}

/* ---- Form ---- */
.fg{margin-bottom:20px}
.fl{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:8px}
.fl .req{color:#ef4444;margin-left:2px}
.iw{position:relative}
.iw .ii{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:18px;pointer-events:none;transition:color .2s}
.if{width:100%;padding:15px 16px 15px 50px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;color:#0f172a;font-size:16px;font-family:'Plus Jakarta Sans',sans-serif;transition:all .2s;outline:none}
.if::placeholder{color:#cbd5e1;font-family:'Plus Jakarta Sans',sans-serif;font-weight:400;font-size:14px}
.if:focus{border-color:#6366f1;background:#fff;box-shadow:0 0 0 4px rgba(99,102,241,.08)}
.if:focus~.ii{color:#6366f1}

/* ---- Buttons ---- */
.btn-main{width:100%;padding:16px;border:none;border-radius:14px;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:10px;letter-spacing:.3px;position:relative;overflow:hidden}
.btn-main.blue{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.3)}
.btn-main.blue:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(99,102,241,.35)}
.btn-main.blue:active{transform:translateY(0)}
.btn-main.violet{background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;box-shadow:0 4px 16px rgba(139,92,246,.3)}
.btn-main.violet:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(139,92,246,.35)}
.btn-main.green{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;box-shadow:0 4px 16px rgba(34,197,94,.3);width:auto;padding:13px 28px}
.btn-main.green:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(34,197,94,.35)}
.btn-sec{width:100%;padding:15px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;font-size:14px;font-weight:600;font-family:inherit;color:#475569;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-sec:hover{background:#f1f5f9;border-color:#cbd5e1}

/* ---- Fee Tag ---- */
.fee-tag{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:50px;margin-top:18px}
.fee-tag i{color:#f59e0b;font-size:16px}
.fee-tag span{font-size:13px;color:#78716c;font-weight:500}
.fee-tag strong{color:#d97706;font-size:17px;font-weight:800}

/* ---- Error ---- */
.err{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px;margin-bottom:20px;animation:errShake .4s ease}
.err i{color:#ef4444;font-size:18px;margin-top:1px;flex-shrink:0}
.err p{color:#dc2626;font-size:13px;font-weight:500;line-height:1.5}
@keyframes errShake{0%{transform:translateX(-8px);opacity:0}30%{transform:translateX(6px)}60%{transform:translateX(-3px)}100%{transform:translateX(0);opacity:1}}

/* ---- Trust Row ---- */
.trust{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}
.trust-i{display:flex;align-items:center;gap:8px;padding:11px 14px;background:#f8fafc;border:1px solid #f1f5f9;border-radius:12px}
.trust-i i{font-size:15px;color:#6366f1}
.trust-i span{font-size:11px;color:#64748b;font-weight:500;line-height:1.3}

/* ---- Result ---- */
.res-table{width:100%;border-collapse:separate;border-spacing:0;margin:0}
.res-table tr td,.res-table tr th{padding:18px 22px;text-align:left;border-bottom:1px solid #f1f5f9}
.res-table tr:last-child td,.res-table tr:last-child th{border-bottom:none}
.res-table th{font-size:13px;font-weight:600;color:#94a3b8;width:42%;letter-spacing:.2px}
.res-table td{font-size:15px;font-weight:600;color:#0f172a}
.pan-show{font-family:'Space Grotesk',monospace;font-size:26px;font-weight:700;color:#6366f1;letter-spacing:3px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);padding:8px 20px;border-radius:12px;display:inline-block;border:1px solid #c7d2fe}
.name-show{font-size:18px;font-weight:700}
.gen-tag{display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:50px;font-size:13px;font-weight:600}
.gen-tag.m{background:#eef2ff;color:#6366f1}
.gen-tag.f{background:#fdf2f8;color:#ec4899}
.app-tag{display:inline-flex;align-items:center;gap:7px;padding:7px 16px;background:#f5f3ff;border:1px solid #ede9fe;border-radius:50px;font-family:'Space Grotesk',monospace;font-size:12px;font-weight:600;color:#7c3aed;margin-top:16px}
.res-acts{display:flex;gap:10px;margin-top:28px;flex-wrap:wrap;justify-content:center}
.res-acts .nav-pill{padding:12px 22px}

/* ---- Slide Up ---- */
.slide-up{animation:slideUp .45s cubic-bezier(.4,0,.2,1)}
@keyframes slideUp{0%{transform:translateY(20px);opacity:0}100%{transform:translateY(0);opacity:1}}

.hidden{display:none!important}
.ft{text-align:center;padding:24px;color:#94a3b8;font-size:12px}
.ft strong{color:#64748b}

/* ---- Responsive ---- */
@media(max-width:768px){
    .container-x{padding:16px 14px 40px}
    .stp-text{display:none}
    .stp-line{width:50px}
    .trust{grid-template-columns:1fr}
    .card-body{padding:22px}
    .pan-show{font-size:20px;letter-spacing:2px}
    .res-acts{flex-direction:column}
    .res-acts .nav-pill,.res-acts .btn-main.green{width:100%;justify-content:center}
}
@media(max-width:400px){
    .card-top{padding:22px 20px 0}
    .card-body{padding:20px}
    .res-table tr td,.res-table tr th{padding:14px 16px}
}
</style>

<div class="page-shell">
<div class="container-x">

    <!-- Nav -->
    <div class="top-nav">
        <a href="javascript:history.back()" class="nav-back"><i class="bi bi-arrow-left"></i></a>
        <div class="nav-right">
            <a href="instant_pan_list" class="nav-pill"><i class="bi bi-clock-history"></i> History</a>
        </div>
    </div>

    <!-- Stepper (Now only 2 steps) -->
    <div class="stepper">
        <div class="stp <?php echo $step>1?'done':($step===1?'active':'') ?>">
            <div class="stp-dot"><?php if($step>1):?><i class="bi bi-check-lg"></i><?php else:?>1<?php endif;?></div>
            <div class="stp-text">Details</div>
        </div>
        <div class="stp-line <?php echo $step>1?'fill':'' ?>"></div>
        <div class="stp <?php echo $step>=3?'active':'' ?>">
            <div class="stp-dot">2</div>
            <div class="stp-text">Result</div>
        </div>
    </div>

    <!-- ====== STEP 1: Name + Mobile Input (NEW) ====== -->
    <?php if($step === 1):?>
    <div class="main-card slide-up">
        <div class="card-top">
            <div class="card-ico blue"><i class="bi bi-person-badge"></i></div>
            <div><h2>PAN Card Details</h2><p>Enter name & registered mobile number</p></div>
        </div>
        <div class="card-body">
            <div class="illust">
                <div class="illust-circle">
                    <div class="illust-inner blue"><i class="bi bi-credit-card-2-front"></i></div>
                </div>
            </div>

            <?php if(!empty($otp_error)):?>
            <div class="err"><i class="bi bi-exclamation-triangle-fill"></i><p><?php echo htmlspecialchars($otp_error);?></p></div>
            <?php endif;?>

            <form method="POST" id="step1Form">
                <input type="hidden" name="action" value="fetch_pan">
                <div class="fg">
                    <label class="fl">Full Name (as on PAN card) <span class="req">*</span></label>
                    <div class="iw">
                        <input type="text" name="full_name" class="if" id="nameInput" placeholder="e.g. NIRDOSH KUMAR" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? '');?>">
                        <i class="bi bi-person ii"></i>
                    </div>
                </div>
                <div class="fg">
                    <label class="fl">Registered Mobile Number <span class="req">*</span></label>
                    <div class="iw">
                        <input type="tel" name="mobile_no" class="if" id="mobileInput" placeholder="10-digit mobile number" maxlength="10" required pattern="[0-9]{10}" value="<?php echo htmlspecialchars($_POST['mobile_no'] ?? $mobile);?>">
                        <i class="bi bi-phone ii"></i>
                    </div>
                </div>
                <button type="submit" class="btn-main blue"><i class="bi bi-search-heart-fill"></i> Fetch PAN Card</button>
                <div style="text-align:center"><div class="fee-tag"><i class="bi bi-info-circle-fill"></i><span>Charges:</span><strong>&#8377;<?php echo $fee;?></strong></div></div>
            </form>
            <div class="trust">
                <div class="trust-i"><i class="bi bi-shield-check"></i><span>Verified API</span></div>
                <div class="trust-i"><i class="bi bi-lightning-charge-fill"></i><span>Instant Result</span></div>
                <div class="trust-i"><i class="bi bi-lock-fill"></i><span>Secure Data</span></div>
            </div>
        </div>
    </div>
    <?php endif;?>

    <!-- ====== STEP 2 (Result) ====== -->
    <?php if($step === 3 && $pan_result && !empty($resdata['data'])):?>
    <div class="main-card slide-up" id="printSection">
        <div class="card-top" style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);padding:28px">
            <div class="card-ico green"><i class="bi bi-patch-check-fill"></i></div>
            <div><h2 style="color:#16a34a">PAN Found</h2><p>Successfully retrieved from database</p></div>
        </div>
        <div class="card-body" style="text-align:center">
            <div class="illust">
                <div class="illust-circle">
                    <div class="illust-inner green"><i class="bi bi-check-lg"></i></div>
                </div>
            </div>

            <table class="res-table">
                <tr>
                    <th><i class="bi bi-credit-card-2-front me-2"></i>PAN Number</th>
                    <td><span class="pan-show"><?php echo strtoupper(htmlspecialchars($resdata['data']['pan_number']??''));?></span></td>
                </tr>
                <tr>
                    <th><i class="bi bi-person-fill me-2"></i>Full Name</th>
                    <td class="name-show"><?php echo htmlspecialchars($resdata['data']['name']??'N/A');?></td>
                </tr>
                <tr>
                    <th><i class="bi bi-phone-fill me-2"></i>Mobile Number</th>
                    <td style="font-family:'Space Grotesk',monospace;letter-spacing:1.5px">+91 <?php echo htmlspecialchars($resdata['data']['mobile']??$mobile);?></td>
                </tr>
                <?php if(!empty($resdata['data']['reference_id'])):?>
                <tr>
                    <th><i class="bi bi-upc-scan me-2"></i>Reference ID</th>
                    <td><?php echo htmlspecialchars($resdata['data']['reference_id']);?></td>
                </tr>
                <?php endif;?>
                <?php if(!empty($resdata['data']['timestamp'])):?>
                <tr>
                    <th><i class="bi bi-clock me-2"></i>Fetched At</th>
                    <td><?php echo date('d M Y h:i A', strtotime($resdata['data']['timestamp']));?></td>
                </tr>
                <?php endif;?>
            </table>

            <div class="app-tag"><i class="bi bi-hash"></i> Application: <?php echo htmlspecialchars($application_no??'');?></div>
            <div style="margin-top:12px"><div class="fee-tag" style="justify-content:center"><i class="bi bi-cash-stack"></i><span>Deducted:</span><strong>&#8377;<?php echo $fee;?></strong></div></div>

            <div class="res-acts">
                <button class="btn-main green" onclick="printBill()"><i class="bi bi-printer-fill"></i> Print</button>
                <a href="instant_pan_list" class="nav-pill"><i class="bi bi-clock-history"></i> History</a>
                <button class="nav-pill" onclick="newSearch()"><i class="bi bi-arrow-repeat"></i> New Search</button>
            </div>
        </div>
    </div>
    <?php endif;?>

    <div class="ft">Powered by <strong>PARI PRINT v2.0</strong> &middot; Developed by <strong>LALA WEB DEVELOPER</strong></div>
</div>
</div>

<script>
// Input validations
const mi = document.getElementById('mobileInput');
if(mi) {
    mi.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0,10);
    });
}

const ni = document.getElementById('nameInput');
if(ni) {
    ni.addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z\s]/g, '');
    });
}

function newSearch(){
    window.location.href = window.location.pathname;
}

function printBill(){
    const ps = document.getElementById('printSection');
    if(!ps) return;
    const w = window.open('', '_blank', 'width=600,height=700');
    w.document.write(`<!DOCTYPE html><html><head><title>PAN Details</title><style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Segoe UI',sans-serif;padding:32px;color:#1e293b}
    .h{text-align:center;margin-bottom:28px;padding-bottom:18px;border-bottom:2px solid #e2e8f0}
    .h h1{font-size:24px;color:#0f172a}.h p{color:#64748b;font-size:13px;margin-top:4px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:14px 18px;text-align:left;border-bottom:1px solid #f1f5f9;font-size:14px}
    th{color:#64748b;font-weight:600;width:40%}
    .pn{font-size:24px;font-weight:800;color:#6366f1;letter-spacing:3px;font-family:'Courier New',monospace}
    .nm{font-size:18px;font-weight:700}
    .ft{text-align:center;margin-top:28px;padding-top:18px;border-top:2px solid #e2e8f0;color:#94a3b8;font-size:11px}
    </style></head><body>
    <div class="h"><h1>PAN Card Details</h1><p>${new Date().toLocaleString('en-IN')}</p></div>
    ${ps.querySelector('.res-table').outerHTML}
    <div class="ft">Powered by PARI PRINT v2.0 &middot; Developed by LALA WEB DEVELOPER</div>
    </body></html>`);
    w.document.close();
    w.print();
}
</script>

<?php include('footer.php');?>
</body>
</html>