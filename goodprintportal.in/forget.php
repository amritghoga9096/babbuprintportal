<?php
ob_start();
session_start();
include('./includes/config.php');

function normalizeForgotName($value) {
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    return mb_strtolower($value, 'UTF-8');
}

function normalizeForgotEmail($value) {
    return mb_strtolower(trim((string) $value), 'UTF-8');
}

function normalizeForgotPhone($value) {
    return preg_replace('/\D+/', '', (string) $value);
}

function sendWhatsAppMessage($mobile, $message) {
    global $whatsappurl, $whapikey;

    if (empty($mobile) || empty($message)) {
        return false;
    }

    $recipient = '91' . preg_replace('/\D+/', '', $mobile);
    $apiUrl = "https://$whatsappurl/api/send-text";
    $params = http_build_query([
        'api_key' => $whapikey,
        'number' => $recipient,
        'message' => $message,
    ]);

    $finalUrl = $apiUrl . '?' . $params;
    $ch = curl_init($finalUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return false;
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    return isset($decoded['status']) && $decoded['status'] === true;
}

function sendConfirmation($name, $mobile) {
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $message = "🌟 *DEAR* _" . strtoupper($name) . "_ 🌟\n\n" .
        "🔐 *Password Reset Successful!*\n\n" .
        "✅ Your password has been changed successfully.\n\n" .
        "📞 *Mobile:* $mobile\n\n" .
        "🔗 *Login:* https://$domain/login.php\n\n" .
        "🤝 *Online Services*";

    sendWhatsAppMessage($mobile, $message);
}

$step = (isset($_SESSION['reset_user_id']) && (int) $_SESSION['reset_user_id'] > 0) ? 2 : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_user'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    if ($name === '' || $phone === '' || $email === '') {
        $_SESSION['error_msg'] = 'Please enter your full name, registered phone number and email.';
    } else {
        $phoneDigits = normalizeForgotPhone($phone);
        $nameNormalized = normalizeForgotName($name);
        $phoneNormalized = normalizeForgotPhone($phone);
        $emailNormalized = normalizeForgotEmail($email);

        $stmt = mysqli_prepare($ahk_conn, 'SELECT id, name, phone, email FROM users WHERE phone=? OR email=? OR LOWER(name)=? LIMIT 50');
        if ($stmt) {
            $phoneParam = $phoneDigits;
            $emailParam = normalizeForgotEmail($email);
            $nameParam = normalizeForgotName($name);

            mysqli_stmt_bind_param($stmt, 'sss', $phoneParam, $emailParam, $nameParam);
            mysqli_stmt_execute($stmt);
            $results = db_stmt_fetch_all_assoc($stmt);
            mysqli_stmt_close($stmt);

            $matchedUser = null;
            foreach ($results as $user) {
                if (
                    normalizeForgotPhone($user['phone']) === $phoneNormalized &&
                    normalizeForgotEmail($user['email']) === $emailNormalized &&
                    normalizeForgotName($user['name']) === $nameNormalized
                ) {
                    $matchedUser = $user;
                    break;
                }
            }

            if ($matchedUser) {
                $_SESSION['reset_user_id'] = (int) $matchedUser['id'];
                $_SESSION['reset_user_name'] = $matchedUser['name'];
                $_SESSION['reset_user_phone'] = $matchedUser['phone'];
                $_SESSION['success_msg'] = 'Identity verified. Please set a new password.';
                $step = 2;
            } else {
                $_SESSION['error_msg'] = 'The entered name, phone number and email do not match our records.';
            }
        } else {
            $_SESSION['error_msg'] = 'Unable to verify your details right now. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);
    $userId = $_SESSION['reset_user_id'] ?? 0;

    if (!$userId) {
        $_SESSION['error_msg'] = 'Your session has expired. Please verify your details again.';
        $step = 1;
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['error_msg'] = 'Passwords do not match.';
        $step = 2;
    } elseif (strlen($newPassword) < 6) {
        $_SESSION['error_msg'] = 'Password must be at least 6 characters long.';
        $step = 2;
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($ahk_conn, 'UPDATE users SET password=? WHERE id=?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $hashed, $userId);
            if (mysqli_stmt_execute($stmt)) {
                sendConfirmation($_SESSION['reset_user_name'] ?? 'User', $_SESSION['reset_user_phone'] ?? '');
                $_SESSION['success_msg'] = 'Password reset successfully! Please login with your new password.';
                $_SESSION['redirect'] = 'login.php';

                unset($_SESSION['reset_user_id'], $_SESSION['reset_user_name'], $_SESSION['reset_user_phone']);
                $step = 1;
            } else {
                $_SESSION['error_msg'] = 'Password reset failed. Please try again.';
                $step = 2;
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_msg'] = 'Database error. Please try again.';
            $step = 2;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Forgot Password | <?php echo ahkweb('webname'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="index, follow">
    <meta name="copyright" content="Print Portal, https://flex4upoint.site/">
    <meta name="author" content="FleXSecure Web Official Private Limited">
    <meta name="description" content="FleXSecure Web Official - PAN Online Services">
    <meta name="google-site-verification" content="l5mrMD-ugyR9mh-46yg0wUnY18dVCRYubZSfFbPsesc" />
    <meta property="og:type" content="website">
    <meta property="og:title" content="PRINT PORTAL - Aadhar Print Portal, Login, PVC Card, Smart Card Online">
    <meta property="og:description" content="PRINT PORTAL | AADHAAR PRINT | DIGITAL FAST PRINT | ONLINE PVC AADHAAR SMART CARD">
    <meta property="og:url" content="https://flex4upoint.site/">

    <link rel="icon" href="/logof.png" type="image/png">
    <link rel="stylesheet" href="../assets/LOGINPAGES/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/LOGINPAGES/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/LOGINPAGES/font/flaticon.css">
    <link rel="stylesheet" href="../assets/LOGINPAGES/style.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <style>
        @media (max-width: 767px) { #myDiv { display: none; } }
        @media (min-width: 767px) { #myD { display: none; } }
    </style>
</head>
<body>
    <div id="ldr"></div>
    <div id="preloader" class="preloader">
        <div class='inner'>
            <div class='line1'></div>
            <div class='line2'></div>
            <div class='line3'></div>
        </div>
    </div>

    <section class="fxt-template-animation fxt-template-layout34 sendDiv" data-bg-image="../assets/LOGINPAGES/img/elements/bg1.png">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 mt-5">
                    <div class="fxt-form p-4 shadow bg-white rounded">
                        <div class="text-center mb-4">
                            <img src="../admin/uploads/1.png" width="100" alt="Logo">
                            <h4>Forgot Password</h4>
                            <p>Enter your registered name, phone number and email.</p>
                        </div>

                        <?php if ($step === 1): ?>
                            <form method="POST">
                                <input type="text" name="name" required placeholder="Registered Name" class="form-control mb-3">
                                <input type="text" name="phone" required placeholder="Registered Phone" class="form-control mb-3">
                                <input type="email" name="email" required placeholder="Registered Email" class="form-control mb-3">
                                <button type="submit" name="verify_user" class="btn btn-primary btn-block">Verify Details</button>
                            </form>
                        <?php else: ?>
                            <form method="POST">
                                <input type="password" name="new_password" required placeholder="New Password" class="form-control mb-2">
                                <input type="password" name="confirm_password" required placeholder="Confirm Password" class="form-control mb-3">
                                <button type="submit" class="btn btn-success btn-block">Reset Password</button>
                            </form>
                        <?php endif; ?>

                        <div class="mt-3 text-center">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/LOGINPAGES/js/bootstrap.min.js"></script>
    <script src="../assets/LOGINPAGES/js/imagesloaded.pkgd.min.js"></script>
    <script src="../assets/LOGINPAGES/js/validator.min.js"></script>
    <script src="../assets/LOGINPAGES/js/main.js"></script>
    <script>
        window.addEventListener('load', function () {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.style.display = 'none';
            }
        });
    </script>

    <script>
        <?php if (isset($_SESSION['error_msg'])): ?>
            swal("Error", "<?php echo addslashes($_SESSION['error_msg']); ?>", "error");
            <?php unset($_SESSION['error_msg']); ?>
        <?php elseif (isset($_SESSION['success_msg'])): ?>
            swal("Success", "<?php echo addslashes($_SESSION['success_msg']); ?>", "success")
            <?php if (!empty($_SESSION['redirect'])): ?>
                .then(() => {
                    window.location.href = "<?php echo $_SESSION['redirect']; ?>";
                });
                <?php unset($_SESSION['redirect']); ?>
            <?php else: ?>;
            <?php endif; ?>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
