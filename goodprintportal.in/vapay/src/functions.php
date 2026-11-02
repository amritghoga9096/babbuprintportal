<?php
/**
 * VAPay shared helpers — used by wallet.php (return / fallback / ajax check) and webhook.php.
 * All crediting goes through vapay_finalize_paid() so a payment is credited exactly once.
 */

if (!function_exists('vapay_log')) {

function vapay_log($msg)
{
    if (!defined('VAPAY_LOG_FILE') || VAPAY_LOG_FILE === '') return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($msg) ? $msg : json_encode($msg)) . "\n";
    @file_put_contents(VAPAY_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function vapay_scheme()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return 'https';
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return 'https';
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return 'https';
    return 'http';
}

function vapay_host()
{
    $h = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    return preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)$h);
}

/** Public URL of this module folder on the current domain, e.g. https://site.com/vapay */
function vapay_module_url()
{
    if (defined('VAPAY_PUBLIC_BASE') && VAPAY_PUBLIC_BASE !== '') return rtrim(VAPAY_PUBLIC_BASE, '/');

    $moduleDir = realpath(dirname(__DIR__));
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $path = '/' . basename(dirname(__DIR__));
    if ($moduleDir && $docRoot) {
        $m = rtrim(str_replace('\\', '/', $moduleDir), '/');
        $d = rtrim(str_replace('\\', '/', $docRoot), '/');
        if ($d !== '' && stripos($m, $d . '/') === 0) {
            $path = substr($m, strlen($d));
        }
    }
    return vapay_scheme() . '://' . vapay_host() . $path;
}

function vapay_webhook_url()
{
    return vapay_module_url() . '/webhook.php';
}

/** URL of the page currently running (admin/wallet.php) — used as success/failure return URL. */
function vapay_return_url()
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/admin/wallet.php';
    return vapay_scheme() . '://' . vapay_host() . $script;
}

function vapay_client($timeoutSeconds = 15)
{
    return new \VAPAY\VapayClient(VAPAY_API_KEY, VAPAY_BASE_URL, (int)$timeoutSeconds);
}

function vapay_datetime($value)
{
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        if ($ts > 0) return date('Y-m-d H:i:s', $ts);
    }
    return date('Y-m-d H:i:s');
}

/**
 * Mark a wallet row paid and credit the user's balance — ONCE.
 *
 * @param mysqli      $conn
 * @param string      $merchantOrderId our txn_id (WLT_...)
 * @param float|null  $paidAmount      original order amount reported by the gateway (not payable_amount); null = skip check
 * @param string      $utr
 * @param string      $paidAt          gateway paid_at (ISO 8601)
 * @return array ['result' => credited|already|not_found|amount_mismatch|db_error, 'amount' => float, 'phone' => string, 'utr' => string, 'date' => string]
 */
function vapay_finalize_paid($conn, $merchantOrderId, $paidAmount, $utr, $paidAt)
{
    $out = array('result' => 'db_error', 'amount' => 0.0, 'phone' => '', 'utr' => (string)$utr, 'date' => vapay_datetime($paidAt));
    if (!$conn || $merchantOrderId === '') return $out;
    @mysqli_report(MYSQLI_REPORT_OFF);

    @mysqli_begin_transaction($conn);

    $rowId = 0; $phone = ''; $rowAmount = ''; $rowStatus = '';
    $st = @mysqli_prepare($conn, "SELECT id, phone, amount, status FROM wallet WHERE txn_id = ? LIMIT 1 FOR UPDATE");
    if (!$st) { @mysqli_rollback($conn); return $out; }
    @mysqli_stmt_bind_param($st, 's', $merchantOrderId);
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

    $utr = (string)$utr !== '' ? (string)$utr : $merchantOrderId;
    $out['utr'] = $utr;
    $mode = VAPAY_PAYMENT_MODE;
    $date = $out['date'];
    $rowIdInt = (int)$rowId;

    // status <> 'success' also covers rows marked rejected/expired: a late payment is still credited.
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
        try { gpGiveReferralBonusIfEligible($out['phone'], $conn); } catch (\Throwable $e) { vapay_log('referral error: ' . $e->getMessage()); }
    }
    return $out;
}

/** Order expired/failed at the gateway: mark a still-pending row rejected. A later paid event still credits it. */
function vapay_mark_unpaid($conn, $merchantOrderId, $gatewayStatus)
{
    if (!$conn || $merchantOrderId === '') return;
    $reason = 'Payment ' . ucfirst((string)$gatewayStatus);
    $st = @mysqli_prepare($conn, "UPDATE wallet SET status = 'rejected', RESPMSG = ? WHERE txn_id = ? AND status = 'pending' LIMIT 1");
    if ($st) {
        @mysqli_stmt_bind_param($st, 'ss', $reason, $merchantOrderId);
        @mysqli_stmt_execute($st);
        @mysqli_stmt_close($st);
    }
}

/**
 * Ask the gateway for the real status and settle our wallet row accordingly.
 * @return array ['state' => paid|pending|unpaid|error, 'gw_status' => string, 'credit' => array|null, 'message' => string]
 */
function vapay_sync_order($conn, $client, $merchantOrderId)
{
    try {
        $st = $client->status($merchantOrderId);
    } catch (\Throwable $e) {
        return array('state' => 'error', 'gw_status' => '', 'credit' => null, 'message' => $e->getMessage());
    }
    $gw = \VAPAY\VapayClient::statusOf($st);
    if ($gw === 'paid') {
        $paid = isset($st['amount']) && is_numeric($st['amount']) ? (float)$st['amount'] : null;
        $credit = vapay_finalize_paid($conn, $merchantOrderId, $paid, (string)($st['utr'] ?? ''), (string)($st['paid_at'] ?? ''));
        if ($credit['result'] === 'credited' || $credit['result'] === 'already') {
            return array('state' => 'paid', 'gw_status' => $gw, 'credit' => $credit, 'message' => 'Payment successful');
        }
        vapay_log('sync ' . $merchantOrderId . ' paid but not credited: ' . $credit['result']);
        return array('state' => 'error', 'gw_status' => $gw, 'credit' => $credit, 'message' => 'Payment received but could not be credited (' . $credit['result'] . '). Contact support.');
    }
    if ($gw === 'expired' || $gw === 'failed') {
        vapay_mark_unpaid($conn, $merchantOrderId, $gw);
        return array('state' => 'unpaid', 'gw_status' => $gw, 'credit' => null, 'message' => 'Payment ' . ucfirst($gw));
    }
    // pending / manual_review / unknown
    return array('state' => 'pending', 'gw_status' => $gw, 'credit' => null, 'message' => 'Waiting for payment confirmation...');
}

}
