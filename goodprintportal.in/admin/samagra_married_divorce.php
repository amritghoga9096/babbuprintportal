<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_married_divorce_common.php');
checkSession();

if (!ensureSamagraMarriedDivorceTable($ahk_conn)) {
    die('Unable to initialize Samagra Married to Divorce requests.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $husbandName = trim($_POST['husband_name'] ?? '');
    $husbandMemberId = trim($_POST['husband_member_id'] ?? '');
    $wifeName = trim($_POST['wife_name'] ?? '');
    $wifeMemberId = trim($_POST['wife_member_id'] ?? '');
    $divorceFor = $_POST['divorce_for'] ?? '';

    if (
        $husbandName === '' || strlen($husbandName) > 150 ||
        $husbandMemberId === '' || strlen($husbandMemberId) > 50 ||
        $wifeName === '' || strlen($wifeName) > 150 ||
        $wifeMemberId === '' || strlen($wifeMemberId) > 50 ||
        !in_array($divorceFor, ['husband', 'wife'], true)
    ) {
        $pageAlert = ['Invalid Details', 'Please fill all divorce request details correctly.', 'error'];
    } else {
        $phone = (string) $udata['phone'];
        $userId = (int) ($udata['id'] ?? 0);
        $applicationNo = samagraMarriedDivorceNumber();
        $fee = SAMAGRA_MARRIED_DIVORCE_FEE;

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

            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO samagra_married_divorce_requests
                (application_no, user_id, user_phone, husband_name, husband_member_id,
                 wife_name, wife_member_id, divorce_for, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param(
                $stmt,
                'sissssssd',
                $applicationNo,
                $userId,
                $phone,
                $husbandName,
                $husbandMemberId,
                $wifeName,
                $wifeMemberId,
                $divorceFor,
                $fee
            );
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Request insert failed.');
            }
            mysqli_stmt_close($stmt);

            $purpose = 'Samagra Married to Divorce - ' . ucfirst($divorceFor);
            $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory
                (userid, amount, balance, purpose, status, type)
                VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt, 'sdds', $phone, $fee, $newBalance, $purpose);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Wallet history insert failed.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($ahk_conn);
            header('Location: samagra_married_divorce_list.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($ahk_conn);
            if ($e->getMessage() === 'LOW_BALANCE') {
                $pageAlert = ['Wallet Balance Low', 'This service requires Rs. ' . SAMAGRA_MARRIED_DIVORCE_FEE . '. Please recharge your wallet.', 'warning'];
            } else {
                error_log('Samagra Married to Divorce error: ' . $e->getMessage());
                $pageAlert = ['Request Failed', 'Please try again. No amount has been deducted.', 'error'];
            }
        }
    }
}

include('../template/ahkweb/header.php');
?>
<style>
.divorce-shell{max-width:850px;margin:25px auto;padding:0 15px}.divorce-card{background:#fff;border:1px solid #d7dce3;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,.16);overflow:hidden}.divorce-title{background:#b96da9;color:#fff;padding:14px 16px;font-size:16px;font-weight:700}.divorce-body{padding:15px}.divorce-note{background:#fff;border:1px solid #e2dce2;padding:13px 17px;margin-bottom:14px;color:#a84f91}.divorce-note h5{color:#b55ba4;font-weight:700}.divorce-warning{border-top:2px solid #f0b34e;background:#fffaf1;padding:8px;margin-top:10px;color:#ce8a28}.divorce-label{display:block;margin:0 0 7px}.divorce-input{width:100%;height:40px;border:1px solid #c8cfdb;border-radius:4px;padding:0 12px;margin-bottom:13px}.divorce-actions{display:flex;justify-content:space-between;align-items:center;gap:12px}.divorce-fee{border:1px solid #d5dae2;border-radius:5px;padding:8px 13px}.divorce-submit{border:0;border-radius:4px;background:#b95fa5;color:#fff;padding:9px 17px}.divorce-links{text-align:right;margin-bottom:10px}@media(max-width:520px){.divorce-actions{align-items:stretch;flex-direction:column}.divorce-submit{width:100%}}
</style>
<div class="page-wrapper"><div class="page-content">
<div class="divorce-shell">
<div class="divorce-links"><a class="btn btn-outline-primary" href="samagra_married_divorce_list.php">My Requests</a></div>
<div class="divorce-card">
<div class="divorce-title">&#128148; Samagarh Married to Unmarried (Divorce)</div>
<form method="post" class="divorce-body">
    <div class="divorce-note">
        <h5>&#9888; &#2350;&#2361;&#2340;&#2381;&#2357;&#2346;&#2370;&#2352;&#2381;&#2339; &#2360;&#2370;&#2330;&#2344;&#2366;</h5>
        <div>&#2360;&#2350;&#2327;&#2381;&#2352; &#2350;&#2375;&#2306; Married &#2360;&#2375; Unmarried &#2325;&#2352;&#2344;&#2375; &#2360;&#2375; &#2346;&#2361;&#2354;&#2375; &#2360;&#2349;&#2368; &#2332;&#2366;&#2344;&#2325;&#2366;&#2352;&#2368; &#2360;&#2361;&#2368; &#2349;&#2352;&#2375;&#2306;&#2404;</div>
        <ul class="mb-1">
            <li>&#2325;&#2375;&#2357;&#2354; Divorce &#2325;&#2375; &#2350;&#2366;&#2350;&#2354;&#2375; &#2350;&#2375;&#2306; &#2361;&#2368; &#2351;&#2361; &#2360;&#2375;&#2357;&#2366; &#2313;&#2346;&#2354;&#2348;&#2381;&#2343; &#2361;&#2376;&#2404;</li>
            <li>Husband &#2351;&#2366; Wife &#2350;&#2375;&#2306; &#2360;&#2375; &#2325;&#2367;&#2360;&#2368; &#2319;&#2325; &#2325;&#2379; Unmarried &#2325;&#2352;&#2366;&#2344;&#2366; &#2361;&#2379;&#2327;&#2366;&#2404;</li>
            <li>&#2342;&#2379;&#2344;&#2379;&#2306; &#2325;&#2368; Member ID &#2360;&#2361;&#2368; &#2361;&#2379;&#2344;&#2368; &#2330;&#2366;&#2361;&#2367;&#2319;&#2404;</li>
            <li>&#2360;&#2350;&#2327;&#2381;&#2352; &#2350;&#2375;&#2306; &#2342;&#2379;&#2344;&#2379;&#2306; Married status &#2350;&#2375;&#2306; &#2361;&#2379;&#2344;&#2375; &#2330;&#2366;&#2361;&#2367;&#2319;&#2404;</li>
        </ul>
        <div class="divorce-warning">&#9888; &#2327;&#2354;&#2340; &#2332;&#2366;&#2344;&#2325;&#2366;&#2352;&#2368; &#2342;&#2375;&#2344;&#2375; &#2346;&#2352; Request Reject &#2361;&#2379; &#2360;&#2325;&#2340;&#2366; &#2361;&#2376;&#2404;</div>
    </div>
    <label class="divorce-label">Husband Name <span class="text-danger">*</span></label>
    <input class="divorce-input" name="husband_name" maxlength="150" placeholder="Enter Husband Name" required>
    <label class="divorce-label">Husband Member ID <span class="text-danger">*</span></label>
    <input class="divorce-input" name="husband_member_id" maxlength="50" placeholder="Enter Husband Member ID" required>
    <label class="divorce-label">Wife Name <span class="text-danger">*</span></label>
    <input class="divorce-input" name="wife_name" maxlength="150" placeholder="Enter Wife Name" required>
    <label class="divorce-label">Wife Member ID <span class="text-danger">*</span></label>
    <input class="divorce-input" name="wife_member_id" maxlength="50" placeholder="Enter Wife Member ID" required>
    <label class="divorce-label">Divorce kiska karna hai? <span class="text-danger">*</span></label>
    <select class="divorce-input" name="divorce_for" required>
        <option value="">-- Select --</option>
        <option value="husband">Husband</option>
        <option value="wife">Wife</option>
    </select>
    <div class="divorce-actions">
        <div class="divorce-fee"><strong>Rs. <?php echo SAMAGRA_MARRIED_DIVORCE_FEE; ?></strong></div>
        <button class="divorce-submit" type="submit">Submit Divorce Request</button>
    </div>
</form>
</div></div></div></div>
<?php
if (isset($pageAlert)) {
    samagraMarriedDivorceAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
}
include('footers.php');
?>
