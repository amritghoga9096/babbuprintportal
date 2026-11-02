<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_recover_member_common.php');
checkSession();

if (!ensureSamagraRecoverMemberTable($ahk_conn)) {
    die('Unable to initialize Recover Member requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $familyId = trim($_POST['family_id'] ?? '');
    $memberId = trim($_POST['member_id'] ?? '');

    if ($fullName === '' || strlen($fullName) > 150) {
        $pageAlert = ['Invalid Full Name', 'Please enter a valid full name.', 'error'];
    } elseif ($familyId === '' || strlen($familyId) > 50) {
        $pageAlert = ['Invalid Family ID', 'Please enter a valid family ID.', 'error'];
    } elseif ($memberId === '' || strlen($memberId) > 50) {
        $pageAlert = ['Invalid Member ID', 'Please enter a valid member ID.', 'error'];
    } else {
        $phone = (string) $udata['phone'];
        $userId = (int) ($udata['id'] ?? 0);
        $applicationNo = samagraRecoverMemberApplicationNumber();
        $fee = SAMAGRA_RECOVER_MEMBER_FEE;
        $uploadDirectory = __DIR__ . '/uploads/samagra_recover_member/user_docs/';
        $savedFile = null;
        $transactionStarted = false;

        try {
            $memberAadharPdf = samagraRecoverMemberUploadPdf('member_aadhar_pdf', $uploadDirectory);
            $savedFile = $uploadDirectory . $memberAadharPdf;

            mysqli_begin_transaction($ahk_conn);
            $transactionStarted = true;

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

            $pdfPath = 'uploads/samagra_recover_member/user_docs/' . $memberAadharPdf;
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_recover_member_requests
                (application_no, user_id, user_phone, full_name, family_id, member_id, member_aadhar_pdf, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'sisssssd', $applicationNo, $userId, $phone, $fullName, $familyId, $memberId, $pdfPath, $fee);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Request insert failed.');
            }
            mysqli_stmt_close($stmt);

            $purpose = 'Recover Member - ' . $memberId;
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                (userid, amount, balance, purpose, status, type)
                VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet history insert failed.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($ahk_conn);
            header('Location: samagra_recover_member_list.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            if ($transactionStarted) {
                mysqli_rollback($ahk_conn);
            }

            if ($savedFile && is_file($savedFile)) {
                @unlink($savedFile);
            }

            if ($e->getMessage() === 'LOW_BALANCE') {
                $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_RECOVER_MEMBER_FEE . '. Please recharge your wallet.', 'warning'];
            } else {
                error_log('Recover Member error: ' . $e->getMessage());
                $pageAlert = ['Request Failed', $e instanceof RuntimeException ? $e->getMessage() : 'Please try again. No amount has been deducted.', 'error'];
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.recover-shell{max-width:780px;margin:35px auto;padding:0 15px}.recover-card{background:#fff;border:1px solid #d7dce3;border-radius:6px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.recover-title{background:#3674b8;color:#fff;padding:20px;font-size:21px;font-weight:700}.recover-body{padding:24px}.recover-badge{display:inline-block;margin-bottom:20px;padding:8px 16px;border-radius:20px;background:#7161ef;color:#fff;font-size:14px;font-weight:700}.recover-label{font-size:18px;margin:0 0 10px}.recover-input{width:100%;height:46px;border:1px solid #c8cfdb;border-radius:5px;padding:0 15px;font-size:17px;margin-bottom:20px}.recover-file{display:block;width:100%;border:1px dashed #c8cfdb;border-radius:5px;padding:12px 15px;font-size:16px;margin-bottom:20px;background:#fafbfd}.recover-actions{display:flex;justify-content:space-between;align-items:center;gap:12px}.recover-fee{border:1px solid #d5dae2;border-radius:5px;padding:10px 16px;font-size:18px}.recover-submit{border:0;border-radius:5px;background:#3674b8;color:#fff;padding:11px 22px;font-size:18px}.recover-links{text-align:right;margin-bottom:12px}@media(max-width:520px){.recover-body{padding:18px}.recover-actions{align-items:stretch;flex-direction:column}.recover-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="recover-shell">
    <div class="recover-links"><a class="btn btn-outline-primary" href="samagra_recover_member_list.php">My Requests</a></div>
    <div class="recover-card">
        <div class="recover-title">RECOVER MEMBER</div>
        <form method="post" enctype="multipart/form-data" class="recover-body">
            <div class="recover-badge">DELIVERY TIME - 1-5 HOURS</div>
            <div class="recover-label">Full Name <span class="text-danger">*</span></div>
            <input class="recover-input" name="full_name" maxlength="150" placeholder="Enter Full Name" required>
            <div class="recover-label">Family ID <span class="text-danger">*</span></div>
            <input class="recover-input" name="family_id" maxlength="50" placeholder="Enter Family ID" required>
            <div class="recover-label">Member ID <span class="text-danger">*</span></div>
            <input class="recover-input" name="member_id" maxlength="50" placeholder="Enter Member ID" required>
            <div class="recover-label">Member Aadhar (Only PDF) <span class="text-danger">*</span></div>
            <input class="recover-file" type="file" name="member_aadhar_pdf" accept="application/pdf" required>
            <div class="recover-actions">
                <div class="recover-fee"><strong>Rs. <?php echo SAMAGRA_RECOVER_MEMBER_FEE; ?></strong></div>
                <button class="recover-submit" type="submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php
if (isset($pageAlert)) {
    samagraRecoverMemberAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
