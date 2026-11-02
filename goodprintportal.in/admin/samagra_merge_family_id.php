<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_merge_family_id_common.php');
checkSession();

if (!ensureSamagraMergeFamilyIdTable($ahk_conn)) {
    die('Unable to initialize Merge Family ID requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $familyId1 = trim($_POST['family_id_1'] ?? '');
    $familyId2 = trim($_POST['family_id_2'] ?? '');
    $originalFamilyId = trim($_POST['original_family_id'] ?? '');

    if ($familyId1 === '' || strlen($familyId1) > 50) {
        $pageAlert = ['Invalid Family ID 1', 'Please enter a valid Family ID 1.', 'error'];
    } elseif ($familyId2 === '' || strlen($familyId2) > 50) {
        $pageAlert = ['Invalid Family ID 2', 'Please enter a valid Family ID 2.', 'error'];
    } elseif ($originalFamilyId === '' || strlen($originalFamilyId) > 50) {
        $pageAlert = ['Invalid Original Family ID', 'Please enter a valid original family ID.', 'error'];
    } else {
        $phone = (string) $udata['phone'];
        $userId = (int) ($udata['id'] ?? 0);
        $applicationNo = samagraMergeFamilyIdApplicationNumber();
        $fee = SAMAGRA_MERGE_FAMILY_ID_FEE;
        $uploadDirectory = __DIR__ . '/uploads/samagra_merge_family_id/user_docs/';
        $savedFiles = [];
        $transactionStarted = false;

        try {
            $mukhiyaPdf1 = samagraMergeFamilyIdUploadPdf('mukhiya_pdf_1', $uploadDirectory);
            $savedFiles[] = $uploadDirectory . $mukhiyaPdf1;
            $mukhiyaPdf2 = samagraMergeFamilyIdUploadPdf('mukhiya_pdf_2', $uploadDirectory);
            $savedFiles[] = $uploadDirectory . $mukhiyaPdf2;

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

            $pdfPath1 = 'uploads/samagra_merge_family_id/user_docs/' . $mukhiyaPdf1;
            $pdfPath2 = 'uploads/samagra_merge_family_id/user_docs/' . $mukhiyaPdf2;

            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_merge_family_id_requests
                (application_no, user_id, user_phone, family_id_1, family_id_2, original_family_id, mukhiya_pdf_1, mukhiya_pdf_2, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'sissssssd', $applicationNo, $userId, $phone, $familyId1, $familyId2, $originalFamilyId, $pdfPath1, $pdfPath2, $fee);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Request insert failed.');
            }
            mysqli_stmt_close($stmt);

            $purpose = 'Merge Family ID - ' . $originalFamilyId;
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                (userid, amount, balance, purpose, status, type)
                VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet history insert failed.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($ahk_conn);
            header('Location: samagra_merge_family_id_list.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            if ($transactionStarted) {
                mysqli_rollback($ahk_conn);
            }

            foreach ($savedFiles as $savedFile) {
                if (is_file($savedFile)) {
                    @unlink($savedFile);
                }
            }

            if ($e->getMessage() === 'LOW_BALANCE') {
                $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_MERGE_FAMILY_ID_FEE . '. Please recharge your wallet.', 'warning'];
            } else {
                error_log('Merge Family ID error: ' . $e->getMessage());
                $pageAlert = ['Request Failed', $e instanceof RuntimeException ? $e->getMessage() : 'Please try again. No amount has been deducted.', 'error'];
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.merge-shell{max-width:780px;margin:35px auto;padding:0 15px}.merge-card{background:#fff;border:1px solid #d7dce3;border-radius:6px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.merge-title{background:#3674b8;color:#fff;padding:20px;font-size:21px;font-weight:700}.merge-body{padding:24px}.merge-badge{display:inline-block;margin-bottom:20px;padding:8px 16px;border-radius:20px;background:#7161ef;color:#fff;font-size:14px;font-weight:700}.merge-label{font-size:18px;margin:0 0 10px}.merge-input{width:100%;height:46px;border:1px solid #c8cfdb;border-radius:5px;padding:0 15px;font-size:17px;margin-bottom:20px}.merge-file{display:block;width:100%;border:1px dashed #c8cfdb;border-radius:5px;padding:12px 15px;font-size:16px;margin-bottom:20px;background:#fafbfd}.merge-actions{display:flex;justify-content:space-between;align-items:center;gap:12px}.merge-fee{border:1px solid #d5dae2;border-radius:5px;padding:10px 16px;font-size:18px}.merge-submit{border:0;border-radius:5px;background:#3674b8;color:#fff;padding:11px 22px;font-size:18px}.merge-links{text-align:right;margin-bottom:12px}@media(max-width:520px){.merge-body{padding:18px}.merge-actions{align-items:stretch;flex-direction:column}.merge-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="merge-shell">
    <div class="merge-links"><a class="btn btn-outline-primary" href="samagra_merge_family_id_list.php">My Requests</a></div>
    <div class="merge-card">
        <div class="merge-title">MARGE SAMAGRA</div>
        <form method="post" enctype="multipart/form-data" class="merge-body">
            <div class="merge-badge">DELIVERY TIME - 1-5 HOURS</div>
            <div class="merge-label">Family ID 1 <span class="text-danger">*</span></div>
            <input class="merge-input" name="family_id_1" maxlength="50" placeholder="Enter Family ID 1" required>
            <div class="merge-label">Family ID 2 <span class="text-danger">*</span></div>
            <input class="merge-input" name="family_id_2" maxlength="50" placeholder="Enter Family ID 2" required>
            <div class="merge-label">Original Family ID <span class="text-danger">*</span></div>
            <input class="merge-input" name="original_family_id" maxlength="50" placeholder="Enter Original Family ID" required>
            <div class="merge-label">Mukhiya Aadhar 1 (Only PDF) <span class="text-danger">*</span></div>
            <input class="merge-file" type="file" name="mukhiya_pdf_1" accept="application/pdf" required>
            <div class="merge-label">Mukhiya Aadhar 2 (Only PDF) <span class="text-danger">*</span></div>
            <input class="merge-file" type="file" name="mukhiya_pdf_2" accept="application/pdf" required>
            <div class="merge-actions">
                <div class="merge-fee"><strong>Rs. <?php echo SAMAGRA_MERGE_FAMILY_ID_FEE; ?></strong></div>
                <button class="merge-submit" type="submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php
if (isset($pageAlert)) {
    samagraMergeFamilyIdAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
