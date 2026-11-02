<?php
// 1. Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('./includes/config.php');

/* ---------------- GATEWAY CONFIGURATION ---------------- */
$CENTRAL_GATEWAY_BASE_ACT = $GLOBALS['CENTRAL_GATEWAY_BASE'] ?? (function_exists('get_central_gateway_domain') ? rtrim(get_central_gateway_domain(), '/') : 'https://gateway.pdfgods.in');
$apnapayhere_url = rtrim($CENTRAL_GATEWAY_BASE_ACT, '/') . '/api';
$apnapayhere_token = $webdata['nsdl_api_key'] ?? ($webdata['api_key'] ?? "166eab75e32c7ac8c7c3693aa46ae521");

/* ---------------- HELPER FUNCTIONS ---------------- */
function http_request_post($url, $postData) {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}

function apnapayhere_create_order($apnapayhere_url, $request) {
    return http_request_post(rtrim($apnapayhere_url, '/') . "/create-order", $request);
}

function apnapayhere_check_status($apnapayhere_url, $token, $order_id) {
    return http_request_post(rtrim($apnapayhere_url, '/') . "/check-order-status", [
        "user_token" => $token,
        "order_id"   => $order_id
    ]);
}

// Dynamic Callback URL
 $socket = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
 $current_url = $socket . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

/* ---------------- CALLBACK HANDLER (Payment Return) ---------------- */
if (isset($_GET['verify_payment'])) {
    $order_id = '';
    if (!empty($_GET['order_id'])) {
        $order_id = mysqli_real_escape_string($ahk_conn, $_GET['order_id']);
    } elseif (!empty($_SESSION['activate_order_id'])) {
        $order_id = mysqli_real_escape_string($ahk_conn, $_SESSION['activate_order_id']);
    }

    if ($order_id === '') {
        $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Verification Failed', 'text' => 'Missing order id.'];
        header("Location: activate.php");
        exit();
    }

    $stmt = mysqli_prepare($ahk_conn, "SELECT * FROM wallet WHERE txn_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $order_id);
    mysqli_stmt_execute($stmt);
    $txn_row = db_stmt_fetch_assoc($stmt);
    mysqli_stmt_close($stmt);

    if (!$txn_row) {
        $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Verification Failed', 'text' => 'Invalid order id.'];
        header("Location: activate.php");
        exit();
    }

    if ($txn_row['status'] === 'success') {
        $_SESSION['swal_msg'] = ['type' => 'success', 'title' => 'Already Activated', 'text' => 'Account is already active. Please login directly.'];
        header("Location: login.php");
        exit();
    }

    if ($txn_row['status'] !== 'pending') {
        $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Failed', 'text' => 'This order is already cancelled/rejected.'];
        header("Location: activate.php");
        exit();
    }

    $response = apnapayhere_check_status($apnapayhere_url, $apnapayhere_token, $order_id);
    $result = $response['result'] ?? [];
    $isSuccess = (
        (isset($response['status']) && $response['status'] === 'COMPLETED' && isset($result['status']) && $result['status'] === 'SUCCESS')
        || (isset($response['status']) && $response['status'] === true && isset($result['status']) && $result['status'] === 'SUCCESS')
    );

    if ($isSuccess) {
        $txn_phone = $txn_row['phone'];
        $txnAmount = (string)($result['amount'] ?? $txn_row['amount']);
        $utr = (string)($result['utr'] ?? '');
        $datetime = (string)($result['date'] ?? date('Y-m-d H:i:s'));
        $paymentMode = 'ApnaPayHere';

        $stmt_w = mysqli_prepare($ahk_conn, "UPDATE wallet SET status='success', BANKTXNID=?, RESPMSG=?, PAYMENTMODE=?, txn_date=?, amount=? WHERE txn_id=? AND status='pending' LIMIT 1");
        mysqli_stmt_bind_param($stmt_w, "ssssss", $utr, $paymentMode, $paymentMode, $datetime, $txnAmount, $order_id);
        mysqli_stmt_execute($stmt_w);
        mysqli_stmt_close($stmt_w);

        $stmt_u = mysqli_prepare($ahk_conn, "UPDATE users SET status='1' WHERE phone=?");
        mysqli_stmt_bind_param($stmt_u, "s", $txn_phone);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // 🔥 REFERRAL BONUS — Activation payment = first deposit, so give bonus now.
        if (function_exists('gpGiveReferralBonusIfEligible')) {
            gpGiveReferralBonusIfEligible($txn_phone, $ahk_conn);
        }

        $display_pass = "********";
        $cookie_name = "reg_pass_" . $txn_phone;
        if (isset($_COOKIE[$cookie_name])) {
            $new_pass_plain = $_COOKIE[$cookie_name];
            $display_pass = $new_pass_plain;
            $new_hash = password_hash($new_pass_plain, PASSWORD_DEFAULT);
            $stmt_p = mysqli_prepare($ahk_conn, "UPDATE users SET password=? WHERE phone=?");
            mysqli_stmt_bind_param($stmt_p, "ss", $new_hash, $txn_phone);
            mysqli_stmt_execute($stmt_p);
            mysqli_stmt_close($stmt_p);
            setcookie($cookie_name, "", time() - 3600, "/");
        }

        if (!empty($txn_phone)) {
            $uq = mysqli_query($ahk_conn, "SELECT name, type FROM users WHERE phone='$txn_phone'");
            $udata = mysqli_fetch_assoc($uq);
            $u_name = $udata['name'] ?? 'User';
            $u_type = $udata['type'] ?? 'Member';

            $message = "🎉 Welcome to {$websiteName}!\n\nDear {$u_name},\n\nYour {$u_type} account has been activated.\n\n📱 Login ID: {$txn_phone}\n🔑 Password: {$display_pass}\n\nPlease keep your details safe.\n\n— Support Team";
            $msg = urlencode($message);
            $api_url = "https://{$whatsappurl}/send-message?api_key={$whapikey}&sender={$whsender}&number=91{$txn_phone}&message={$msg}";

            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_URL => $api_url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
            curl_exec($ch);
            curl_close($ch);
        }

        unset($_SESSION['activate_order_id']);

        $_SESSION['swal_msg'] = [
            'type' => 'success',
            'title' => 'Payment Successful!',
            'html' => "<b>User ID:</b> $txn_phone<br><b>Password:</b> $display_pass"
        ];
        header("Location: activate.php");
        exit();
    }

    $reason = (string)($response['message'] ?? 'Payment verification failed');
    $stmt_f = mysqli_prepare($ahk_conn, "UPDATE wallet SET status='rejected', RESPMSG=? WHERE txn_id=? AND status='pending' LIMIT 1");
    mysqli_stmt_bind_param($stmt_f, "ss", $reason, $order_id);
    mysqli_stmt_execute($stmt_f);
    mysqli_stmt_close($stmt_f);

    $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Failed', 'text' => 'If payment was deducted, contact support!'];
    header("Location: activate.php");
    exit();
}

/* ---------------- STEP 2: INITIATE PAYMENT ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_payment_btn'])) {
    
    $phone    = mysqli_real_escape_string($ahk_conn, $_POST['phone']);
    $new_pass = $_POST['new_password']; // Plain text

    // Fetch User Data
    $check_q = mysqli_query($ahk_conn, "SELECT * FROM users WHERE phone='$phone'");
    if (mysqli_num_rows($check_q) > 0) {
        $user_data = mysqli_fetch_assoc($check_q);
        $email = $user_data['email'];
        $usertype = $user_data['type'];

        // Double check status before payment
        if ($user_data['status'] == '1') {
            $_SESSION['swal_msg'] = ['type' => 'info', 'title' => 'Already Active', 'text' => 'Please login directly.'];
            header("Location: activate.php");
            exit();
        }

        // Calculate Amount
        if ($usertype === "retailer") {
            $amount = (int)$webdata['ret_reg_fee'];
        } elseif ($usertype === "distributor") {
            $amount = (int)$webdata['dist_reg_fee'];
        } else {
            $amount = 0;
        }

        if ($amount == 0) {
            // Free Activation Logic
            mysqli_query($ahk_conn, "UPDATE users SET status='1' WHERE phone='$phone'");
             if(!empty($new_pass)){
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                mysqli_query($ahk_conn, "UPDATE users SET password='$hash' WHERE phone='$phone'");
            }
            $_SESSION['swal_msg'] = ['type' => 'success', 'title' => 'Activated!', 'text' => 'Account activated successfully.'];
            header("Location: activate.php");
            exit();
        }

        // Generate a FRESH Order ID for every attempt
        $order_id = "ACT" . date("YmdHis") . rand(1000,99999);
        $date = date("Y-m-d");

        // Check if a Pending Transaction already exists for this user
        $pending_q = mysqli_query($ahk_conn, "SELECT * FROM wallet WHERE phone='$phone' AND status='pending' LIMIT 1");
        
        if (mysqli_num_rows($pending_q) > 0) {
            // If pending found, UPDATE the existing row with NEW Order ID and Amount
            $stmt_update = mysqli_prepare($ahk_conn, "UPDATE wallet SET txn_id=?, amount=?, date=? WHERE phone=? AND status='pending'");
            mysqli_stmt_bind_param($stmt_update, "sdss", $order_id, $amount, $date, $phone);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        } else {
            // If no pending, INSERT new row
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallet (phone, amount, txn_id, email, status, date, PAYMENTMODE) VALUES (?, ?, ?, ?, 'pending', ?, 'ACTIVATION')");
            mysqli_stmt_bind_param($stmt, "sdsss", $phone, $amount, $order_id, $email, $date);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Set Cookie for Password
        if (!empty($new_pass)) {
            setcookie("reg_pass_" . $phone, $new_pass, time() + 600, "/");
        }

        $_SESSION['activate_order_id'] = $order_id;

        // Call Gateway with the NEW Order ID
        $redirect_url = $current_url . "?verify_payment=1&order_id=" . $order_id;
        $request = [
            "customer_mobile" => $phone,
            "user_token"      => $apnapayhere_token,
            "amount"          => $amount,
            "order_id"        => $order_id,
            "redirect_url"    => $redirect_url,
            "remark1"         => "Account Activation",
            "remark2"         => $phone
        ];

        $response = apnapayhere_create_order($apnapayhere_url, $request);

        if (isset($response['status']) && $response['status'] === true && isset($response['result']['payment_url'])) {
            $payment_url = $response['result']['payment_url'];
            header("Location: " . $payment_url);
            exit();
        } else {
            $err_msg = htmlspecialchars($response['message'] ?? 'Gateway Error');
            $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Error', 'text' => $err_msg];
            header("Location: activate.php");
            exit();
        }
    }
}

/* ---------------- STEP 1: SEARCH USER (WITH VALIDATION) ---------------- */
 $found_user = null;
 $search_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_user_btn'])) {
    $search_phone = mysqli_real_escape_string($ahk_conn, $_POST['phone_search']);
    
    $stmt = mysqli_prepare($ahk_conn, "SELECT name, email, phone, type, status FROM users WHERE phone = ?");
    mysqli_stmt_bind_param($stmt, "s", $search_phone);
    mysqli_stmt_execute($stmt);
    $row = db_stmt_fetch_assoc($stmt);
    
    if ($row) {
        
        // VALIDATION 1: Check User Type (Retailer or Distributor only)
        if ($row['type'] !== 'retailer' && $row['type'] !== 'distributor') {
            $search_error = "Invalid User Type! Only Retailer or Distributor can activate here.";
        } 
        // VALIDATION 2: Check if Already Active
        elseif ($row['status'] == '1') {
            $search_error = "Account is already active! Please login directly.";
        }
        else {
            // All checks passed: Valid Inactive User
            $found_user = $row;
        }

    } else {
        $search_error = "User not found in database!";
    }
    mysqli_stmt_close($stmt);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Activation | <?php echo $websiteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-card { max-width: 450px; margin: 50px auto; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; padding: 25px; }
        .card-body { padding: 30px; background: white; }
        .btn-custom { background: linear-gradient(to right, #667eea, #764ba2); border: none; color: white; font-weight: 600; }
        .btn-custom:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); color: white; }
    </style>
</head>
<body>

<div class="container">
    <div class="login-card">
        <div class="card-header">
            <h3 class="mb-0"><i class="fas fa-user-check me-2"></i>Account Activation</h3>
            <p class="mt-2 mb-0" style="font-size: 14px; opacity: 0.9;">Activate your account to continue</p>
        </div>
        <div class="card-body">

            <!-- Logic: If User Found, Show Activation Form, Else Show Search Form -->
            
            <?php if ($found_user): ?>
                <!-- STEP 2: ACTIVATION FORM -->
                <div class="text-center mb-4">
                    <i class="fas fa-user-circle fa-3x text-success mb-2"></i>
                    <h5 class="text-success">User Found</h5>
                </div>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label text-muted">Full Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($found_user['name']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($found_user['email']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">User Type</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($found_user['type']); ?>" readonly>
                    </div>

                    <input type="hidden" name="phone" value="<?php echo $found_user['phone']; ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Set New Password</label>
                        <input type="text" name="new_password" class="form-control form-control-lg" placeholder="Enter Password to Set" required>
                    </div>

                    <?php 
                        $fee = 0;
                        if ($found_user['type'] == 'retailer') $fee = $webdata['ret_reg_fee'];
                        elseif ($found_user['type'] == 'distributor') $fee = $webdata['dist_reg_fee'];
                    ?>

                    <div class="d-grid gap-2">
                        <button type="submit" name="activate_payment_btn" class="btn btn-success btn-lg">
                            <i class="fas fa-credit-card me-2"></i>Pay ₹<?php echo $fee; ?> & Activate
                        </button>
                        <a href="activate.php" class="btn btn-outline-secondary btn-sm mt-2">Search Another Number</a>
                    </div>
                </form>

            <?php else: ?>
                <!-- STEP 1: SEARCH FORM -->
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter Mobile Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                            <input type="text" name="phone_search" class="form-control form-control-lg" placeholder="Registered Mobile No" maxlength="10" required>
                        </div>
                        <?php if($search_error): ?>
                            <small class="text-danger mt-2 d-block"><i class="fas fa-exclamation-circle"></i> <?php echo $search_error; ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="search_user_btn" class="btn btn-custom btn-lg">
                            <i class="fas fa-search me-2"></i>Find Account
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
        <div class="card-footer text-center text-muted bg-light py-3">
            Already Active? <a href="login.php" class="text-decoration-none fw-bold">Login Here</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- SWEETALERT HANDLER -->
<?php 
if (isset($_SESSION['swal_msg'])) {
    $msg = $_SESSION['swal_msg'];
    unset($_SESSION['swal_msg']);
    $html = $msg['html'] ?? null;
    $text = $msg['text'] ?? null;
    $msgLine = $html !== null ? "html: " . json_encode((string)$html) . "," : "text: " . json_encode((string)($text ?? '')) . ",";
    echo "
    <script>
        Swal.fire({
            title: '{$msg['title']}',
            {$msgLine}
            icon: '{$msg['type']}'
        }).then(() => {
            window.location.href = '" . (($msg['type'] ?? '') === 'success' ? "login.php" : "activate.php") . "';
        });
    </script>";
}
?>
</body>
</html>
