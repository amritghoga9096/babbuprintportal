<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_new_apply_common.php');
checkSession();

if (!ensureSamagraNewApplyTable($ahk_conn)) {
    die('Unable to initialize Samagra New Apply requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicantName = trim($_POST['applicant_name'] ?? '');
    $requestId = trim($_POST['request_id'] ?? '');

    if ($applicantName === '' || strlen($applicantName) > 150) {
        $pageAlert = ['Invalid Applicant Name', 'Please enter a valid applicant name.', 'error'];
    } elseif ($requestId === '' || strlen($requestId) > 100) {
        $pageAlert = ['Invalid Request ID', 'Please enter a valid Request ID.', 'error'];
    } else {
        $phone = (string) $udata['phone'];
        $userId = (int) ($udata['id'] ?? 0);
        $applicationNo = samagraNewApplicationNumber();
        $fee = SAMAGRA_NEW_APPLY_FEE;

        mysqli_begin_transaction($ahk_conn);
        try {
            $stmt = mysqli_prepare($ahk_conn, "SELECT balance FROM users WHERE phone=? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 's', $phone);
            mysqli_stmt_execute($stmt);
            $user = db_stmt_fetch_assoc($stmt);
            mysqli_stmt_close($stmt);

            if (!$user || (float) $user['balance'] < $fee) {
                throw new Exception('LOW_BALANCE');
            }

            $newBalance = (float) $user['balance'] - $fee;
            $stmt = mysqli_prepare($ahk_conn, "UPDATE users SET balance=? WHERE phone=?");
            mysqli_stmt_bind_param($stmt, 'ds', $newBalance, $phone);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet update failed.');
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_new_apply_requests
                (application_no, user_id, user_phone, applicant_name, request_id, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'sisssd', $applicationNo, $userId, $phone, $applicantName, $requestId, $fee);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Request insert failed.');
            }
            mysqli_stmt_close($stmt);

            $purpose = 'Samagra New Apply - ' . $requestId;
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                (userid, amount, balance, purpose, status, type)
                VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet history insert failed.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($ahk_conn);
            header('Location: samagra_new_apply_list.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($ahk_conn);
            if ($e->getMessage() === 'LOW_BALANCE') {
                $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_NEW_APPLY_FEE . '. Please recharge your wallet.', 'warning'];
            } else {
                error_log('Samagra New Apply error: ' . $e->getMessage());
                $pageAlert = ['Request Failed', 'Please try again. No amount has been deducted.', 'error'];
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.new-shell{max-width:760px;margin:35px auto;padding:0 15px}.new-card{background:#fff;border:1px solid #d7dce3;border-radius:6px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.new-title{background:#3674b8;color:#fff;padding:20px;font-size:21px;font-weight:700}.new-body{padding:24px}.new-label{font-size:18px;margin:0 0 10px}.new-input{width:100%;height:46px;border:1px solid #c8cfdb;border-radius:5px;padding:0 15px;font-size:17px;margin-bottom:20px}.new-actions{display:flex;justify-content:space-between;align-items:center;gap:12px}.new-fee{border:1px solid #d5dae2;border-radius:5px;padding:10px 16px;font-size:18px}.new-submit{border:0;border-radius:5px;background:#3674b8;color:#fff;padding:11px 22px;font-size:18px}.new-links{text-align:right;margin-bottom:12px}@media(max-width:520px){.new-body{padding:18px}.new-actions{align-items:stretch;flex-direction:column}.new-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="new-shell">
    <div class="new-links"><a class="btn btn-outline-primary" href="samagra_new_apply_list.php">My Applications</a></div>
    <div class="new-card">
        <div class="new-title">SAMAGRAH-NEW</div>
        <form method="post" class="new-body">
            <div class="new-label">Applicant Name <span class="text-danger">*</span></div>
            <input class="new-input" name="applicant_name" maxlength="150" placeholder="Enter Applicant Name" required>
            <div class="new-label">Request Id <span class="text-danger">*</span></div>
            <input class="new-input" name="request_id" maxlength="100" placeholder="Enter Request Id" required>
            <div class="new-actions">
                <div class="new-fee"><strong>Rs. <?php echo SAMAGRA_NEW_APPLY_FEE; ?></strong></div>
                <button class="new-submit" type="submit">Apply Data</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php
if (isset($pageAlert)) {
    samagraNewAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
