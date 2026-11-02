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

 $price_query = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Aadhaar_To_Pan_find'");
 $price = mysqli_fetch_assoc($price_query);
 $fee = $price['price'] ?? 0;

if (isset($_POST['action']) && $_POST['action'] === "resend_otp") {
    $mobile = mysqli_real_escape_string($ahk_conn, $_POST['mobile_no']);
    $_SESSION['pan_find_mobile'] = $mobile;
    if (strlen($mobile) != 10 || !is_numeric($mobile)) {
        $otp_error = "Please enter a valid 10-digit Mobile Number";
    } else {
        $url = "https://apiadda.in/api_service_v5/pan_number_find.php?" . http_build_query([
            'api_key'=>$api_key,'secret_key'=>$secret_key,'action'=>'send_otp','phone_number'=>$mobile
        ]);
        $curl = curl_init();
        curl_setopt_array($curl,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_ENCODING=>"",
            CURLOPT_MAXREDIRS=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_CUSTOMREQUEST=>"GET"]);
        $response = curl_exec($curl); $curl_error = curl_error($curl); curl_close($curl);
        if ($curl_error) { $otp_error = "Connection Error: " . $curl_error; }
        else {
            $res = json_decode($response, true);
            if (isset($res['status']) && $res['status'] === "success") {
                $_SESSION['pan_find_uniq_id'] = $res['apiadda_uniq_id'];
                $apiadda_uniq_id = $res['apiadda_uniq_id']; $otp_sent = true; $step = 2;
            } else { $otp_error = $res['message'] ?? "Failed to send OTP. Try again."; }
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] === "send_otp") {
    $mobile = mysqli_real_escape_string($ahk_conn, $_POST['mobile_no']);
    $_SESSION['pan_find_mobile'] = $mobile;
    if (strlen($mobile) != 10 || !is_numeric($mobile)) {
        $otp_error = "Please enter a valid 10-digit Mobile Number";
    } else {
        $url = "https://apiadda.in/api_service_v5/pan_number_find.php?" . http_build_query([
            'api_key'=>$api_key,'secret_key'=>$secret_key,'action'=>'send_otp','phone_number'=>$mobile
        ]);
        $curl = curl_init();
        curl_setopt_array($curl,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_ENCODING=>"",
            CURLOPT_MAXREDIRS=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_CUSTOMREQUEST=>"GET"]);
        $response = curl_exec($curl); $curl_error = curl_error($curl); curl_close($curl);
        if ($curl_error) { $otp_error = "Connection Error: " . $curl_error; }
        else {
            $res = json_decode($response, true);
            if (isset($res['status']) && $res['status'] === "success") {
                $_SESSION['pan_find_uniq_id'] = $res['apiadda_uniq_id'];
                $apiadda_uniq_id = $res['apiadda_uniq_id']; $otp_sent = true; $step = 2;
            } else { $otp_error = $res['message'] ?? "Failed to send OTP. Try again."; }
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] === "verify_otp") {
    $mobile = isset($_SESSION['pan_find_mobile']) ? $_SESSION['pan_find_mobile'] : "";
    $otp = mysqli_real_escape_string($ahk_conn, $_POST['otp_code']);
    $apiadda_uniq_id = isset($_SESSION['pan_find_uniq_id']) ? $_SESSION['pan_find_uniq_id'] : "";
    if (empty($otp) || strlen($otp) < 4) { $otp_error = "Please enter the valid OTP"; $step = 2; $otp_sent = true; }
    elseif (empty($apiadda_uniq_id)) {
        $otp_error = "Session expired. Please send OTP again."; $step = 1;
        unset($_SESSION['pan_find_uniq_id']); unset($_SESSION['pan_find_mobile']);
    } else {
        $url = "https://apiadda.in/api_service_v5/pan_number_find.php?" . http_build_query([
            'api_key'=>$api_key,'secret_key'=>$secret_key,'action'=>'verify_and_fetch',
            'phone_number'=>$mobile,'otp'=>$otp,'apiadda_uniq_id'=>$apiadda_uniq_id
        ]);
        $curl = curl_init();
        curl_setopt_array($curl,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_ENCODING=>"",
            CURLOPT_MAXREDIRS=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_CUSTOMREQUEST=>"GET"]);
        $response = curl_exec($curl); $curl_error = curl_error($curl); curl_close($curl);
        if ($curl_error) { $otp_error = "Connection Error: " . $curl_error; $step = 2; $otp_sent = true; }
        else {
            $resdata = json_decode($response, true);
            if (isset($resdata['status_code']) && $resdata['status_code'] == "100") {
                $pan_number = mysqli_real_escape_string($ahk_conn, $resdata['data']['pan_number'] ?? '');
                $pan_name = mysqli_real_escape_string($ahk_conn, $resdata['data']['name'] ?? 'N/A');
                $message = $resdata['message'] ?? '';
                $application_no = "PANF" . rand(100000, 999999);
                $username = $udata['phone']; $appliedby = $udata['phone'];
                $wallet_amount = $udata['balance']; $debit_fee = $wallet_amount - $fee;
                if ($wallet_amount >= $fee) {
                    $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    mysqli_query($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$appliedby', '$fee', '$debit_fee', 'PAN Number Find', '1', 'Debit')");
                    if ($debit) {
                        $sql_insert = "INSERT INTO instantpan_find (application_no, username, aadhaar_no, pan_no, status, status_code, fee, old_balance, new_balance, message) VALUES ('$application_no', '$username', '$mobile', '$pan_number', '100', '" . mysqli_real_escape_string($ahk_conn, $response) . "', '$fee', '$wallet_amount', '$debit_fee', '$message')";
                        mysqli_query($ahk_conn, $sql_insert);
                    }
                    $pan_result = true; $step = 3;
                    unset($_SESSION['pan_find_uniq_id']); unset($_SESSION['pan_find_mobile']);
                } else { $otp_error = "Insufficient wallet balance. Please recharge."; $step = 2; $otp_sent = true; }
            } else {
                $otp_error = $resdata['message'] ?? "OTP verification failed."; $step = 2; $otp_sent = true;
                unset($_SESSION['pan_find_uniq_id']);
            }
        }
    }
}

if ($step === 1 && isset($_SESSION['pan_find_uniq_id']) && isset($_SESSION['pan_find_mobile'])) {
    $mobile = $_SESSION['pan_find_mobile']; $apiadda_uniq_id = $_SESSION['pan_find_uniq_id']; $otp_sent = true; $step = 2;
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

/* ---- Stepper ---- */
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
.if{width:100%;padding:15px 16px 15px 50px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;color:#0f172a;font-size:16px;font-family:'Space Grotesk',monospace;font-weight:600;letter-spacing:2px;transition:all .2s;outline:none}
.if::placeholder{color:#cbd5e1;font-family:'Plus Jakarta Sans',sans-serif;letter-spacing:0;font-weight:400;font-size:14px}
.if:focus{border-color:#6366f1;background:#fff;box-shadow:0 0 0 4px rgba(99,102,241,.08)}
.if:focus~.ii{color:#6366f1}

/* ---- OTP Boxes ---- */
.otp-wrap{display:flex;gap:14px;justify-content:center}
.otp-b{width:58px;height:66px;text-align:center;font-size:26px;font-weight:700;font-family:'Space Grotesk',monospace;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:16px;color:#0f172a;outline:none;transition:all .25s;caret-color:#6366f1}
.otp-b:focus{border-color:#6366f1;background:#fff;box-shadow:0 0 0 4px rgba(99,102,241,.08);transform:translateY(-3px)}

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

/* ---- Mobile Info ---- */
.mob-info{display:flex;align-items:center;gap:14px;padding:14px 18px;background:#fafafe;border:1.5px solid #ede9fe;border-radius:14px;margin-bottom:24px}
.mob-info i{font-size:22px;color:#8b5cf6}
.mob-info .ml{font-size:12px;color:#94a3b8;font-weight:500}
.mob-info .mn{font-family:'Space Grotesk',monospace;font-size:17px;font-weight:700;color:#0f172a;letter-spacing:1.5px}
.mob-info .chg{margin-left:auto;background:#fff;border:1.5px solid #e2e8f0;color:#64748b;padding:7px 14px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s}
.mob-info .chg:hover{border-color:#8b5cf6;color:#8b5cf6}

/* ---- Fee Tag ---- */
.fee-tag{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:50px;margin-top:18px}
.fee-tag i{color:#f59e0b;font-size:16px}
.fee-tag span{font-size:13px;color:#78716c;font-weight:500}
.fee-tag strong{color:#d97706;font-size:17px;font-weight:800}

/* ---- Resend ---- */
.resend{text-align:center;margin-top:16px}
.resend button{background:none;border:none;color:#94a3b8;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;transition:color .2s}
.resend button:hover:not(:disabled){color:#6366f1;text-decoration:underline}
.resend button:disabled{cursor:not-allowed;opacity:.5}

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
    .otp-b{width:52px;height:58px;font-size:22px;border-radius:14px}
    .pan-show{font-size:20px;letter-spacing:2px}
    .res-acts{flex-direction:column}
    .res-acts .nav-pill,.res-acts .btn-main.green{width:100%;justify-content:center}
}
@media(max-width:400px){
    .otp-wrap{gap:10px}
    .otp-b{width:48px;height:54px;font-size:20px}
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

    <!-- Stepper -->
    <div class="stepper">
        <div class="stp <?php echo $step>1?'done':($step===1?'active':'') ?>">
            <div class="stp-dot"><?php if($step>1):?><i class="bi bi-check-lg"></i><?php else:?>1<?php endif;?></div>
            <div class="stp-text">Mobile</div>
        </div>
        <div class="stp-line <?php echo $step>1?'fill':'' ?>"></div>
        <div class="stp <?php echo $step>2?'done':($step===2?'active':'') ?>">
            <div class="stp-dot"><?php if($step>2):?><i class="bi bi-check-lg"></i><?php else:?>2<?php endif;?></div>
            <div class="stp-text">OTP</div>
        </div>
        <div class="stp-line <?php echo $step>2?'fill':'' ?>"></div>
        <div class="stp <?php echo $step>=3?'active':'' ?>">
            <div class="stp-dot">3</div>
            <div class="stp-text">Result</div>
        </div>
    </div>

    <!-- ====== STEP 1 ====== -->
    <?php if($step===1):?>
    <div class="main-card slide-up">
        <div class="card-top">
            <div class="card-ico blue"><i class="bi bi-phone"></i></div>
            <div><h2>Mobile Number</h2><p>Linked with your PAN card</p></div>
        </div>
        <div class="card-body">
            <div class="illust">
                <div class="illust-circle">
                    <div class="illust-inner blue"><i class="bi bi-phone"></i></div>
                </div>
            </div>

            <?php if(!empty($otp_error)):?>
            <div class="err"><i class="bi bi-exclamation-triangle-fill"></i><p><?php echo htmlspecialchars($otp_error);?></p></div>
            <?php endif;?>

            <form method="POST" id="step1Form">
                <input type="hidden" name="action" value="send_otp">
                <div class="fg">
                    <label class="fl">10-Digit Mobile Number <span class="req">*</span></label>
                    <div class="iw">
                        <input type="text" name="mobile_no" class="if" id="mobileInput" placeholder="Enter mobile number" maxlength="10" required pattern="[0-9]{10}" value="<?php echo htmlspecialchars($mobile);?>">
                        <i class="bi bi-phone ii"></i>
                    </div>
                </div>
                <button type="submit" class="btn-main blue"><i class="bi bi-send-fill"></i> Send OTP</button>
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

    <!-- ====== STEP 2 ====== -->
    <?php if($step===2):?>
    <div class="main-card slide-up">
        <div class="card-top">
            <div class="card-ico violet"><i class="bi bi-shield-lock"></i></div>
            <div><h2>Verify OTP</h2><p>Sent to your mobile number</p></div>
        </div>
        <div class="card-body">
            <div class="illust">
                <div class="illust-circle">
                    <div class="illust-inner violet"><i class="bi bi-shield-lock"></i></div>
                </div>
            </div>

            <div class="mob-info">
                <i class="bi bi-phone-fill"></i>
                <div><div class="ml">OTP sent to</div><div class="mn">+91 <?php echo htmlspecialchars($mobile);?></div></div>
                <button class="chg" onclick="goToStep1()">Change</button>
            </div>

            <?php if(!empty($otp_error)):?>
            <div class="err"><i class="bi bi-exclamation-triangle-fill"></i><p><?php echo htmlspecialchars($otp_error);?></p></div>
            <?php endif;?>

            <form method="POST" id="step2Form">
                <input type="hidden" name="action" value="verify_otp">
                <div class="fg" style="text-align:center">
                    <label class="fl" style="text-align:center">Enter 4-Digit OTP <span class="req">*</span></label>
                    <div class="otp-wrap">
                        <input type="text" class="otp-b" name="otp[]" maxlength="1" required inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="otp-b" name="otp[]" maxlength="1" required inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="otp-b" name="otp[]" maxlength="1" required inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="otp-b" name="otp[]" maxlength="1" required inputmode="numeric" pattern="[0-9]">
                    </div>
                </div>
                <button type="submit" class="btn-main violet"><i class="bi bi-patch-check-fill"></i> Verify & Find PAN</button>
                <div class="resend">
                    <button type="button" id="resendBtn" disabled onclick="resendOTP()">Resend OTP in <span id="resendTimer">30</span>s</button>
                </div>
            </form>
            <div class="fee-tag" style="justify-content:center"><i class="bi bi-shield-fill-check"></i><span>Charged only on successful fetch</span></div>
        </div>
    </div>
    <?php endif;?>

    <!-- ====== STEP 3 ====== -->
    <?php if($step===3 && $pan_result && !empty($resdata['data'])):?>
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
                    <th><i class="bi bi-calendar3 me-2"></i>Date of Birth</th>
                    <td><?php
                        $dob=$resdata['data']['dob']??'';
                        if(!empty($dob)){$d=DateTime::createFromFormat('Y-m-d',$dob);echo $d?$d->format('d M Y'):htmlspecialchars($dob);}
                        else echo 'N/A';
                    ?></td>
                </tr>
                <tr>
                    <th><i class="bi bi-gender-ambiguous me-2"></i>Gender</th>
                    <td><?php
                        $g=strtoupper($resdata['data']['gender']??'');
                        if($g==='M') echo '<span class="gen-tag m"><i class="bi bi-gender-male"></i> Male</span>';
                        elseif($g==='F') echo '<span class="gen-tag f"><i class="bi bi-gender-female"></i> Female</span>';
                        else echo htmlspecialchars($g?:'N/A');
                    ?></td>
                </tr>
                <tr>
                    <th><i class="bi bi-phone-fill me-2"></i>Mobile</th>
                    <td style="font-family:'Space Grotesk',monospace;letter-spacing:1.5px">+91 <?php echo htmlspecialchars($resdata['data']['mobile']??$mobile);?></td>
                </tr>
            </table>

            <div class="app-tag"><i class="bi bi-hash"></i><?php echo htmlspecialchars($application_no??'');?></div>
            <div style="margin-top:12px"><div class="fee-tag" style="justify-content:center"><i class="bi bi-cash-stack"></i><span>Deducted:</span><strong>&#8377;<?php echo $fee;?></strong></div></div>

            <div class="res-acts">
                <button class="btn-main green" onclick="printBill()"><i class="bi bi-printer-fill"></i> Print</button>
                <a href="instant_pan_list" class="nav-pill"><i class="bi bi-clock-history"></i> History</a>
                <button class="nav-pill" onclick="newSearch()"><i class="bi bi-arrow-repeat"></i> New Search</button>
            </div>
        </div>
    </div>
    <?php endif;?>

    <div class="ft">Powered by <strong>goodprintportal.in</strong> &middot; Developed by <strong>LALA WEB DEVELOPER</strong></div>
</div>
</div>

<script>
document.querySelectorAll('.otp-b').forEach((b,i,a)=>{
    b.addEventListener('input',e=>{e.target.value=e.target.value.replace(/[^0-9]/g,'');if(e.target.value&&i<a.length-1)a[i+1].focus()});
    b.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!e.target.value&&i>0)a[i-1].focus()});
    b.addEventListener('paste',e=>{e.preventDefault();const p=e.clipboardData.getData('text').replace(/[^0-9]/g,'').slice(0,4);p.split('').forEach((c,j)=>{if(a[j])a[j].value=c});const n=Array.from(a).findIndex(x=>!x.value);(n!==-1?a[n]:a[a.length-1]).focus()})
});
const mi=document.getElementById('mobileInput');if(mi)mi.addEventListener('input',function(){this.value=this.value.replace(/[^0-9]/g,'')});

let ti;function startRT(){let s=30;const b=document.getElementById('resendBtn'),sp=document.getElementById('resendTimer');if(!b||!sp)return;b.disabled=true;sp.textContent=s;ti=setInterval(()=>{s--;sp.textContent=s;if(s<=0){clearInterval(ti);b.disabled=false;b.innerHTML='Resend OTP'}},1000)}
<?php if($step===2&&$otp_sent):?>startRT();<?php endif;?>

function resendOTP(){const m="<?php echo htmlspecialchars($mobile);?>";const f=document.createElement('form');f.method='POST';f.innerHTML='<input type="hidden" name="action" value="resend_otp"><input type="hidden" name="mobile_no" value="'+m+'">';document.body.appendChild(f);f.submit()}
function goToStep1(){const f=document.createElement('form');f.method='POST';f.action=window.location.pathname;f.innerHTML='<input type="hidden" name="reset_step" value="1">';document.body.appendChild(f);f.submit()}
function newSearch(){window.location.href=window.location.pathname}

function printBill(){
    const ps=document.getElementById('printSection');if(!ps)return;
    const w=window.open('','_blank','width=600,height=700');
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
    <div class="ft">Powered by PARI PRINT &middot; Developed by LALA WEB DEVELOPER</div>
    </body></html>`);
    w.document.close();w.print()
}

const s2f=document.getElementById('step2Form');
if(s2f)s2f.addEventListener('submit',function(){let o='';document.querySelectorAll('.otp-b').forEach(b=>o+=b.value);const old=this.querySelector('input[name="otp_code"]');if(old)old.remove();const h=document.createElement('input');h.type='hidden';h.name='otp_code';h.value=o;this.appendChild(h)});

<?php if(isset($_POST['reset_step'])){unset($_SESSION['pan_find_uniq_id']);unset($_SESSION['pan_find_mobile']);echo"window.location.href=window.location.pathname;";}?>
</script>

<?php include('footer.php');?>
</body>
</html>