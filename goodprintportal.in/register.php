<?php
// 1. Start Session at the very top
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('./includes/config.php');

/* ---------------- GATEWAY CONFIGURATION ---------------- */
$CENTRAL_GATEWAY_BASE_REG = $GLOBALS['CENTRAL_GATEWAY_BASE'] ?? (function_exists('get_central_gateway_domain') ? rtrim(get_central_gateway_domain(), '/') : 'https://gateway.pdfgods.in');
$apnapayhere_url = rtrim($CENTRAL_GATEWAY_BASE_REG, '/') . '/api';
$apnapayhere_token = $webdata['nsdl_api_key'] ?? ($webdata['api_key'] ?? "166eab75e32c7ac8c7c3693aa46ae521");

/* ---------------- HELPER FUNCTIONS ---------------- */
function renderSwalAndExit($title, $message, $icon = 'success', $redirectUrl = null, $asHtml = false) {
    $safeTitle = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
    $safeRedirect = $redirectUrl !== null ? (string)$redirectUrl : '';
    // Auto-append welcome=1 if redirecting to admin dashboard (popup trigger, no cookie/header dependency)
    if ($safeRedirect !== '' && strpos($safeRedirect, 'admin') !== false && strpos($safeRedirect, 'welcome=') === false) {
        $sep = (strpos($safeRedirect, '?') === false) ? '?' : '&';
        $safeRedirect = rtrim($safeRedirect, '/') . '/index.php' . $sep . 'welcome=1';
    }
    $safeRedirectEnc = htmlspecialchars($safeRedirect, ENT_QUOTES, 'UTF-8');

    if ($asHtml) {
        $payload = json_encode((string)$message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $msgField = "html: {$payload}";
    } else {
        $payload = json_encode((string)$message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $msgField = "text: {$payload}";
    }

    $safeIcon = htmlspecialchars((string)$icon, ENT_QUOTES, 'UTF-8');

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $safeTitle . '</title>';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
    echo '</head><body>';
    echo '<script>';
    echo "Swal.fire({title: " . json_encode($safeTitle) . ", {$msgField}, icon: " . json_encode($safeIcon) . "})";
    if ($redirectUrl !== null && $redirectUrl !== '') {
        echo ".then(() => { window.location.href = " . json_encode($safeRedirect) . "; })";
    }
    echo ';';
    echo '</script>';
    echo '</body></html>';
    exit;
}

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

if (isset($_GET['verify_payment'])) {
    $order_id = '';
    if (!empty($_GET['order_id'])) {
        $order_id = mysqli_real_escape_string($ahk_conn, $_GET['order_id']);
    } elseif (!empty($_SESSION['reg_order_id'])) {
        $order_id = mysqli_real_escape_string($ahk_conn, $_SESSION['reg_order_id']);
    }

    if ($order_id === '') {
        $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Verification Failed', 'html' => 'Missing order id.'];
        header("Location: " . $current_url);
        exit();
    }

    $stmt = mysqli_prepare($ahk_conn, "SELECT phone, email, status FROM wallet WHERE txn_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $order_id);
    mysqli_stmt_execute($stmt);
    $txn_row = db_stmt_fetch_assoc($stmt);
    mysqli_stmt_close($stmt);

    if (!$txn_row) {
        $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Verification Failed', 'html' => 'Invalid order id.'];
        header("Location: " . $current_url);
        exit();
    }

    if ($txn_row['status'] === 'success') {
        $_SESSION['swal_msg'] = [
            'type' => 'success',
            'title' => 'Payment Already Verified',
            'html' => 'Your payment is already marked as successful. Please login to continue.'
        ];
        header("Location: " . $current_url);
        exit();
    }

    if ($txn_row['status'] !== 'pending') {
        $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Failed', 'html' => 'This order is already rejected/cancelled.'];
        header("Location: " . $current_url);
        exit();
    }

    $response = apnapayhere_check_status($apnapayhere_url, $apnapayhere_token, $order_id);
    $isSuccess = false;
    $result = $response['result'] ?? [];
    if (
        (isset($response['status']) && $response['status'] === 'COMPLETED' && isset($result['status']) && $result['status'] === 'SUCCESS')
        || (isset($response['status']) && $response['status'] === true && isset($result['status']) && $result['status'] === 'SUCCESS')
    ) {
        $isSuccess = true;
    }

    if ($isSuccess) {
        $txn_phone = $txn_row['phone'];
        $txnAmount = (string)($result['amount'] ?? '');
        $utr = (string)($result['utr'] ?? '');
        $datetime = (string)($result['date'] ?? date('Y-m-d H:i:s'));
        $respMsg = 'ApnaPayHere';

        $stmt_w = mysqli_prepare($ahk_conn, "UPDATE wallet SET status='success', BANKTXNID=?, RESPMSG=?, txn_date=?, amount=? WHERE txn_id=? AND status='pending' LIMIT 1");
        mysqli_stmt_bind_param($stmt_w, "sssss", $utr, $respMsg, $datetime, $txnAmount, $order_id);
        mysqli_stmt_execute($stmt_w);
        mysqli_stmt_close($stmt_w);

        $stmt_u = mysqli_prepare($ahk_conn, "UPDATE users SET status='1' WHERE phone=?");
        mysqli_stmt_bind_param($stmt_u, "s", $txn_phone);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // 🔥 REFERRAL BONUS — Paid Registration Fee = first deposit, so give bonus now.
        if (function_exists('gpGiveReferralBonusIfEligible')) {
            gpGiveReferralBonusIfEligible($txn_phone, $ahk_conn);
        }

        $display_pass = "********";
        $cookie_name = "reg_pass_" . $txn_phone;
        if (isset($_COOKIE[$cookie_name])) {
            $display_pass = $_COOKIE[$cookie_name];
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

        unset($_SESSION['reg_order_id']);

        // ===== AUTO-LOGIN AFTER PAYMENT SUCCESS =====
        $_SESSION['phone'] = $txn_phone;
        $_SESSION['name']  = $u_name;
        $login_time = date('Y-m-d H:i:s');
        $login_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $popup_nonce = bin2hex(random_bytes(16)) . '_' . uniqid('', true);

        // Fetch user id for event log
        $_urow = null; $_urid = 0;
        $_ures = @mysqli_query($ahk_conn, "SELECT id FROM users WHERE phone='".mysqli_real_escape_string($ahk_conn,$txn_phone)."' LIMIT 1");
        if ($_ures && ($_urow = mysqli_fetch_assoc($_ures))) $_urid = (int)($_urow['id'] ?? 0);

        // ===== 🔥 POPUP LOGIN EVENT
        $_evid = 0;
        if ($_urid > 0) {
            @mysqli_query($ahk_conn, "INSERT INTO login_popup_events (user_id,login_ip,created_at) VALUES (".$_urid.",'".mysqli_real_escape_string($ahk_conn,$login_ip)."','".mysqli_real_escape_string($ahk_conn,$login_time)."')");
            $_evid = (int)mysqli_insert_id($ahk_conn);
            if ($_evid <= 0) { $_i2 = @mysqli_query($ahk_conn, "SELECT id FROM login_popup_events WHERE user_id=".$_urid." ORDER BY id DESC LIMIT 1"); if ($_i2 && ($_r2 = mysqli_fetch_assoc($_i2))) $_evid = (int)$_r2['id']; }
        }
        $evSql = $_urid > 0 && $_evid > 0 ? ", last_popup_event_id=".(int)$_evid : "";
        @mysqli_query($ahk_conn, "UPDATE users SET last_login='".mysqli_real_escape_string($ahk_conn,$login_time)."', login_ip='".mysqli_real_escape_string($ahk_conn,$login_ip)."', popup_login_nonce='".mysqli_real_escape_string($ahk_conn,$popup_nonce)."'".$evSql." WHERE phone='".mysqli_real_escape_string($ahk_conn,$txn_phone)."' LIMIT 1");

        // 🔥🔥🔥 SIMPLE COOKIE TRIGGER
        if (!headers_sent()) {
            $_COOKIE['gp_popup_show_1'] = '1';
            setcookie('gp_popup_show_1', '1', [ 'expires' => time() + 3600, 'path' => '/', 'samesite' => 'Lax' ]);
        }
        $_SESSION['gp_popup_show_1'] = true;

        $_SESSION['swal_msg'] = [
            'type' => 'success',
            'title' => 'Payment Successful!',
            'html' => "🎉 Welcome {$u_name}!<br><b>User ID:</b> {$txn_phone}<br><b>Password:</b> {$display_pass}<br><small>Redirecting to dashboard...</small>",
            'redirect' => 'admin/'
        ];
        header("Location: " . $current_url);
        exit();
    }

    $reason = $response['message'] ?? 'Payment verification failed';
    $reasonStr = (string)$reason;
    $stmt_f = mysqli_prepare($ahk_conn, "UPDATE wallet SET status='rejected', RESPMSG=? WHERE txn_id=? AND status='pending' LIMIT 1");
    mysqli_stmt_bind_param($stmt_f, "ss", $reasonStr, $order_id);
    mysqli_stmt_execute($stmt_f);
    mysqli_stmt_close($stmt_f);

    $_SESSION['swal_msg'] = ['type' => 'error', 'title' => 'Payment Failed', 'html' => 'If payment was deducted, contact support!'];
    header("Location: " . $current_url);
    exit();
}

/* ---------------- REFERRAL CODE ---------------- */
 $ref_code = '';
if (!empty($_GET['ref'])) {
    $_gpRawRef = (string)$_GET['ref'];
    // Handle both: standard base64 (+ / =) AND URL-safe base64 (- _ no padding)
    $_gpRawRef = str_replace(['-','_'], ['+','/'], $_gpRawRef);
    $_gpPad = strlen($_gpRawRef) % 4;
    if ($_gpPad) $_gpRawRef .= substr('====', $_gpPad);
    $_gpDec = @base64_decode($_gpRawRef, true);
    if ($_gpDec !== false && $_gpDec !== '') {
        $ref_code = trim((string)$_gpDec);
        // Sanity: phone number hi hona chahiye (digits me, max 15 chars)
        if (!preg_match('/^[0-9+\-\s]{6,20}$/', $ref_code)) $ref_code = '';
    }
    unset($_gpRawRef, $_gpDec, $_gpPad);
    // Keep old behavior as fallback if above yielded empty
    if ($ref_code === '') {
        $ref_code = @base64_decode(mysqli_real_escape_string($ahk_conn, $_GET['ref']));
        if (!is_string($ref_code)) $ref_code = '';
    }
    if ($ref_code !== '') {
        $ref_code = mysqli_real_escape_string($ahk_conn, trim($ref_code));
    }
}

/* ---------------- FORM SUBMIT ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['phone'])) {

    $name     = mysqli_real_escape_string($ahk_conn, $_POST['name']);
    $email    = mysqli_real_escape_string($ahk_conn, $_POST['email']);
    $phone    = mysqli_real_escape_string($ahk_conn, $_POST['phone']);
    $usertype = mysqli_real_escape_string($ahk_conn, $_POST['usertype']);
    $password = $_POST['password']; // Plain text password

    $order_id = "REG" . date("YmdHis") . rand(1000,99999);
    $date     = date("Y-m-d");

    if (strcasecmp($usertype, "admin") === 0) {
        renderSwalAndExit('Access Denied!', 'Invalid registration type.', 'error', 'register.php');
    }

    $check = mysqli_query($ahk_conn, "SELECT id FROM users WHERE phone='$phone' OR email='$email'");
    if (mysqli_num_rows($check) > 0) {
        renderSwalAndExit('Oops!', 'Email or phone already exists!', 'error', 'register.php');
    }

    $vpass = password_hash($password, PASSWORD_DEFAULT);

    if ($usertype === "retailer") {
        $amount = (int)$webdata['ret_reg_fee'];
    } elseif ($usertype === "distributor") {
        $amount = (int)$webdata['dist_reg_fee'];
    } else {
        $amount = 0; 
    }

    $isFree = ($amount == 0);

    if ($isFree) {
        $res = mysqli_query($ahk_conn, "INSERT INTO users (name, phone, email, password, type, status, referred_by, order_id, date) VALUES ('$name', '$phone', '$email', '$vpass', '$usertype', '1', '$ref_code', '$order_id', '$date')");

        if ($res) {
            // NOTE: FREE users ke liye referral bonus ABHI nahi diya — pehli wallet DEPOSIT ke baad diya jayega.
            // isliye yahaan referal insert REMOVE kar diya.

            // WhatsApp for Free Reg
            if (!empty($phone)) {
                $message = "🎉 Welcome to {$websiteName}!\n\nDear {$name},\n\nYour account is created.\n\n📱 Mobile: {$phone}\n🔑 Password: {$password}\n\n— Support Team";
                $msg = urlencode($message);
                $api_url = "https://{$whatsappurl}/send-message?api_key={$whapikey}&sender={$whsender}&number=91{$phone}&message={$msg}";
                $ch = curl_init();
                curl_setopt_array($ch, [CURLOPT_URL => $api_url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
                curl_exec($ch);
                curl_close($ch);
            }

            // ===== AUTO-LOGIN AFTER FREE REGISTRATION =====
            $_SESSION['phone'] = $phone;
            $_SESSION['name']  = $name;
            $login_time = date('Y-m-d H:i:s');
            $login_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
            $popup_nonce = bin2hex(random_bytes(16)) . '_' . uniqid('', true);

            $_urid_free = 0;
            $_ufree = @mysqli_query($ahk_conn, "SELECT id FROM users WHERE phone='".mysqli_real_escape_string($ahk_conn,$phone)."' LIMIT 1");
            if ($_ufree && ($_ufr = mysqli_fetch_assoc($_ufree))) $_urid_free = (int)($_ufr['id'] ?? 0);

            // ===== 🔥 POPUP LOGIN EVENT
            $_evid_free = 0;
            if ($_urid_free > 0) {
                @mysqli_query($ahk_conn, "INSERT INTO login_popup_events (user_id,login_ip,created_at) VALUES (".$_urid_free.",'".mysqli_real_escape_string($ahk_conn,$login_ip)."','".mysqli_real_escape_string($ahk_conn,$login_time)."')");
                $_evid_free = (int)mysqli_insert_id($ahk_conn);
                if ($_evid_free <= 0) { $_i3 = @mysqli_query($ahk_conn, "SELECT id FROM login_popup_events WHERE user_id=".$_urid_free." ORDER BY id DESC LIMIT 1"); if ($_i3 && ($_r3 = mysqli_fetch_assoc($_i3))) $_evid_free = (int)$_r3['id']; }
            }
            $evSqlFree = $_urid_free > 0 && $_evid_free > 0 ? ", last_popup_event_id=".(int)$_evid_free : "";
            @mysqli_query($ahk_conn, "UPDATE users SET last_login='".mysqli_real_escape_string($ahk_conn,$login_time)."', login_ip='".mysqli_real_escape_string($ahk_conn,$login_ip)."', popup_login_nonce='".mysqli_real_escape_string($ahk_conn,$popup_nonce)."'".$evSqlFree." WHERE phone='".mysqli_real_escape_string($ahk_conn,$phone)."' LIMIT 1");

            // 🔥🔥🔥 SIMPLE COOKIE TRIGGER
            if (!headers_sent()) {
                $_COOKIE['gp_popup_show_1'] = '1';
                setcookie('gp_popup_show_1', '1', [ 'expires' => time() + 3600, 'path' => '/', 'samesite' => 'Lax' ]);
            }
            $_SESSION['gp_popup_show_1'] = true;

            renderSwalAndExit('Registration Successful!', "🎉 Welcome {$name}! Redirecting to dashboard...", 'success', 'admin/');
        }
        exit;
    }

    /* ---------------- PAID REGISTRATION ---------------- */
    
    // SET COOKIE HERE: Store password in cookie for 10 minutes (600 seconds)
    // This allows us to retrieve it after payment callback
    setcookie("reg_pass_" . $phone, $password, time() + 600, "/");

    // Insert User (Inactive)
    $res = mysqli_query($ahk_conn, "INSERT INTO users (name, phone, email, password, type, status, referred_by, order_id, date) VALUES ('$name', '$phone', '$email', '$vpass', '$usertype', '0', '$ref_code', '$order_id', '$date')");

    if (!$res) {
        renderSwalAndExit('Error!', 'Database insertion failed!', 'error', 'register.php');
    }

    // NOTE: PAID users ke liye referral bonus ABHI nahi diya — payment SUCCESS hone par (verify_payment block me) diya jayega.
    // isliye yahaan referal insert REMOVE kar diya.

    // Insert Pending Wallet Transaction
    $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallet (phone, amount, txn_id, email, status, date, PAYMENTMODE) VALUES (?, ?, ?, ?, 'pending', ?, 'REGFEE')");
    mysqli_stmt_bind_param($stmt, "sdsss", $phone, $amount, $order_id, $email, $date);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['reg_order_id'] = $order_id;

    $redirect_url = $current_url . "?verify_payment=1&order_id=" . $order_id;
    $request = [
        "customer_mobile" => $phone,
        "user_token"      => $apnapayhere_token,
        "amount"          => $amount,
        "order_id"        => $order_id,
        "redirect_url"    => $redirect_url,
        "remark1"         => "Registration Fee",
        "remark2"         => $phone
    ];

    $response = apnapayhere_create_order($apnapayhere_url, $request);

    if (isset($response['status']) && $response['status'] === true && isset($response['result']['payment_url'])) {
        $payment_url = $response['result']['payment_url'];
        header("Location: " . $payment_url);
        exit();
    }

    $err_msg = htmlspecialchars($response['message'] ?? 'Gateway Error');
    renderSwalAndExit('Error!', 'Payment Gateway Error: ' . $err_msg, 'error', 'register.php');
}

// Include Template (Contains HTML Head & SweetAlert Library)
include('./template/ahkweb/authentication-signup.php');

// ---------------- DISPLAY SWEETALERT MESSAGE ---------------- 
if (isset($_SESSION['swal_msg'])) {
    $msg = $_SESSION['swal_msg'];
    unset($_SESSION['swal_msg']);
    $html = $msg['html'] ?? null;
    $text = $msg['text'] ?? null;
    $msgLine = $html !== null ? "html: " . json_encode((string)$html) . "," : "text: " . json_encode((string)($text ?? '')) . ",";
    $redirectUrl = !empty($msg['redirect']) ? (string)$msg['redirect'] : 'admin/';
    // Append welcome=1 trigger IF redirecting to admin dashboard (login auto-success)
    if (strpos($redirectUrl, 'admin') !== false) {
        $sep = (strpos($redirectUrl, '?') === false) ? '?' : '&';
        if (strpos($redirectUrl, 'welcome=') === false) $redirectUrl = rtrim($redirectUrl, '/') . '/index.php' . $sep . 'welcome=1';
    }
    echo "
    <script>
        Swal.fire({
            title: '{$msg['title']}',
            {$msgLine}
            icon: '{$msg['type']}',
            timer: 500,
            showConfirmButton: false,
            timerProgressBar: false
        }).then(() => {
            window.location.href = '{$redirectUrl}';
        });
    </script>";
}
?>
