<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_member_delete_common.php');
checkSession();

if (!ensureSamagraMemberDeleteTable($ahk_conn)) {
    die('Unable to initialize Samagra Member Delete requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $familyId = trim($_POST['family_id'] ?? '');
    $applicantName = trim($_POST['applicant_name'] ?? '');
    $memberId = trim($_POST['member_id'] ?? '');

    if ($familyId === '' || strlen($familyId) > 50) {
        $pageAlert = ['Invalid Family ID', 'Please enter a valid Family ID.', 'error'];
    } elseif ($applicantName === '' || strlen($applicantName) > 150) {
        $pageAlert = ['Invalid Applicant Name', 'Please enter a valid applicant name.', 'error'];
    } elseif ($memberId === '' || strlen($memberId) > 50) {
        $pageAlert = ['Invalid Sadasya ID', 'Please enter a valid Sadasya ID.', 'error'];
    } else {
        $phone = (string) $udata['phone'];
        $userId = (int) ($udata['id'] ?? 0);
        $applicationNo = samagraMemberDeleteNumber();
        $fee = SAMAGRA_MEMBER_DELETE_FEE;

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

            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_member_delete_requests
                (application_no, user_id, user_phone, family_id, applicant_name, member_id, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'sissssd', $applicationNo, $userId, $phone, $familyId, $applicantName, $memberId, $fee);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Request insert failed.');
            }
            mysqli_stmt_close($stmt);

            $purpose = 'Samagra Member Delete - ' . $memberId;
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                (userid, amount, balance, purpose, status, type)
                VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet history insert failed.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($ahk_conn);
            header('Location: samagra_member_delete_list.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($ahk_conn);
            if ($e->getMessage() === 'LOW_BALANCE') {
                $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_MEMBER_DELETE_FEE . '. Please recharge your wallet.', 'warning'];
            } else {
                error_log('Samagra Member Delete error: ' . $e->getMessage());
                $pageAlert = ['Request Failed', 'Please try again. No amount has been deducted.', 'error'];
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.delete-shell{max-width:760px;margin:35px auto;padding:0 15px}.delete-card{background:#fff;border:1px solid #d7dce3;border-radius:6px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.delete-title{background:#3674b8;color:#fff;padding:20px;font-size:21px;font-weight:700}.delete-body{padding:24px}.delete-label{font-size:18px;margin:0 0 10px}.delete-input{width:100%;height:46px;border:1px solid #c8cfdb;border-radius:5px;padding:0 15px;font-size:17px;margin-bottom:20px}.delete-actions{display:flex;justify-content:space-between;align-items:center;gap:12px}.delete-fee{border:1px solid #d5dae2;border-radius:5px;padding:10px 16px;font-size:18px}.delete-submit{border:0;border-radius:5px;background:#3674b8;color:#fff;padding:11px 22px;font-size:18px}.delete-links{text-align:right;margin-bottom:12px}@media(max-width:520px){.delete-body{padding:18px}.delete-actions{align-items:stretch;flex-direction:column}.delete-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="delete-shell">
    <div class="delete-links"><a class="btn btn-outline-primary" href="samagra_member_delete_list.php">My Requests</a></div>
    <div class="delete-card">
        <div class="delete-title">SAMAGARH-MEMBER-DELETE</div>
        <form method="post" class="delete-body">
            <div class="delete-label">Family Id <span class="text-danger">*</span></div>
            <input class="delete-input" name="family_id" maxlength="50" placeholder="Enter Family Id" required>
            <div class="delete-label">Applicant Name <span class="text-danger">*</span></div>
            <input class="delete-input" name="applicant_name" maxlength="150" placeholder="Enter Applicant Name" required>
            <div class="delete-label">Sadasya Id <span class="text-danger">*</span></div>
            <input class="delete-input" name="member_id" maxlength="50" placeholder="Enter Sadasya Id" required>
            <div class="delete-actions">
                <div class="delete-fee"><strong>Rs. <?php echo SAMAGRA_MEMBER_DELETE_FEE; ?></strong></div>
                <button class="delete-submit" type="submit">Apply Data</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php
if (isset($pageAlert)) {
    samagraMemberDeleteAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
