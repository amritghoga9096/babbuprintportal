<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_new_family_id_common.php');
checkSession();

if (!ensureSamagraNewFamilyIdTable($ahk_conn)) {
    die('Unable to initialize Samagra New Family ID requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $referenceNumber = trim($_POST['reference_number'] ?? '');

    if ($fullName === '' || strlen($fullName) > 150) {
        $pageAlert = ['Invalid Full Name', 'Please enter a valid full name.', 'error'];
    } elseif ($referenceNumber === '' || strlen($referenceNumber) > 100) {
        $pageAlert = ['Invalid Reference Number', 'Please enter a valid reference number.', 'error'];
    } else {
        $phone = (string) $udata['phone'];
        $userId = (int) ($udata['id'] ?? 0);
        $applicationNo = samagraNewFamilyIdApplicationNumber();
        $fee = SAMAGRA_NEW_FAMILY_ID_FEE;

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

            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_new_family_id_requests
                (application_no, user_id, user_phone, full_name, reference_number, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'sisssd', $applicationNo, $userId, $phone, $fullName, $referenceNumber, $fee);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Request insert failed.');
            }
            mysqli_stmt_close($stmt);

            $purpose = 'Samagra New Family ID - ' . $referenceNumber;
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                (userid, amount, balance, purpose, status, type)
                VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet history insert failed.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($ahk_conn);
            header('Location: samagra_new_family_id_list.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($ahk_conn);
            if ($e->getMessage() === 'LOW_BALANCE') {
                $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_NEW_FAMILY_ID_FEE . '. Please recharge your wallet.', 'warning'];
            } else {
                error_log('Samagra New Family ID error: ' . $e->getMessage());
                $pageAlert = ['Request Failed', 'Please try again. No amount has been deducted.', 'error'];
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.family-shell{max-width:760px;margin:35px auto;padding:0 15px}.family-card{background:#fff;border:1px solid #d7dce3;border-radius:6px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.family-title{background:#3674b8;color:#fff;padding:20px;font-size:21px;font-weight:700}.family-body{padding:24px}.family-badge{display:inline-block;margin-bottom:20px;padding:8px 16px;border-radius:20px;background:#7161ef;color:#fff;font-size:14px;font-weight:700}.family-label{font-size:18px;margin:0 0 10px}.family-input{width:100%;height:46px;border:1px solid #c8cfdb;border-radius:5px;padding:0 15px;font-size:17px;margin-bottom:20px}.family-actions{display:flex;justify-content:space-between;align-items:center;gap:12px}.family-fee{border:1px solid #d5dae2;border-radius:5px;padding:10px 16px;font-size:18px}.family-submit{border:0;border-radius:5px;background:#3674b8;color:#fff;padding:11px 22px;font-size:18px}.family-links{text-align:right;margin-bottom:12px}@media(max-width:520px){.family-body{padding:18px}.family-actions{align-items:stretch;flex-direction:column}.family-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="family-shell">
    <div class="family-links"><a class="btn btn-outline-primary" href="samagra_new_family_id_list.php">My Requests</a></div>
    <div class="family-card">
        <div class="family-title">SAMAGRA NEW FAMILY ID Req</div>
        <form method="post" class="family-body">
            <div class="family-badge">DELIVERY TIME - 1-5 HOURS</div>
            <div class="family-label">Full Name <span class="text-danger">*</span></div>
            <input class="family-input" name="full_name" maxlength="150" placeholder="Enter Full Name" required>
            <div class="family-label">Reference Number <span class="text-danger">*</span></div>
            <input class="family-input" name="reference_number" maxlength="100" placeholder="Enter Reference Number Details" required>
            <div class="family-actions">
                <div class="family-fee"><strong>Rs. <?php echo SAMAGRA_NEW_FAMILY_ID_FEE; ?></strong></div>
                <button class="family-submit" type="submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php
if (isset($pageAlert)) {
    samagraNewFamilyIdAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
