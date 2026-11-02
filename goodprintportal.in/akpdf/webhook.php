<?php
/**
 * AutoUPI (akpdf.site) Webhook Endpoint
 * --------------------------------------
 * URL to set in AutoUPI order creation (webhook_url):
 *   https://YOUR-DOMAIN/akpdf/webhook.php
 *
 * This endpoint receives signed events from AutoUPI. On payment.success it:
 *  - verifies the HMAC signature (X-Signature: t=<unix>,v1=<hex>)
 *  - confirms the payment independently via the status API
 *  - marks the pending wallet row as success (UTR, amount, date)
 *  - credits the user's wallet balance (ONCE, atomic)
 *  - awards the referral bonus if eligible
 *
 * Idempotent: duplicate deliveries / retries never double-credit.
 */

@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
if (function_exists('ob_start') && !ob_get_level()) @ob_start();

// ===================== CONFIG =====================================
// AutoUPI webhook secret (from API Keys dashboard)
define('AKPDF_WEBHOOK_SECRET', 'whsec_68545b3195a0b6acfcca5954ec6345d8041aa127cfd2eca2');
// Live API key (same as wallet.php)
define('AKPDF_API_KEY', 'lk_live_af1b6dc9ce2dac3b56c17b72bd3b31040ea9f7b37e8604ce');
// Optional debug log (folder must be writable). Leave empty to disable.
define('AKPDF_WEBHOOK_LOG', __DIR__ . '/webhook.log');
// ==================================================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/src/AkpdfClient.php';
require_once __DIR__ . '/src/WebhookVerifier.php';

use AKPDF\WebhookVerifier;
use AKPDF\AkpdfClient;

function akpdf_wh_log($msg) {
    if (AKPDF_WEBHOOK_LOG === '') return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($msg) ? $msg : json_encode($msg)) . "\n";
    @file_put_contents(AKPDF_WEBHOOK_LOG, $line, FILE_APPEND | LOCK_EX);
}

function akpdf_wh_respond($code, $ok) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array('ok' => $ok));
    exit;
}

/**
 * Forward the raw signed webhook payload to all other domains running this same
 * module. AutoUPI sends the webhook to the URL specified at order creation, so
 * the primary endpoint fans the event out to every sister site. Each site
 * credits its OWN wallet.
 *
 * Loop prevention: if the incoming request already carries X-Webhook-Forwarded,
 * this instance was itself reached via forwarding — do not forward again.
 */
function akpdf_wh_forward($rawBody, $headers) {
    $normalized = array();
    foreach ($headers as $k => $v) {
        $normalized[strtolower((string)$k)] = is_array($v) ? (string)reset($v) : (string)$v;
    }
    if (!empty($normalized['x-webhook-forwarded'])) return;

    $allDomains = array(
        'https://anuprint.com',
        'https://goodprintportal.in',
        'https://abprintportal.in',
        'https://indprints.com',
        'https://member.pkrprint.com',
    );

    $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $otherDomains = array();
    foreach ($allDomains as $d) {
        $host = strtolower(parse_url($d, PHP_URL_HOST));
        if ($host !== $currentHost) $otherDomains[] = $d;
    }

    // Carry over the AutoUPI signature header so each receiver can verify the HMAC.
    $passthru = array();
    if (!empty($normalized['x-signature'])) $passthru['x-signature'] = $normalized['x-signature'];

    $curlHeaders = array(
        'Content-Type: application/json',
        'X-Webhook-Forwarded: 1',
    );
    foreach ($passthru as $k => $v) $curlHeaders[] = $k . ': ' . $v;

    foreach ($otherDomains as $domain) {
        $url = rtrim($domain, '/') . '/akpdf/webhook.php';
        $ch = @curl_init($url);
        if (!$ch) continue;
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $resp = @curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        akpdf_wh_log('forward -> ' . $domain . ' => HTTP ' . $httpCode . ($err ? ' err=' . $err : ''));
    }
}

function akpdf_wh_extract_amount($status, $fallback = '') {
    if (!is_array($status)) return (string)$fallback;
    foreach (array('amount','order_amount','total_amount','payable_amount') as $k) {
        if (isset($status[$k]) && $status[$k] !== '' && $status[$k] !== null) return (string)$status[$k];
    }
    return (string)$fallback;
}

function akpdf_wh_extract_utr($status, $fallback = '') {
    if (!is_array($status)) return $fallback;
    $keys = array('utr','provider_ref','provider_txn_id','provider_reference','bank_ref','bank_txn_id',
        'bank_reference','transaction_id','txn_id','payment_id','payment_ref','gateway_txn_id','rrn',
        'external_ref','imps_ref','upi_transaction_id','order_id');
    foreach (array($status, $status['payment'] ?? array()) as $src) {
        if (!is_array($src)) continue;
        foreach ($keys as $k) {
            if (!empty($src[$k]) && is_string($src[$k])) return $src[$k];
        }
    }
    return $fallback;
}

function akpdf_wh_extract_datetime($status) {
    $cands = array('paid_at','confirmed_at','completed_at','success_at','updated_at','created_at','txn_date','payment_date','date');
    foreach (array($status, $status['payment'] ?? array()) as $src) {
        if (!is_array($src)) continue;
        foreach ($cands as $k) {
            if (!empty($src[$k]) && is_string($src[$k])) {
                $ts = strtotime($src[$k]);
                if ($ts > 0) return date('Y-m-d H:i:s', $ts);
            }
        }
    }
    return date('Y-m-d H:i:s');
}

// merchant_order_id format created by wallet.php: WLT_{phone}_{timestamp}
function akpdf_wh_phone_from_order($orderId) {
    if (!is_string($orderId)) return '';
    if (strpos($orderId, 'WLT_') === 0) {
        $parts = explode('_', $orderId);
        if (count($parts) >= 3) return $parts[1];
    }
    return '';
}

akpdf_wh_log('--- webhook hit method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ---');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    akpdf_wh_respond(405, false);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === '' || $rawBody === false) {
    akpdf_wh_log('empty body');
    akpdf_wh_respond(400, false);
}

$headers = function_exists('getallheaders') ? getallheaders() : array();
if (empty($headers)) {
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }
}

// ---- signature verification ----
$secretConfigured = (AKPDF_WEBHOOK_SECRET !== '' && AKPDF_WEBHOOK_SECRET !== 'PASTE_YOUR_AUTOUPI_WEBHOOK_SECRET_HERE');

if ($secretConfigured) {
    try {
        $event = WebhookVerifier::verify($rawBody, $headers, AKPDF_WEBHOOK_SECRET);
    } catch (Throwable $e) {
        akpdf_wh_log('signature verify failed: ' . $e->getMessage());
        akpdf_wh_respond(400, false);
    }
} else {
    akpdf_wh_log('WARNING: webhook signing secret not configured. Signature skipped; relying on status API.');
    $event = json_decode($rawBody, true);
    if (!is_array($event)) {
        akpdf_wh_log('invalid JSON body');
        akpdf_wh_respond(400, false);
    }
}

// ---- forward to all sister sites ----
akpdf_wh_forward($rawBody, $headers);

$eventType = (string)($event['event'] ?? '');
$gwOrderId   = (string)($event['order_id'] ?? '');          // gateway order_id (PANME-...)
$merchantOrderId = (string)($event['merchant_order_id'] ?? ''); // our txn_id (WLT_...)

akpdf_wh_log('event type=' . $eventType . ' gw_order=' . $gwOrderId . ' merchant_order=' . $merchantOrderId);

// We only act on payment.success. Acknowledge everything else so AutoUPI stops retrying.
if ($eventType !== 'payment.success') {
    akpdf_wh_respond(200, true);
}

// wallet row is matched by our merchant_order_id (txn_id).
$orderId = ($merchantOrderId !== '') ? $merchantOrderId : $gwOrderId;

if ($orderId === '') {
    akpdf_wh_log('payment.success without order_id');
    akpdf_wh_respond(200, true);
}

// ---- independent provider verification (use gateway order_id) ----
$verifyId = ($gwOrderId !== '') ? $gwOrderId : $orderId;
try {
    $akpdf = new AkpdfClient(AKPDF_API_KEY);
    $status = $akpdf->status($verifyId);
} catch (Throwable $e) {
    akpdf_wh_log('status API failed for ' . $verifyId . ': ' . $e->getMessage());
    akpdf_wh_respond(500, false);
}

if (!AkpdfClient::isPaid($status)) {
    akpdf_wh_log('order ' . $verifyId . ' not provider-verified as paid. status=' . ($status['status'] ?? 'unknown'));
    akpdf_wh_respond(200, true);
}

$amount = akpdf_wh_extract_amount($status, isset($event['amount']) ? (string)$event['amount'] : '');
$utr = akpdf_wh_extract_utr($status, $verifyId);
$txnDate = akpdf_wh_extract_datetime($status);

if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
    akpdf_wh_log('order ' . $orderId . ' has invalid amount=' . var_export($amount, true));
    akpdf_wh_respond(200, true);
}

// ---- resolve user phone ----
$phone = '';
$existingRowId = 0;
$existingStatus = '';
if (!empty($ahk_conn)) {
    $stmt = @mysqli_prepare($ahk_conn, "SELECT id, phone, status, BANKTXNID FROM wallet WHERE txn_id = ? LIMIT 1");
    if ($stmt) {
        @mysqli_stmt_bind_param($stmt, 's', $orderId);
        @mysqli_stmt_execute($stmt);
        @mysqli_stmt_bind_result($stmt, $existingRowId, $rowPhone, $existingStatus, $existingBankTxn);
        @mysqli_stmt_fetch($stmt);
        @mysqli_stmt_close($stmt);
        $phone = (string)$rowPhone;
    }
}

if ($phone === '') {
    $phone = akpdf_wh_phone_from_order($orderId);
}

if ($phone === '') {
    akpdf_wh_log('could not resolve phone for order ' . $orderId);
    akpdf_wh_respond(200, true);
}

// ---- idempotent fulfillment ----
$credited = false;
if (!empty($ahk_conn)) {
    $upd = @mysqli_prepare($ahk_conn, "UPDATE wallet SET BANKTXNID=?, amount=?, RESPMSG='AutoUPI', PAYMENTMODE='AutoUPI', status='success', txn_date=? WHERE txn_id=? AND status <> 'success' LIMIT 1");
    if ($upd) {
        $amtStr = (string)$amount;
        @mysqli_stmt_bind_param($upd, 'ssss', $utr, $amtStr, $txnDate, $orderId);
        @mysqli_stmt_execute($upd);
        $affected = (int)@mysqli_stmt_affected_rows($upd);
        @mysqli_stmt_close($upd);
        if ($affected > 0) {
            $credited = true;
        }
    }

    // If no pending row existed (edge case), insert a success row once.
    if (!$credited && $existingRowId == 0) {
        $ins = @mysqli_prepare($ahk_conn, "INSERT INTO wallet (txn_id, phone, BANKTXNID, amount, RESPMSG, PAYMENTMODE, status, txn_date, date) VALUES (?, ?, ?, ?, 'AutoUPI', 'AutoUPI', 'success', ?, ?)");
        if ($ins) {
            $today = date('Y-m-d');
            $amtStr = (string)$amount;
            @mysqli_stmt_bind_param($ins, 'ssssss', $orderId, $phone, $utr, $amtStr, $txnDate, $today);
            if (@mysqli_stmt_execute($ins)) $credited = true;
            @mysqli_stmt_close($ins);
        }
    }
}

if ($credited) {
    if (!empty($ahk_conn)) {
        $bal = @mysqli_prepare($ahk_conn, "UPDATE users SET balance = balance + ? WHERE phone = ?");
        if ($bal) {
            $amtFlt = (float)$amount;
            @mysqli_stmt_bind_param($bal, 'ds', $amtFlt, $phone);
            @mysqli_stmt_execute($bal);
            @mysqli_stmt_close($bal);
        }
    }
    if (function_exists('gpGiveReferralBonusIfEligible')) {
        try { gpGiveReferralBonusIfEligible($phone, $ahk_conn); } catch (Throwable $e) { akpdf_wh_log('referral error: '.$e->getMessage()); }
    }
    akpdf_wh_log('CREDITED order=' . $orderId . ' phone=' . $phone . ' amount=' . $amount . ' utr=' . $utr);
} else {
    akpdf_wh_log('already processed order=' . $orderId . ' (skipped credit)');
}

akpdf_wh_respond(200, true);
