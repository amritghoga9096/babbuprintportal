<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_pdf_common.php');
checkSession();

if (!ensureSamagraPdfTable($ahk_conn)) {
    die('Unable to initialize Samagra PDF requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $samagraId = preg_replace('/\D+/', '', $_POST['samagra_id'] ?? '');

    if (!preg_match('/^\d{8,9}$/', $samagraId)) {
        $pageAlert = ['Invalid Samagra ID', 'Please enter an 8 digit Family ID or 9 digit Member ID.', 'error'];
    } else {
        $phone = (string) ($udata['phone'] ?? '');
        $userId = (int) ($udata['id'] ?? 0);

        if ($phone === '' || $userId === 0) {
            $pageAlert = ['Session Error', 'User session nahi mila. Dobara login karein.', 'error'];
        } else {
            $idType = strlen($samagraId) === 8 ? 'family' : 'member';
            $requestNo = samagraRequestNumber();
            $fee = SAMAGRA_PDF_FEE;

            try {
                if (!mysqli_begin_transaction($ahk_conn)) {
                    throw new Exception('MySQL Transaction Start Failed: ' . mysqli_error($ahk_conn));
                }

                $stmt = mysqli_prepare($ahk_conn, "SELECT balance FROM users WHERE phone=? FOR UPDATE");
                if (!$stmt) {
                    throw new Exception('MySQL Prepare Failed (SELECT): ' . mysqli_error($ahk_conn));
                }
                mysqli_stmt_bind_param($stmt, 's', $phone);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('MySQL Execute Failed (SELECT): ' . mysqli_stmt_error($stmt));
                }
                $user = db_stmt_fetch_assoc($stmt);
                mysqli_stmt_close($stmt);

                if (!$user || (float) $user['balance'] < $fee) {
                    throw new Exception('LOW_BALANCE');
                }

                $newBalance = (float) $user['balance'] - $fee;
                $stmt = mysqli_prepare($ahk_conn, "UPDATE users SET balance=? WHERE phone=?");
                if (!$stmt) {
                    throw new Exception('MySQL Prepare Failed (UPDATE users): ' . mysqli_error($ahk_conn));
                }
                mysqli_stmt_bind_param($stmt, 'ds', $newBalance, $phone);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('MySQL Execute Failed (UPDATE users): ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_pdf_requests
                    (request_no, user_id, user_phone, samagra_id, id_type, amount, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                if (!$stmt) {
                    throw new Exception('MySQL Prepare Failed (INSERT samagra): ' . mysqli_error($ahk_conn));
                }
                mysqli_stmt_bind_param($stmt, 'sisssd', $requestNo, $userId, $phone, $samagraId, $idType, $fee);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('MySQL Execute Failed (INSERT samagra): ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                $purpose = 'Samagra PDF Without OTP - ' . $samagraId;
                $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                    (userid, amount, balance, purpose, status, type)
                    VALUES (?, ?, ?, ?, '1', 'Debit')");
                if (!$stmt) {
                    throw new Exception('MySQL Prepare Failed (INSERT wallethistory): ' . mysqli_error($ahk_conn));
                }
                mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('MySQL Execute Failed (INSERT wallethistory): ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                mysqli_commit($ahk_conn);
                header('Location: samagra_pdf_list.php?submitted=1');
                exit;
            } catch (Throwable $e) {
                if (isset($ahk_conn) && $ahk_conn) {
                    @mysqli_rollback($ahk_conn);
                }
                $errMsg = $e->getMessage();
                error_log('Samagra PDF request error: ' . $errMsg . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
                if ($errMsg === 'LOW_BALANCE') {
                    $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_PDF_FEE . '. Please recharge your wallet.', 'warning'];
                } else {
                    $debugInfo = '(Debug: ' . $errMsg . ')';
                    $pageAlert = ['Request Failed', 'Please try again. No amount has been deducted. ' . $debugInfo, 'error'];
                }
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.samagra-shell{max-width:760px;margin:35px auto;padding:0 15px}.samagra-card{background:#fff;border:1px solid #d7dce3;border-radius:6px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.samagra-title{background:#3674b8;color:#fff;padding:20px;font-size:22px;font-weight:700}.samagra-body{padding:24px}.samagra-note{background:linear-gradient(100deg,#657cf2,#7a43a8);color:#fff;border-radius:28px;padding:12px 20px;font-size:16px;font-weight:700;box-shadow:0 7px 14px rgba(92,75,180,.25);margin-bottom:35px}.samagra-label{font-size:18px;margin-bottom:10px}.samagra-input{width:100%;height:46px;border:1px solid #c8cfdb;border-radius:5px;padding:0 15px;font-size:17px}.samagra-help{color:#c95697;margin-top:6px}.samagra-actions{display:flex;justify-content:space-between;align-items:center;margin-top:24px;gap:12px}.samagra-fee{border:1px solid #d5dae2;border-radius:5px;padding:10px 16px;font-size:18px}.samagra-submit{border:0;border-radius:5px;background:#3674b8;color:#fff;padding:11px 22px;font-size:18px}.samagra-links{text-align:right;margin-bottom:12px}@media(max-width:520px){.samagra-title{font-size:18px}.samagra-body{padding:18px}.samagra-actions{align-items:stretch;flex-direction:column}.samagra-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="samagra-shell">
    <div class="samagra-links"><a class="btn btn-outline-primary" href="samagra_pdf_list.php">My Requests</a></div>
    <div class="samagra-card">
        <div class="samagra-title">SAMAGRA PDF / MANUAL REQUEST</div>
        <form method="post" class="samagra-body">
            <div class="samagra-note">8 digit Family ID or 9 digit Member ID enter karein. Request admin ko bheji jayegi.</div>
            <div class="samagra-label">Samagra ID Number <span class="text-danger">*</span></div>
            <input class="samagra-input" name="samagra_id" inputmode="numeric" maxlength="9" pattern="\d{8,9}" placeholder="Enter 8 Digit Family ID OR 9 Digit Member ID" required>
            <div class="samagra-help">8 Digit Family ID = Request, 9 Digit Member ID = Request</div>
            <div class="samagra-actions">
                <div class="samagra-fee"><strong>Rs. <?php echo SAMAGRA_PDF_FEE; ?></strong> / Request</div>
                <button class="samagra-submit" type="submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php
if (isset($pageAlert)) {
    samagraAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
