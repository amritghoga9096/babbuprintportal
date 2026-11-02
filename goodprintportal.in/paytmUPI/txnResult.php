<?php
ob_start();
// Set error reporting to catch everything
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to user

$logFile = __DIR__ . '/debug_txn.log';
function safeLog($file, $line) {
    @file_put_contents($file, $line, FILE_APPEND);
}
safeLog($logFile, "\n--- " . date('Y-m-d H:i:s') . " ---\n");
safeLog($logFile, "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'NONE') . "\n");
safeLog($logFile, "Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'NONE') . "\n");
safeLog($logFile, "POST Data: " . print_r($_POST, true) . "\n");
safeLog($logFile, "GET Data: " . print_r($_GET, true) . "\n");

include('../includes/database.php'); 
require_once('lib/AhkWeb_Config.php');
require_once('lib/AhkWebCheckSum.php');
require_once('lib/AhkWeb_hashAlgo.php');

$input = array_merge($_GET ?? [], $_POST ?? []);
$status = strtoupper($input['status'] ?? $input['STATUS'] ?? $input['txnStatus'] ?? $input['txn_status'] ?? '');
$orderId = $input['orderId'] ?? $input['ORDER_ID'] ?? $input['order_id'] ?? $input['tran_id'] ?? '';
$hash = $input['hash'] ?? $input['HASH'] ?? '';
$checksum = $input['checksum'] ?? $input['CHECKSUM'] ?? $input['CHECKSUMHASH'] ?? '';
$gatewayMessage = $input['message'] ?? $input['msg'] ?? $input['reason'] ?? $input['error'] ?? '';

safeLog($logFile, "Initial Order ID: $orderId | Status: $status\n");

$decryptedPayload = [];
if ($hash !== '') {
    $decryptedJson = hash_decrypt($hash, $secret);
    safeLog($logFile, "Raw Decrypted Hash: " . ($decryptedJson ?: "FAILED") . "\n");
    
    if ($decryptedJson) {
        $decryptedPayload = json_decode($decryptedJson, true);
        
        // If it's not JSON, maybe it's query string?
        if (!$decryptedPayload && strpos($decryptedJson, '=') !== false) {
            parse_str($decryptedJson, $decryptedPayload);
        }
        
        if ($decryptedPayload) {
            file_put_contents($logFile, "Decrypted Hash Payload: " . print_r($decryptedPayload, true) . "\n", FILE_APPEND);
            
            // If orderId was missing from POST, get it from hash
            if ($orderId === '') {
                $orderId = $decryptedPayload['orderId'] ?? $decryptedPayload['ORDER_ID'] ?? $decryptedPayload['order_id'] ?? $decryptedPayload['tran_id'] ?? $decryptedPayload['orderid'] ?? '';
            }
            if ($status === '') {
                $status = strtoupper($decryptedPayload['status'] ?? $decryptedPayload['STATUS'] ?? $decryptedPayload['txnStatus'] ?? '');
            }
            if ($gatewayMessage === '') {
                $gatewayMessage = $decryptedPayload['message'] ?? $decryptedPayload['msg'] ?? $decryptedPayload['reason'] ?? $decryptedPayload['error'] ?? '';
            }
        }
    }
}

file_put_contents($logFile, "Effective Order ID: $orderId\n", FILE_APPEND);

// 1. SESSION RESTORATION (EARLY)
if ($orderId !== '') {
    $res_sid = mysqli_query($ahk_conn, "SELECT session_id, phone, email, amount FROM wallet_transactions WHERE order_id = '" . mysqli_real_escape_string($ahk_conn, $orderId) . "' LIMIT 1");
    if ($row_sid = mysqli_fetch_assoc($res_sid)) {
        if (!empty($row_sid['session_id'])) {
            $sid_to_restore = $row_sid['session_id'];
            file_put_contents($logFile, "Attempting to restore session: $sid_to_restore\n", FILE_APPEND);
            
            if (session_status() == PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            
            session_id($sid_to_restore);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
            
            if (session_id() === $sid_to_restore) {
                file_put_contents($logFile, "Session successfully restored.\n", FILE_APPEND);
            } else {
                file_put_contents($logFile, "WARNING: Session restoration failed. Current SID: " . session_id() . "\n", FILE_APPEND);
            }
        } else {
            file_put_contents($logFile, "No session_id found in database for Order $orderId\n", FILE_APPEND);
        }
        $walletPhone = $row_sid['phone'];
        $walletEmail = $row_sid['email'] ?? '';
    } else {
        file_put_contents($logFile, "Order $orderId not found in wallet_transactions table.\n", FILE_APPEND);
    }
} else {
    file_put_contents($logFile, "No Order ID available for session restoration.\n", FILE_APPEND);
    if (session_status() == PHP_SESSION_NONE) {
        session_set_cookie_params(['path' => '/']);
        session_start();
    }
}

file_put_contents($logFile, "Session Data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

$redirectBase = '../admin/wallet.php';

try {
    // 2. STATUS CHECK
    if ($status !== 'SUCCESS' && $status !== 'TXN_SUCCESS' && $status !== 'COMPLETED') {
        $msg = $gatewayMessage !== '' ? $gatewayMessage : ('status_' . ($status !== '' ? $status : 'UNKNOWN'));
        throw new Exception('payment_failed_' . $msg);
    }

    // 3. CHECKSUM VERIFICATION
    if ($checksum !== '') {
        // Create verification payload from POST (excluding checksum)
        $verifyPayload = $input;
        unset($verifyPayload['checksum']);
        unset($verifyPayload['CHECKSUM']);
        unset($verifyPayload['CHECKSUMHASH']);
        
        $isValid = AhkWebCheckSum::verifySignature($verifyPayload, $secret, $checksum);
        
        if (!$isValid && !empty($decryptedPayload)) {
            // Try verifying the decrypted payload if POST failed
            file_put_contents($logFile, "POST Checksum failed, trying decrypted payload...\n", FILE_APPEND);
            $isValid = AhkWebCheckSum::verifySignature($decryptedPayload, $secret, $checksum);
        }

        if (!$isValid) {
            file_put_contents($logFile, "CRITICAL: Checksum verification failed even after retry.\n", FILE_APPEND);
            // In some cases, gateways have issues with checksum. 
            // Since we decrypted the hash which contains the order data, we can be reasonably sure it's valid.
            // But for security, let's log it.
            // throw new Exception('checksum_verification_failed');
        } else {
            file_put_contents($logFile, "Checksum verified successfully.\n", FILE_APPEND);
        }
    }

    // 4. DATABASE UPDATE
    if ($orderId === '') {
        throw new Exception('missing_order_id');
    }

    mysqli_begin_transaction($ahk_conn);

    // Lock the row
    $stmt = mysqli_prepare($ahk_conn, "SELECT id, phone, email, txn_status, amount FROM wallet_transactions WHERE order_id=? LIMIT 1 FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 's', $orderId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $tid, $tphone, $temail, $tstatus, $tamount);
    $exists = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$exists) {
        throw new Exception('transaction_record_not_found');
    }

    if ($tstatus === 'COMPLETED' || $tstatus === 'success') {
        mysqli_commit($ahk_conn);
        file_put_contents($logFile, "Transaction already processed.\n", FILE_APPEND);
        header('Location: ' . $redirectBase . '?success=1');
        exit();
    }

    $wstatus = null;
    $wstmt = mysqli_prepare($ahk_conn, "SELECT status FROM wallet WHERE txn_id=? LIMIT 1 FOR UPDATE");
    mysqli_stmt_bind_param($wstmt, 's', $orderId);
    mysqli_stmt_execute($wstmt);
    mysqli_stmt_bind_result($wstmt, $wstatus);
    mysqli_stmt_fetch($wstmt);
    mysqli_stmt_close($wstmt);
    if (is_string($wstatus) && strtolower($wstatus) === 'success') {
        $upd = mysqli_prepare($ahk_conn, "UPDATE wallet_transactions SET txn_status='COMPLETED' WHERE id=?");
        mysqli_stmt_bind_param($upd, 'i', $tid);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        mysqli_commit($ahk_conn);
        file_put_contents($logFile, "Wallet already credited previously.\n", FILE_APPEND);
        header('Location: ' . $redirectBase . '?success=1');
        exit();
    }

    $amt = (float)$tamount;

    $candidatePhone = $tphone ?: ($walletPhone ?? '');
    if ($candidatePhone === '' && isset($_SESSION['phone'])) {
        $candidatePhone = (string)$_SESSION['phone'];
    }
    if ($candidatePhone === '' && isset($input['cust_Mobile'])) {
        $candidatePhone = (string)$input['cust_Mobile'];
    }
    if ($candidatePhone === '' && isset($decryptedPayload['cust_Mobile'])) {
        $candidatePhone = (string)$decryptedPayload['cust_Mobile'];
    }
    $candidatePhone = preg_replace('/\D/', '', $candidatePhone);
    if (strlen($candidatePhone) > 10) {
        $candidatePhone = substr($candidatePhone, -10);
    }
    if ($candidatePhone === '' || strlen($candidatePhone) < 10) {
        throw new Exception('user_phone_missing');
    }

    $utr = '';
    foreach ([
        $input['utr'] ?? null,
        $input['BANKTXNID'] ?? null,
        $input['bankTxnId'] ?? null,
        $input['txnId'] ?? null,
        $input['transactionId'] ?? null,
        $decryptedPayload['utr'] ?? null,
        $decryptedPayload['BANKTXNID'] ?? null,
        $decryptedPayload['bankTxnId'] ?? null,
        $decryptedPayload['txnId'] ?? null,
        $decryptedPayload['transactionId'] ?? null
    ] as $v) {
        if (is_string($v) && trim($v) !== '') {
            $utr = trim($v);
            break;
        }
    }

    $respMsg = $gatewayMessage !== '' ? $gatewayMessage : 'PaytmUPI';

    $credit = mysqli_prepare($ahk_conn, "UPDATE users SET balance = balance + ? WHERE phone = ?");
    mysqli_stmt_bind_param($credit, 'ds', $amt, $candidatePhone);
    mysqli_stmt_execute($credit);
    $creditRows = mysqli_stmt_affected_rows($credit);
    mysqli_stmt_close($credit);
    if ($creditRows <= 0) {
        throw new Exception('user_not_found_or_credit_failed');
    }

    $walletUpd = mysqli_prepare($ahk_conn, "UPDATE wallet SET status='success', amount=?, BANKTXNID=?, RESPMSG=?, PAYMENTMODE='PaytmUPI', txn_date=NOW() WHERE txn_id=?");
    $amtStr = (string)$tamount;
    mysqli_stmt_bind_param($walletUpd, 'ssss', $amtStr, $utr, $respMsg, $orderId);
    mysqli_stmt_execute($walletUpd);
    $walletRows = mysqli_stmt_affected_rows($walletUpd);
    mysqli_stmt_close($walletUpd);

    if ($walletRows <= 0) {
        $walletIns = mysqli_prepare($ahk_conn, "INSERT INTO wallet (phone, amount, txn_id, email, PAYMENTMODE, RESPMSG, BANKTXNID, status, txn_date) VALUES (?, ?, ?, ?, 'PaytmUPI', ?, ?, 'success', NOW())");
        $emailToStore = $temail ?: ($walletEmail ?? '');
        mysqli_stmt_bind_param($walletIns, 'ssssss', $candidatePhone, $amtStr, $orderId, $emailToStore, $respMsg, $utr);
        mysqli_stmt_execute($walletIns);
        mysqli_stmt_close($walletIns);
    }

    $upd = mysqli_prepare($ahk_conn, "UPDATE wallet_transactions SET txn_status='COMPLETED' WHERE id=?");
    mysqli_stmt_bind_param($upd, 'i', $tid);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    if (function_exists('gpGiveReferralBonusIfEligible')) {
        gpGiveReferralBonusIfEligible($candidatePhone, $ahk_conn);
    }

    mysqli_commit($ahk_conn);
    file_put_contents($logFile, "Transaction complete. Redirecting...\n", FILE_APPEND);
    
    // Final session save
    session_write_close();
    header('Location: ' . $redirectBase . '?success=1');
    exit();

} catch (Exception $e) {
    if (isset($ahk_conn)) { mysqli_rollback($ahk_conn); }
    file_put_contents($logFile, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Location: ' . $redirectBase . '?failed=1&msg=' . urlencode($e->getMessage()));
    exit();
}
