<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_member_transfer_common.php');
checkSession();

if (!ensureSamagraMemberTransferTable($ahk_conn)) {
    die('Unable to initialize Samagra Member Transfer requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'husband_name' => 150,
        'husband_family_id' => 50,
        'husband_member_id' => 50,
        'wife_name' => 150,
        'wife_member_id' => 50,
        'wife_family_id' => 50
    ];
    $data = [];
    $invalid = false;
    foreach ($fields as $field => $maxLength) {
        $data[$field] = trim($_POST[$field] ?? '');
        if ($data[$field] === '' || strlen($data[$field]) > $maxLength) {
            $invalid = true;
        }
    }

    if ($invalid) {
        $pageAlert = ['Invalid Details', 'Please fill all transfer details correctly.', 'error'];
    } else {
        $phone = (string) $udata['phone'];
        $userId = (int) ($udata['id'] ?? 0);
        $applicationNo = samagraMemberTransferNumber();
        $fee = SAMAGRA_MEMBER_TRANSFER_FEE;

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

            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_member_transfer_requests
                (application_no, user_id, user_phone, husband_name, husband_family_id, husband_member_id,
                 wife_name, wife_member_id, wife_family_id, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param(
                $stmt,
                'sisssssssd',
                $applicationNo,
                $userId,
                $phone,
                $data['husband_name'],
                $data['husband_family_id'],
                $data['husband_member_id'],
                $data['wife_name'],
                $data['wife_member_id'],
                $data['wife_family_id'],
                $fee
            );
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Request insert failed.');
            }
            mysqli_stmt_close($stmt);

            $purpose = 'Samagra Member Transfer - ' . $data['wife_member_id'];
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                (userid, amount, balance, purpose, status, type)
                VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet history insert failed.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($ahk_conn);
            header('Location: samagra_member_transfer_list.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($ahk_conn);
            if ($e->getMessage() === 'LOW_BALANCE') {
                $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_MEMBER_TRANSFER_FEE . '. Please recharge your wallet.', 'warning'];
            } else {
                error_log('Samagra Member Transfer error: ' . $e->getMessage());
                $pageAlert = ['Request Failed', 'Please try again. No amount has been deducted.', 'error'];
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.transfer-shell{max-width:850px;margin:25px auto;padding:0 15px}.transfer-card{background:#fff;border:1px solid #d7dce3;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.transfer-title{background:#3674b8;color:#fff;padding:15px;font-size:16px;font-weight:700}.transfer-body{padding:15px}.transfer-note{background:#fffbea;border:1px solid #e5dcc0;padding:14px 18px;margin-bottom:15px;color:#bd8522}.transfer-note h5{color:#bd55a4}.transfer-warning{border-top:2px solid #bd55a4;background:#fff;padding:8px;margin-top:10px;color:#a04c8e}.transfer-label{display:block;margin:0 0 7px}.transfer-input{width:100%;height:40px;border:1px solid #c8cfdb;border-radius:4px;padding:0 12px;margin-bottom:13px}.transfer-actions{display:flex;justify-content:space-between;align-items:center;gap:12px}.transfer-fee{border:1px solid #d5dae2;border-radius:5px;padding:8px 13px;font-size:17px}.transfer-submit{border:0;border-radius:4px;background:#3674b8;color:#fff;padding:9px 17px}.transfer-links{text-align:right;margin-bottom:10px}@media(max-width:520px){.transfer-actions{align-items:stretch;flex-direction:column}.transfer-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="transfer-shell">
<div class="transfer-links"><a class="btn btn-outline-primary" href="samagra_member_transfer_list.php">My Requests</a></div>
<div class="transfer-card">
<div class="transfer-title">SAMAGARH TRANSFER</div>
<form method="post" class="transfer-body">
    <div class="transfer-note">
        <h5>विशेष अनुरोध</h5>
        <div>समग्र ID मायके से ससुराल ट्रांसफर करवाने से पहले कृपया सभी जानकारी सही भरें।</div>
        <ul class="mb-1">
            <li>लड़की की उम्र कम से कम 18 वर्ष होनी चाहिए।</li>
            <li>लड़के की उम्र कम से कम 21 वर्ष होनी चाहिए।</li>
            <li>समग्र e-KYC पूरी होनी चाहिए।</li>
            <li>लड़का और लड़की अविवाहित होने चाहिए।</li>
        </ul>
        <div class="transfer-warning">यदि उपरोक्त सभी शर्तें पूरी हैं तभी डेटा लगाएं।</div>
    </div>
    <label class="transfer-label">Husband Name <span class="text-danger">*</span></label>
    <input class="transfer-input" name="husband_name" maxlength="150" placeholder="Husband Name" required>
    <label class="transfer-label">Husband Family Id <span class="text-danger">*</span></label>
    <input class="transfer-input" name="husband_family_id" maxlength="50" placeholder="Enter Husband Family Id" required>
    <label class="transfer-label">Husband Sadasaya Id <span class="text-danger">*</span></label>
    <input class="transfer-input" name="husband_member_id" maxlength="50" placeholder="Enter Husband Sadasaya Id" required>
    <label class="transfer-label">Wife Name <span class="text-danger">*</span></label>
    <input class="transfer-input" name="wife_name" maxlength="150" placeholder="Enter Wife Name" required>
    <label class="transfer-label">Wife Sadasaya Id <span class="text-danger">*</span></label>
    <input class="transfer-input" name="wife_member_id" maxlength="50" placeholder="Enter Wife Sadasaya Id" required>
    <label class="transfer-label">Wife Family Id <span class="text-danger">*</span></label>
    <input class="transfer-input" name="wife_family_id" maxlength="50" placeholder="Enter Wife Family Id" required>
    <div class="transfer-actions">
        <div class="transfer-fee"><strong>Rs. <?php echo SAMAGRA_MEMBER_TRANSFER_FEE; ?></strong></div>
        <button class="transfer-submit" type="submit">Apply Data</button>
    </div>
</form>
</div></div></div></div>
<?php
if (isset($pageAlert)) {
    samagraMemberTransferAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
