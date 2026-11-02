<?php
@ob_start();
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
error_reporting(0);
header('Content-Type: text/html; charset=UTF-8');

include_once(__DIR__.'/../includes/session.php');
include_once(__DIR__.'/../includes/config.php');

if (function_exists('checkSession')) {
    @checkSession();
}

$GLOBALS['_mp_ensure_done'] = false;
function _mpEnsure($conn) {
    if (!empty($GLOBALS['_mp_ensure_done'])) return true;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = array();
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM settings");
    if ($r) while ($row = @mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    foreach (array(
        'gateway_mode' => "ALTER TABLE settings ADD COLUMN gateway_mode VARCHAR(20) NOT NULL DEFAULT 'gateway'",
        'manual_upi_id' => "ALTER TABLE settings ADD COLUMN manual_upi_id VARCHAR(255) NULL DEFAULT NULL",
        'manual_qr_path' => "ALTER TABLE settings ADD COLUMN manual_qr_path VARCHAR(500) NULL DEFAULT NULL",
        'manual_qr_updated_at' => "ALTER TABLE settings ADD COLUMN manual_qr_updated_at DATETIME NULL DEFAULT NULL"
    ) as $c => $sql) if (!in_array($c, $cols)) @mysqli_query($conn, $sql);

    $wcols = array();
    $wr = @mysqli_query($conn, "SHOW COLUMNS FROM wallet");
    if ($wr) while ($row = @mysqli_fetch_assoc($wr)) $wcols[] = $row['Field'];
    foreach (array(
        'BANKTXNID' => "ALTER TABLE wallet ADD COLUMN BANKTXNID VARCHAR(60) NULL DEFAULT NULL",
        'PAYMENTMODE' => "ALTER TABLE wallet ADD COLUMN PAYMENTMODE VARCHAR(40) NULL DEFAULT NULL",
        'RESPMSG' => "ALTER TABLE wallet ADD COLUMN RESPMSG VARCHAR(500) NULL DEFAULT NULL",
        'status' => "ALTER TABLE wallet ADD COLUMN status VARCHAR(30) NULL DEFAULT 'pending'",
        'txn_date' => "ALTER TABLE wallet ADD COLUMN txn_date DATETIME NULL DEFAULT NULL",
        'date' => "ALTER TABLE wallet ADD COLUMN date VARCHAR(20) NULL DEFAULT NULL"
    ) as $c => $sql) if (!in_array($c, $wcols)) @mysqli_query($conn, $sql);

    $GLOBALS['_mp_ensure_done'] = true;
    return true;
}
_mpEnsure($ahk_conn);

$_gwRow = array('manual_upi_id'=>'', 'manual_qr_path'=>'', 'webname'=>'Good Print Portal', 'logo'=>'');
$_gwR = @mysqli_query($ahk_conn, "SELECT manual_upi_id, manual_qr_path, webname, logo FROM settings WHERE id=1 LIMIT 1");
if ($_gwR && ($_r = @mysqli_fetch_assoc($_gwR))) { foreach ($_gwRow as $k=>$v) if (isset($_r[$k])) $_gwRow[$k] = $_r[$k]; }
$manualUpi = $_gwRow['manual_upi_id'];
$manualQr  = $_gwRow['manual_qr_path'];
$webName   = $_gwRow['webname'];
$logoPath  = $_gwRow['logo'];

$orderId = '';
if (!empty($_GET['order'])) $orderId = trim($_GET['order']);
if ($orderId === '' && !empty($_POST['order'])) $orderId = trim($_POST['order']);
if ($orderId === '' && !empty($_SESSION['order_id'])) $orderId = $_SESSION['order_id'];
if ($orderId === '') { while (@ob_get_level()) @ob_end_clean(); header('Location: wallet.php'); exit; }

$txn_id = $phone = $amount = $banktxnid = $status = $paymode = $respmsg = $txndate = '';
$txnRowFound = false;
$ch = @mysqli_prepare($ahk_conn, "SELECT txn_id, phone, amount, BANKTXNID, status, PAYMENTMODE, RESPMSG, txn_date FROM wallet WHERE txn_id = ? LIMIT 1");
if ($ch) {
    @mysqli_stmt_bind_param($ch, 's', $orderId);
    if (@mysqli_stmt_execute($ch)) {
        @mysqli_stmt_store_result($ch);
        if (@mysqli_stmt_num_rows($ch) > 0) {
            @mysqli_stmt_bind_result($ch, $txn_id, $phone, $amount, $banktxnid, $status, $paymode, $respmsg, $txndate);
            if (@mysqli_stmt_fetch($ch)) $txnRowFound = true;
        }
    }
    @mysqli_stmt_close($ch);
}
if (!$txnRowFound) { while (@ob_get_level()) @ob_end_clean(); header('Location: wallet.php'); exit; }

$sessionPhone = (isset($udata['phone']) && $udata['phone']) ? $udata['phone'] : '';
$sessionType  = (isset($udata['type'])) ? $udata['type'] : '';
$_isAdmin = (function_exists('checkAdmin') && checkAdmin($sessionType));
if ($phone !== $sessionPhone && !$_isAdmin) {
    while (@ob_get_level()) @ob_end_clean();
    echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=wallet.php"></head><body><script>window.location="wallet.php";</script></body></html>';
    exit;
}

$orderAmount = (float)$amount;
if ($orderAmount <= 0 && isset($_SESSION['order_amount'])) $orderAmount = (float)$_SESSION['order_amount'];
$orderMobile = htmlspecialchars($phone);
$existingUtr = (string)$banktxnid;
$currentStatus = (string)$status;

function _popupAndExit($icon, $title, $bodyText, $confirmText, $redirectUrl) {
    while (@ob_get_level()) @ob_end_clean();
    $iconHtml = 'ℹ️';
    if ($icon === 'success') $iconHtml = '✅';
    elseif ($icon === 'error') $iconHtml = '❌';
    elseif ($icon === 'warning') $iconHtml = '⚠️';
    $h = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">'
        .'<title>Processing</title>'
        .'<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
        .'<style>'
        .'*{box-sizing:border-box;font-family:Inter,Arial,sans-serif}'
        .'body{margin:0;min-height:100vh;background:linear-gradient(135deg,#4e54c8 0%,#667eea 100%);display:flex;align-items:center;justify-content:center;padding:24px;}'
        .'.card{background:#fff;border-radius:20px;max-width:520px;width:100%;padding:28px 26px;box-shadow:0 30px 70px rgba(30,41,59,.4);}'
        .'.hd{font-size:22px;font-weight:800;color:#0f172a;margin:12px 0 6px}'
        .'.msg{line-height:1.75;color:#334155;font-weight:600;font-size:14px;white-space:pre-wrap;}'
        .'.btn{margin-top:22px;display:inline-block;background:linear-gradient(135deg,#4e54c8 0%,#667eea 100%);border:none;color:#fff;padding:12px 20px;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer;text-decoration:none;text-align:center;box-shadow:0 10px 24px rgba(79,70,229,.28);}'
        .'.ic{font-size:48px;}'
        .'</style></head><body>'
        .'<div class="card" id="fallbackCard">'
        .'<div class="ic">'.htmlspecialchars($iconHtml).'</div>'
        .'<div class="hd">'.htmlspecialchars($title).'</div>'
        .'<div class="msg">'.str_replace("\n", "<br>", htmlspecialchars($bodyText)).'</div>';
    if ($redirectUrl) {
        $safeUrl = htmlspecialchars($redirectUrl);
        $h .= '<a class="btn" href="'.$safeUrl.'">'.htmlspecialchars($confirmText ?: 'Continue').'</a>';
    } else {
        $h .= '<button class="btn" onclick="history.back()">'.htmlspecialchars($confirmText ?: 'Back').'</button>';
    }
    $h .= '</div>';

    $safeIcon = $icon;
    $safeTitle = json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $safeText  = json_encode($bodyText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $safeBtn   = json_encode($confirmText ?: 'Continue', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $safeUrlJs = $redirectUrl ? json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'null';

    $h .= '<script>'
          .'function goFallback(){'
          .'  var c=document.getElementById("fallbackCard"); if(c){ c.style.display="block"; }'
          .'}'
          .'try { '
          .'  if (typeof Swal !== "undefined") { '
          .'    Swal.fire({'
          .'      icon:'.json_encode($safeIcon, JSON_UNESCAPED_UNICODE).','
          .'      title:'.$safeTitle.','
          .'      text:'.$safeText.','
          .'      confirmButtonColor: "#4e54c8",'
          .'      confirmButtonText:'.$safeBtn.','
          .'      allowOutsideClick: false,'
          .'      allowEscapeKey: false'
          .'    }).then(function(res){ if (res && res.isConfirmed && '.$safeUrlJs.') window.location.href = '.$safeUrlJs.'; });'
          .'    var card=document.getElementById("fallbackCard"); if(card){ card.style.display="none"; } '
          .'  } else { '
          .'    alert('.$safeTitle.' + "\n\n" + '.$safeText.'); '
          .'    if ('.$safeUrlJs.') window.location.href = '.$safeUrlJs.'; '
          .'    else goFallback();'
          .'  }'
          .'} catch(e){ try{ alert('.$safeTitle.' + "\n\n" + '.$safeText.'); if('.$safeUrlJs.'){ window.location.href = '.$safeUrlJs.'; } } catch(e2){ goFallback(); } }'
          .' setTimeout(goFallback, 2500); '
          .'</script>';

    $h .= '</body></html>';
    echo $h;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_utr']) || isset($_POST['order']) && !empty($_POST['utr_number']))) {
    $utr = trim($_POST['utr_number'] ?? '');
    $utrSafe = function_exists('getSafe') ? getSafe($utr) : @mysqli_real_escape_string($ahk_conn, $utr);
    $backUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/manual_payment.php?order=' . urlencode($orderId);
    $walletUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/wallet.php';
    $orderT = $orderId;
    $amtT   = '₹' . number_format($orderAmount, 2);
    $utrT   = $utrSafe;

    if ($utrSafe === '' || strlen($utrSafe) < 6) {
        _popupAndExit('error', 'Invalid UTR',
            "Please enter a valid UTR / Transaction ID.\nMinimum 6 characters required.",
            'Try Again', $backUrl);
    }

    $dupCount = 0;
    $dupStmt = @mysqli_prepare($ahk_conn, "SELECT COUNT(*) FROM wallet WHERE BANKTXNID = ? AND txn_id != ? AND BANKTXNID IS NOT NULL AND BANKTXNID != ''");
    if ($dupStmt) {
        @mysqli_stmt_bind_param($dupStmt, 'ss', $utrSafe, $orderId);
        if (@mysqli_stmt_execute($dupStmt)) {
            @mysqli_stmt_store_result($dupStmt);
            @mysqli_stmt_bind_result($dupStmt, $dupCount);
            @mysqli_stmt_fetch($dupStmt);
        }
        @mysqli_stmt_close($dupStmt);
    }
    if ($dupCount > 0) {
        _popupAndExit('error', 'Duplicate UTR!',
            "This UTR has ALREADY been used for another payment.\n\n"
            ."Please enter correct UTR from your payment receipt.",
            'Try Again', $backUrl);
    }

    $remarkUpd = 'UTR Submitted - Awaiting Admin Approval | UTR: ' . $utrSafe;
    $modeStr = 'ManualUPI';
    $statusPend = 'pending';
    $ok = false;

    $updStmt = @mysqli_prepare($ahk_conn, "UPDATE wallet SET BANKTXNID = ?, RESPMSG = ?, PAYMENTMODE = ?, status = ?, txn_date = NOW() WHERE txn_id = ? LIMIT 1");
    if (!$updStmt) {
        $fb = @mysqli_prepare($ahk_conn, "UPDATE wallet SET RESPMSG = ?, PAYMENTMODE = ?, status = ?, txn_date = NOW() WHERE txn_id = ? LIMIT 1");
        if ($fb) {
            @mysqli_stmt_bind_param($fb, 'ssss', $remarkUpd, $modeStr, $statusPend, $orderId);
            if (@mysqli_stmt_execute($fb)) $ok = true;
            @mysqli_stmt_close($fb);
        }
    } else {
        @mysqli_stmt_bind_param($updStmt, 'sssss', $utrSafe, $remarkUpd, $modeStr, $statusPend, $orderId);
        if (@mysqli_stmt_execute($updStmt)) $ok = true;
        @mysqli_stmt_close($updStmt);
    }

    if (!$ok) {
        $dberr = @mysqli_error($ahk_conn);
        _popupAndExit('error', 'Server Error',
            "Sorry, we could not save your UTR right now.\nPlease try again in 1-2 minutes.\n"
            . ($dberr ? "\nDetails: ".$dberr : ''),
            'Try Again', $backUrl);
    }

    unset($_SESSION['order_id'], $_SESSION['order_amount'], $_SESSION['order_generated_time']);

    $successTxt = "Order ID: $orderT\nAmount:   $amtT\nUTR:      $utrT\n\n"
                 ."Payment Details Submitted Successfully!\n\n"
                 ."Your wallet will be automatically credited once the admin reviews and approves this transaction.\n"
                 ."You will see the updated balance + deposit history in wallet page.";
    _popupAndExit('success', 'UTR Submitted!', $successTxt, 'View Wallet History', $walletUrl);
}

$hasUtrSubmitted = ($existingUtr !== '' && $currentStatus === 'pending' && stripos((string)$respmsg, 'UTR Submitted') !== false);
$isSuccess = ($currentStatus === 'success');
$isRejected = ($currentStatus === 'rejected');

$selfUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
         . '/manual_payment.php?order=' . urlencode($orderId);
$walletUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
           . '://' . $_SERVER['HTTP_HOST']
           . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
           . '/wallet.php';

while (@ob_get_level()) @ob_end_clean();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Secure Payment | <?= htmlspecialchars($webName) ?></title>
<meta name="robots" content="noindex, nofollow">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { font-family:'Inter',sans-serif; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
html, body { overflow-x:hidden; }
body {
    background: linear-gradient(135deg,#4e54c8 0%,#8f94fb 45%,#667eea 100%);
    min-height:100vh;
    padding:14px 10px 40px 10px;
    margin:0;
    -webkit-font-smoothing:antialiased;
}
.page-wrap { max-width:720px; margin:0 auto; }
.secure-banner {
    background:rgba(255,255,255,0.14);
    color:#fff;
    border-radius:12px;
    padding:10px 14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:16px;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    font-size:12.5px;
    font-weight:600;
    border:1px solid rgba(255,255,255,0.22);
    flex-wrap:wrap;
    gap:8px;
}
.secure-banner .brand { display:flex; align-items:center; gap:10px; font-weight:800; letter-spacing:.3px; }
.secure-banner .sec { display:flex; align-items:center; gap:8px; }
.card-pay {
    background:#fff;
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 25px 60px rgba(30,41,59,0.35), 0 8px 24px rgba(30,41,59,0.15);
}
.card-head {
    background:linear-gradient(135deg,#4e54c8 0%,#667eea 100%);
    color:#fff;
    padding:18px 20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
}
.card-head .amt { font-size:28px; font-weight:800; }
.card-head .amt sub { font-size:15px; font-weight:500; opacity:.9; }
.card-head .label { font-size:11px; opacity:.85; letter-spacing:.4px; text-transform:uppercase; margin-bottom:3px; }
.card-body { padding:20px; }
.brand-row { display:flex; align-items:center; gap:12px; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid #f1f5f9; }
.brand-row .logo {
    width:42px; height:42px; border-radius:10px; background:#f8fafc;
    display:flex; align-items:center; justify-content:center; overflow:hidden;
    border:1px solid #e2e8f0; flex-shrink:0;
}
.brand-row .logo img { width:100%; height:100%; object-fit:contain; display:block; }
.brand-row .webname { font-weight:800; font-size:16px; color:#0f172a; }
.brand-row .sub { font-size:12px; color:#64748b; font-weight:500; }
.qr-wrap {
    background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);
    border:2px dashed #c7d2fe;
    border-radius:18px;
    padding:18px;
    text-align:center;
    margin-bottom:18px;
}
.qr-wrap .qr-box {
    width:200px; height:200px; max-width:75vw;
    background:#fff; margin:0 auto 12px auto; border-radius:14px;
    padding:10px; box-shadow:0 10px 25px rgba(79,70,229,0.12);
    display:flex; align-items:center; justify-content:center; overflow:hidden;
}
.qr-wrap .qr-box img { width:100%; height:100%; object-fit:contain; display:block; }
.qr-wrap .qr-hint { font-size:12.5px; color:#475569; font-weight:600; line-height:1.6; }
.qr-empty { color:#94a3b8; font-size:13px; font-weight:600; line-height:1.6; padding:20px 10px; }
.upi-box {
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:13px 15px;
    margin-bottom:16px;
}
.upi-label { font-size:11px; text-transform:uppercase; letter-spacing:.6px; color:#64748b; font-weight:700; margin-bottom:6px; }
.upi-row { display:flex; align-items:center; gap:10px; justify-content:space-between; flex-wrap:wrap; }
.upi-id { font-size:17px; font-weight:800; color:#0f172a; word-break:break-all; min-width:120px; flex:1; }
.copy-btn {
    background:#4e54c8; color:#fff; border:none; border-radius:10px; padding:8px 14px;
    font-weight:700; font-size:13px; cursor:pointer; transition:.18s all;
    display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
    -webkit-appearance:none; appearance:none;
}
.copy-btn:hover { background:#4338ca; transform:translateY(-1px); box-shadow:0 8px 18px rgba(79,70,229,.28); }
.copy-btn.copied { background:#059669; }
.amount-info {
    display:grid; grid-template-columns:1fr 1fr; gap:10px;
    background:#fafbff; border-radius:14px; padding:14px 16px; margin-bottom:16px;
    border:1px solid #e0e7ff;
}
.amount-info .inf { font-size:11.5px; color:#64748b; font-weight:600; margin-bottom:3px; }
.amount-info .val { font-size:15px; font-weight:800; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.amount-info .val.amt { color:#4e54c8; }
.utr-form { padding:4px 2px 2px 2px; }
.form-label { font-size:12px; font-weight:700; color:#334155; margin-bottom:8px; display:block; letter-spacing:.3px; }
.utr-input {
    width:100%; border:2px solid #e2e8f0; border-radius:12px; padding:13px 14px;
    font-size:16px; font-weight:600; transition:.18s all; background:#fff; color:#0f172a;
    -webkit-appearance:none; appearance:none; font-family:inherit;
}
.utr-input:focus { outline:none; border-color:#4e54c8; box-shadow:0 0 0 4px rgba(79,70,229,.12); }
.submit-btn {
    width:100%; margin-top:14px; background:linear-gradient(135deg,#4e54c8 0%,#667eea 100%);
    border:none; color:#fff; padding:14px; border-radius:12px; font-weight:800; font-size:15px;
    cursor:pointer; letter-spacing:.4px; box-shadow:0 10px 24px rgba(79,70,229,.28);
    transition:.2s all; min-height:48px;
    -webkit-appearance:none; appearance:none; font-family:inherit;
}
.submit-btn:hover { transform:translateY(-2px); box-shadow:0 14px 30px rgba(79,70,229,.35); }
.submit-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
.status-box {
    border-radius:14px; padding:14px 16px; margin-bottom:18px;
    display:flex; align-items:flex-start; gap:14px;
}
.status-box.pending { background:#fffbeb; border:1px solid #fcd34d; color:#92400e; }
.status-box.success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.status-box.rejected { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
.status-box .ic { font-size:26px; margin-top:2px; flex-shrink:0; }
.status-box .t { font-weight:800; font-size:15px; }
.status-box .d { font-size:13px; opacity:.95; font-weight:600; margin-top:5px; line-height:1.6; }
.go-wallet-btn {
    display:inline-block; margin-top:16px;
    background:#fff; color:#4e54c8; padding:10px 16px;
    border-radius:10px; font-weight:700; font-size:13px; text-decoration:none;
    box-shadow:0 6px 18px rgba(0,0,0,0.08); border:1px solid #e0e7ff;
}
.go-wallet-btn:hover { background:#eef2ff; text-decoration:none; color:#4e54c8; }
.trust-row { display:flex; align-items:center; justify-content:center; gap:12px; margin-top:20px; color:#fff; font-size:11.5px; opacity:.95; font-weight:600; text-align:center; flex-wrap:wrap; padding:0 10px; line-height:1.6; }
.trust-row i { color:#a5f3fc; }
.noscript { background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-weight:700;font-size:12.5px; }
@media(max-width:520px){
    body { padding:10px 8px 36px 8px; }
    .card-body { padding:16px 14px; }
    .card-head { padding:15px 16px; }
    .card-head .amt { font-size:22px; }
    .card-head .label { font-size:10px; }
    .upi-id { font-size:14.5px; }
    .amount-info { grid-template-columns:1fr 1fr; gap:8px; padding:12px; }
    .amount-info .val { font-size:14px; }
    .qr-wrap { padding:14px; }
    .qr-wrap .qr-box { width:170px; height:170px; padding:8px; }
    .brand-row .logo { width:38px; height:38px; }
    .brand-row .webname { font-size:15px; }
    .utr-input { font-size:16px; padding:12px 13px; }
    .submit-btn { font-size:14px; padding:13px; }
    .copy-btn { padding:7px 12px; font-size:12px; }
    .secure-banner { font-size:11.5px; padding:8px 12px; }
    .status-box { padding:12px 13px; gap:12px; }
    .status-box .t { font-size:14px; }
    .trust-row { font-size:10.5px; }
}
@media(max-width:360px){
    .card-head .amt { font-size:20px; }
    .upi-id { font-size:13.5px; }
    .amount-info .val { font-size:13px; }
    .amount-info .inf { font-size:10.5px; }
    .qr-wrap .qr-box { width:150px; height:150px; }
}
</style>
</head>
<body>
<noscript>
    <div class="noscript"><i class="fa-solid fa-triangle-exclamation"></i> JavaScript is disabled! Please enable JavaScript in your browser to submit this form.</div>
</noscript>
<div class="page-wrap">
    <div class="secure-banner">
        <div class="brand">
            <i class="fa-solid fa-shield-halved"></i>
            <span><?= htmlspecialchars($webName) ?> · Secure Checkout</span>
        </div>
        <div class="sec">
            <i class="fa-solid fa-lock"></i>
            <span>SSL Secured</span>
            <span style="opacity:.5;">|</span>
            <i class="fa-regular fa-clock"></i>
            <span><?= date('d M, H:i') ?></span>
        </div>
    </div>

    <div class="card-pay">
        <div class="card-head">
            <div>
                <div class="label">Wallet Top-up Amount</div>
                <div class="amt"><sub>₹</sub><?= number_format($orderAmount, 2) ?></div>
            </div>
            <div style="text-align:right;">
                <div class="label">Order ID</div>
                <div style="font-weight:800; font-size:13px; word-break:break-all; max-width:200px;"><?= htmlspecialchars($orderId) ?></div>
                <div style="font-size:11px; opacity:.85; margin-top:4px;">Mobile: <?= $orderMobile ?></div>
            </div>
        </div>
        <div class="card-body">

            <div class="brand-row">
                <div class="logo">
                    <?php if (!empty($logoPath)): ?>
                        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($webName) ?>">
                    <?php else: ?>
                        <i class="fa-solid fa-wallet" style="color:#4e54c8; font-size:18px;"></i>
                    <?php endif; ?>
                </div>
                <div style="flex:1; min-width:0;">
                    <div class="webname"><?= htmlspecialchars($webName) ?></div>
                    <div class="sub"><i class="fa-solid fa-circle-check" style="color:#4ade80;"></i> Verified Merchant · Manual UPI</div>
                </div>
            </div>

            <?php if ($isSuccess): ?>
                <div class="status-box success">
                    <div class="ic"><i class="fa-solid fa-circle-check"></i></div>
                    <div>
                        <div class="t">Payment Successful</div>
                        <div class="d">Your wallet has been credited with ₹<?= number_format($orderAmount, 2) ?>.</div>
                        <a class="go-wallet-btn" href="<?= htmlspecialchars($walletUrl) ?>"><i class="fa-solid fa-left-long me-1"></i> Back to Wallet</a>
                    </div>
                </div>
                <script>setTimeout(function(){ window.location.href = <?= json_encode($walletUrl, JSON_UNESCAPED_SLASHES) ?>; }, 1800);</script>
            <?php elseif ($isRejected): ?>
                <div class="status-box rejected">
                    <div class="ic"><i class="fa-solid fa-circle-xmark"></i></div>
                    <div>
                        <div class="t">Payment Rejected</div>
                        <div class="d"><?= htmlspecialchars($respmsg) ?></div>
                        <a class="go-wallet-btn" href="<?= htmlspecialchars($walletUrl) ?>"><i class="fa-solid fa-left-long me-1"></i> Back to Wallet</a>
                    </div>
                </div>
            <?php elseif ($hasUtrSubmitted): ?>
                <div class="status-box pending">
                    <div class="ic"><i class="fa-solid fa-circle-pause"></i></div>
                    <div>
                        <div class="t">UTR Already Submitted · Awaiting Approval</div>
                        <div class="d">UTR: <b><?= htmlspecialchars($existingUtr) ?></b><br>Admin will approve shortly. Once approved, wallet balance will reflect automatically.</div>
                        <a class="go-wallet-btn" href="<?= htmlspecialchars($walletUrl) ?>"><i class="fa-solid fa-book me-1"></i> View Wallet History</a>
                    </div>
                </div>
            <?php else: ?>

                <div class="qr-wrap">
                    <?php if (!empty($manualQr)): ?>
                        <div class="qr-box">
                            <img src="<?= htmlspecialchars($manualQr) ?>" alt="Scan to Pay" loading="eager">
                        </div>
                        <div class="qr-hint"><i class="fa-solid fa-qrcode"></i> Scan this QR in any UPI App (GPay / PhonePe / Paytm / BHIM)</div>
                    <?php else: ?>
                        <div class="qr-box">
                            <div class="qr-empty"><i class="fa-solid fa-image" style="font-size:34px; margin-bottom:10px; display:block;"></i>No QR uploaded yet by admin</div>
                        </div>
                        <div class="qr-hint">Please use the UPI ID below to make payment manually</div>
                    <?php endif; ?>
                </div>

                <div class="upi-box">
                    <div class="upi-label"><i class="fa-solid fa-building-columns"></i> Pay To UPI ID</div>
                    <div class="upi-row">
                        <div class="upi-id" id="upiId"><?= htmlspecialchars($manualUpi ?: 'Contact admin to set UPI ID') ?></div>
                        <button class="copy-btn" id="copyUpiBtn" type="button" onclick="copyText('upiId','copyUpiBtn','UPI ID copied!')">
                            <i class="fa-regular fa-copy"></i> <span>Copy UPI</span>
                        </button>
                    </div>
                </div>

                <div class="amount-info">
                    <div>
                        <div class="inf">Amount to Pay</div>
                        <div class="val amt" id="amtVal">₹<?= number_format($orderAmount, 2) ?></div>
                    </div>
                    <div>
                        <div class="inf">Reference</div>
                        <div class="val" title="<?= $orderMobile ?>"><?= $orderMobile ?></div>
                    </div>
                </div>

                <div class="upi-box" style="background:#eff6ff; border-color:#bfdbfe; margin-bottom:18px;">
                    <div class="upi-label"><i class="fa-solid fa-indian-rupee-sign"></i> Exact Amount</div>
                    <div class="upi-row">
                        <div class="upi-id" id="amtCopyVal">₹<?= number_format($orderAmount, 2) ?></div>
                        <button class="copy-btn" id="copyAmtBtn" type="button" onclick="copyAmt('<?= number_format($orderAmount,2,'.','') ?>','copyAmtBtn','Amount copied!')">
                            <i class="fa-regular fa-copy"></i> <span>Copy Amount</span>
                        </button>
                    </div>
                </div>

                <form class="utr-form" method="post" id="utrForm" action="<?= htmlspecialchars($selfUrl) ?>" novalidate onsubmit="return validateBeforeSubmit(event)">
                    <input type="hidden" name="order" value="<?= htmlspecialchars($orderId) ?>">
                    <label class="form-label" for="utr_number"><i class="fa-solid fa-receipt"></i> Enter UTR / Transaction ID (12 digits)</label>
                    <input type="text" class="utr-input" name="utr_number" id="utr_number" inputmode="numeric"
                           placeholder="Example: 309712345678" maxlength="30" autocomplete="off" required>
                    <div style="font-size:11.5px; color:#64748b; margin-top:7px; font-weight:600; line-height:1.6;">
                        <i class="fa-solid fa-circle-info"></i> After paying in GPay/PhonePe/Paytm/Bank App, copy the 12-digit UTR (transaction ID) from your payment receipt and paste it above.
                    </div>
                    <button type="submit" name="submit_utr" value="1" class="submit-btn" id="submitBtn">
                        <i class="fa-solid fa-paper-plane"></i> Submit Payment Details
                    </button>
                </form>

            <?php endif; ?>

        </div>
    </div>

    <div class="trust-row">
        <i class="fa-solid fa-user-shield"></i>
        <span>100% Secure · Payment records are verified &amp; stored</span>
        <i class="fa-solid fa-user-shield"></i>
    </div>
</div>

<script>
(function(){
    window._swalAvailable = false;
    try { window._swalAvailable = (typeof Swal !== 'undefined'); } catch(e){ window._swalAvailable = false; }
    setTimeout(function(){ try{ window._swalAvailable = (typeof Swal !== 'undefined'); }catch(e){ window._swalAvailable = false; } }, 800);

    window.validateBeforeSubmit = function(e) {
        var inp = document.getElementById('utr_number');
        var v = inp ? String(inp.value || '').trim() : '';
        if (v.length < 6) {
            e.preventDefault && e.preventDefault();
            e.stopPropagation && e.stopPropagation();
            try {
                if (window._swalAvailable) {
                    Swal.fire({ icon:'error', title:'Invalid UTR', text:'Please enter a valid UTR / Transaction ID (at least 6 characters).', confirmButtonColor:'#4e54c8' });
                } else {
                    alert('Invalid UTR!\n\nPlease enter a valid UTR / Transaction ID (at least 6 characters).');
                }
            } catch(err) { alert('Invalid UTR! Minimum 6 characters required.'); }
            return false;
        }
        var btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
        }
        return true;
    };

    function flashCopy(btn, msg) {
        if (!btn) return;
        var orig = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>' + msg + '</span>';
        setTimeout(function(){
            btn.classList.remove('copied');
            btn.innerHTML = orig;
        }, 1800);
    }
    window.copyText = function(elId, btnId, msg) {
        var el = document.getElementById(elId);
        var btn = document.getElementById(btnId);
        var t = el ? (el.innerText || '').trim() : '';
        if (!t) return;
        var done = function(){ flashCopy(btn, msg); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(done).catch(function(){ fallbackCopy(t, done); });
        } else {
            fallbackCopy(t, done);
        }
    };
    window.copyAmt = function(val, btnId, msg) {
        var btn = document.getElementById(btnId);
        val = String(val || '').trim();
        if (!val) return;
        var done = function(){ flashCopy(btn, msg); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(val).then(done).catch(function(){ fallbackCopy(val, done); });
        } else {
            fallbackCopy(val, done);
        }
    };
    function fallbackCopy(text, cb) {
        try {
            var ta = document.createElement('textarea');
            ta.value = String(text);
            ta.setAttribute('readonly','');
            ta.style.position = 'fixed';
            ta.style.top = '-9999px';
            ta.style.left = '0';
            ta.style.width = '1px';
            ta.style.height = '1px';
            document.body.appendChild(ta);
            ta.select();
            ta.setSelectionRange(0, ta.value.length);
            var ok = false;
            try { ok = document.execCommand('copy'); } catch(e){ ok = false; }
            document.body.removeChild(ta);
            if (cb) cb();
        } catch(e) { if (cb) cb(); }
    }
})();
</script>
</body>
</html>
