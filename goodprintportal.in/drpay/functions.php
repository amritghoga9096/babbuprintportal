<?php
/**
 * DrPay shared helpers — used by wallet2.php (return / ajax check) and webhook.php.
 * All crediting goes through drpay_finalize_paid() so a payment is credited exactly once.
 */

if (!function_exists('drpay_log')) {

function drpay_log($msg)
{
    if (!defined('DRPAY_LOG_FILE') || DRPAY_LOG_FILE === '') return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($msg) ? $msg : json_encode($msg)) . "\n";
    @file_put_contents(DRPAY_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function drpay_scheme()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return 'https';
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return 'https';
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return 'https';
    return 'http';
}

function drpay_host()
{
    $h = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    return preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)$h);
}

/** Public URL of this module folder on the current domain, e.g. https://site.com/drpay */
function drpay_module_url()
{
    if (defined('DRPAY_PUBLIC_BASE') && DRPAY_PUBLIC_BASE !== '') return rtrim(DRPAY_PUBLIC_BASE, '/');

    $moduleDir = realpath(__DIR__);
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $path = '/' . basename(__DIR__);
    if ($moduleDir && $docRoot) {
        $m = rtrim(str_replace('\\', '/', $moduleDir), '/');
        $d = rtrim(str_replace('\\', '/', $docRoot), '/');
        if ($d !== '' && stripos($m, $d . '/') === 0) {
            $path = substr($m, strlen($d));
        }
    }
    return drpay_scheme() . '://' . drpay_host() . $path;
}

function drpay_webhook_url()
{
    return drpay_module_url() . '/webhook.php';
}

/**
 * Is domain ka webhook URL gateway pe register karo (Webhook API, action=register).
 * Ek baar register hone ke baad file me yaad rakhta hai — baar baar API call nahi hoti.
 * Domain / key badalne pe apne aap dobara register hota hai.
 */
function drpay_ensure_webhook_registered($force = false)
{
    $url = drpay_webhook_url();
    if (drpay_host() === '') return false;

    $cacheFile = __DIR__ . '/webhook_registered.json';
    $sig = md5(DRPAY_WEBHOOK_API . '|' . DRPAY_WEBHOOK_KEY);
    $cache = @json_decode((string)@file_get_contents($cacheFile), true);
    if (!is_array($cache)) $cache = array();

    if (!$force && isset($cache[$url]) && $cache[$url] === $sig) return true;
    // Cache file likh nahi paaye to bhi har request pe API na lage
    if (!$force && session_status() === PHP_SESSION_ACTIVE && (($_SESSION['drpay_wh_reg'][$url] ?? '') === $sig)) return true;

    $ch = curl_init(DRPAY_WEBHOOK_API);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array(
            'webhook_key' => DRPAY_WEBHOOK_KEY,
            'action'      => 'register',
            'webhook_url' => $url,
        )),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
    ));
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$resp, true);

    $ok = is_array($data) && !empty($data['status']);
    drpay_log('register ' . $url . ' => HTTP ' . $code . ' ' . substr((string)$resp, 0, 300));

    if ($ok) {
        $cache[$url] = $sig;
        @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['drpay_wh_reg'][$url] = $sig;
    }
    return $ok;
}

/** X-Webhook-Signature = HMAC-SHA256(raw body, Webhook Key) */
function drpay_verify_signature($rawBody, $signature)
{
    if (!is_string($signature) || $signature === '') return false;
    return hash_equals(hash_hmac('sha256', (string)$rawBody, DRPAY_WEBHOOK_KEY), strtolower(trim($signature)));
}

function drpay_datetime($value)
{
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        if ($ts > 0) return date('Y-m-d H:i:s', $ts);
    }
    return date('Y-m-d H:i:s');
}

/**
 * Mark a wallet row paid and credit the user's balance — ONCE.
 * Webhook, redirect verify aur ajax check teeno isi ko call karte hain, isliye double credit nahi hota.
 *
 * @param mysqli      $conn
 * @param string      $orderId     our txn_id (WLT_...)
 * @param float|null  $paidAmount  amount reported by the gateway; null = skip check
 * @param string      $utr
 * @param string      $paidAt
 * @return array ['result' => credited|already|not_found|amount_mismatch|db_error, 'amount' => float, 'phone' => string, 'utr' => string, 'date' => string]
 */
function drpay_finalize_paid($conn, $orderId, $paidAmount, $utr, $paidAt)
{
    $out = array('result' => 'db_error', 'amount' => 0.0, 'phone' => '', 'utr' => (string)$utr, 'date' => drpay_datetime($paidAt));
    if (!$conn || $orderId === '') return $out;
    @mysqli_report(MYSQLI_REPORT_OFF);

    @mysqli_begin_transaction($conn);

    $rowId = 0; $phone = ''; $rowAmount = ''; $rowStatus = '';
    $st = @mysqli_prepare($conn, "SELECT id, phone, amount, status FROM wallet WHERE txn_id = ? LIMIT 1 FOR UPDATE");
    if (!$st) { @mysqli_rollback($conn); return $out; }
    @mysqli_stmt_bind_param($st, 's', $orderId);
    @mysqli_stmt_execute($st);
    @mysqli_stmt_bind_result($st, $rowId, $phone, $rowAmount, $rowStatus);
    $found = @mysqli_stmt_fetch($st);
    @mysqli_stmt_close($st);

    if (!$found || !$rowId) {
        @mysqli_rollback($conn);
        $out['result'] = 'not_found';
        return $out;
    }

    $amount = round((float)$rowAmount, 2);
    $out['amount'] = $amount;
    $out['phone'] = (string)$phone;

    if (strtolower((string)$rowStatus) === 'success') {
        @mysqli_commit($conn);
        $out['result'] = 'already';
        return $out;
    }

    if ($paidAmount !== null && abs(round((float)$paidAmount, 2) - $amount) > 0.009) {
        @mysqli_rollback($conn);
        $out['result'] = 'amount_mismatch';
        return $out;
    }

    $utr = (string)$utr !== '' ? (string)$utr : $orderId;
    $out['utr'] = $utr;
    $mode = DRPAY_PAYMENT_MODE;
    $date = $out['date'];
    $rowIdInt = (int)$rowId;

    // status <> 'success' also covers rows marked rejected: a late payment is still credited.
    $upd = @mysqli_prepare($conn, "UPDATE wallet SET BANKTXNID = ?, RESPMSG = ?, PAYMENTMODE = ?, status = 'success', txn_date = ? WHERE id = ? AND status <> 'success' LIMIT 1");
    if (!$upd) { @mysqli_rollback($conn); return $out; }
    @mysqli_stmt_bind_param($upd, 'ssssi', $utr, $mode, $mode, $date, $rowIdInt);
    @mysqli_stmt_execute($upd);
    $affected = (int)@mysqli_stmt_affected_rows($upd);
    @mysqli_stmt_close($upd);

    if ($affected !== 1) {
        @mysqli_commit($conn);
        $out['result'] = 'already';
        return $out;
    }

    $bal = @mysqli_prepare($conn, "UPDATE users SET balance = balance + ? WHERE phone = ?");
    $balOk = false;
    if ($bal) {
        @mysqli_stmt_bind_param($bal, 'ds', $amount, $out['phone']);
        $balOk = @mysqli_stmt_execute($bal);
        @mysqli_stmt_close($bal);
    }
    if (!$balOk) {
        @mysqli_rollback($conn);
        return $out;
    }
    @mysqli_commit($conn);
    $out['result'] = 'credited';

    if (function_exists('gpGiveReferralBonusIfEligible')) {
        try { gpGiveReferralBonusIfEligible($out['phone'], $conn); } catch (\Throwable $e) { drpay_log('referral error: ' . $e->getMessage()); }
    }
    return $out;
}

/**
 * Gateway se order ka asli status pucho (check-order-status) aur SUCCESS ho to ek hi baar credit karo.
 * @return array ['state' => paid|pending|error, 'credit' => array|null, 'message' => string]
 */
function drpay_sync_order($conn, $apiBase, $userToken, $orderId)
{
    $ch = curl_init(rtrim($apiBase, '/') . '/check-order-status');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array('user_token' => $userToken, 'order_id' => $orderId)),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string)$resp, true);

    if (!is_array($data)) {
        return array('state' => 'error', 'credit' => null, 'message' => 'Invalid response from payment gateway');
    }
    if (($data['status'] ?? '') === 'COMPLETED' && ($data['result']['status'] ?? '') === 'SUCCESS') {
        $r = $data['result'];
        $paid = (isset($r['amount']) && is_numeric($r['amount'])) ? (float)$r['amount'] : null;
        $credit = drpay_finalize_paid($conn, $orderId, $paid, (string)($r['utr'] ?? ''), (string)($r['date'] ?? ''));
        drpay_log('sync ' . $orderId . ' => ' . $credit['result']);
        if ($credit['result'] === 'credited' || $credit['result'] === 'already') {
            return array('state' => 'paid', 'credit' => $credit, 'message' => 'Payment successful');
        }
        return array('state' => 'error', 'credit' => $credit, 'message' => 'Payment received but could not be credited (' . $credit['result'] . '). Contact support.');
    }
    return array('state' => 'pending', 'credit' => null, 'message' => (string)($data['message'] ?? 'Waiting for payment confirmation...'));
}

/**
 * wallet2.php ke liye: pending row nahi mili (purana order) to pehle bana do, phir ek hi baar credit.
 */
function drpay_finalize_with_fallback($conn, $orderId, $phone, $paidAmount, $utr, $paidAt)
{
    $res = drpay_finalize_paid($conn, $orderId, $paidAmount, $utr, $paidAt);
    if ($res['result'] !== 'not_found' || $paidAmount === null || $phone === '') return $res;

    $ins = @mysqli_prepare($conn, "INSERT INTO wallet (txn_id, phone, amount, PAYMENTMODE, RESPMSG, status, txn_date, date) VALUES (?, ?, ?, ?, 'Wallet Recharge Pending', 'pending', NOW(), ?)");
    if ($ins) {
        $amt = (string)$paidAmount;
        $mode = DRPAY_PAYMENT_MODE;
        $today = date('Y-m-d');
        @mysqli_stmt_bind_param($ins, 'sssss', $orderId, $phone, $amt, $mode, $today);
        @mysqli_stmt_execute($ins);
        @mysqli_stmt_close($ins);
    }
    return drpay_finalize_paid($conn, $orderId, $paidAmount, $utr, $paidAt);
}

}
