<?php
/**
 * AKQR Webhook Endpoint
 * ----------------------
 * URL to add in AKQR Dashboard -> Webhooks:
 *   https://YOUR-DOMAIN/akqr/webhook.php
 *
 * Steps:
 *  1. Put this exact URL in the "Your website webhook URL (HTTPS)" box.
 *  2. Save it. AKQR will show a "Signing secret".
 *  3. Copy that secret and paste it below in AKQR_WEBHOOK_SECRET.
 *
 * This endpoint receives signed events from AKQR. On order.paid it:
 *  - verifies the HMAC signature (raw body)
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
// Paste your AKQR "Signing secret" from the Webhooks dashboard here.
define('AKQR_WEBHOOK_SECRET', 'whsec_70d785fa6c0f23953019648208e53a45c920b41bcf06ab0c41f053472ddfe76e');
// Live API key (same as wallet.php)
define('AKQR_API_KEY', 'akqr_live_d886e9a8ab2bc9a4968aa732aad969aea3f7a66b1e822d3f7fb57f0a9ae4');
// Optional debug log (folder must be writable). Leave empty to disable.
define('AKQR_WEBHOOK_LOG', __DIR__ . '/webhook.log');
// ==================================================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/src/AkqrClient.php';
require_once __DIR__ . '/src/WebhookVerifier.php';

use AKQR\WebhookVerifier;
use AKQR\AkqrClient;

function akqr_wh_log($msg) {
    if (AKQR_WEBHOOK_LOG === '') return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($msg) ? $msg : json_encode($msg)) . "\n";
    @file_put_contents(AKQR_WEBHOOK_LOG, $line, FILE_APPEND | LOCK_EX);
}

function akqr_wh_respond($code, $ok) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array('ok' => $ok));
    exit;
}

/**
 * Forward the raw signed webhook payload to all other domains running this same
 * module. AKQR only lets you configure ONE webhook URL, so the primary endpoint
 * fans the event out to every sister site. Each site credits its OWN wallet.
 *
 * Loop prevention: if the incoming request already carries X-Webhook-Forwarded,
 * this instance was itself reached via forwarding — do not forward again.
 */
function akqr_wh_forward($rawBody, $headers) {
    $normalized = array();
    foreach ($headers as $k => $v) {
        $normalized[strtolower((string)$k)] = is_array($v) ? (string)reset($v) : (string)$v;
    }
    // Already forwarded by another site -> stop
    if (!empty($normalized['x-webhook-forwarded'])) return;

    // ALL domains running this same AKQR module (webhook.php at /akqr/webhook.php).
    // The current domain is auto-excluded below, so the SAME file works on every
    // site without edits — whichever one AKQR calls becomes the forwarder.
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

    // Carry over the AKQR signature headers so each receiver can verify the HMAC.
    $passthru = array();
    foreach (array('x-akqr-timestamp', 'x-akqr-signature') as $h) {
        if (!empty($normalized[$h])) $passthru[$h] = $normalized[$h];
    }

    $curlHeaders = array(
        'Content-Type: application/json',
        'X-Webhook-Forwarded: 1',
    );
    foreach ($passthru as $k => $v) $curlHeaders[] = $k . ': ' . $v;

    foreach ($otherDomains as $domain) {
        $url = rtrim($domain, '/') . '/akqr/webhook.php';
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
        akqr_wh_log('forward -> ' . $domain . ' => HTTP ' . $httpCode . ($err ? ' err=' . $err : ''));
    }
}

function akqr_wh_extract_amount($status, $fallback = '') {
    if (!is_array($status)) return (string)$fallback;
    foreach (array('amount','order_amount','total_amount') as $k) {
        if (isset($status[$k]) && $status[$k] !== '' && $status[$k] !== null) return (string)$status[$k];
    }
    if (isset($status['payment']['amount']) && $status['payment']['amount'] !== '') return (string)$status['payment']['amount'];
    return (string)$fallback;
}

function akqr_wh_extract_utr($status, $fallback = '') {
    if (!is_array($status)) return $fallback;
    $keys = array('utr','provider_ref','provider_txn_id','provider_reference','bank_ref','bank_txn_id',
        'bank_reference','transaction_id','txn_id','payment_id','payment_ref','gateway_txn_id','rrn',
        'external_ref','imps_ref','upi_transaction_id');
    foreach (array($status, $status['provider'] ?? array(), $status['payment'] ?? array()) as $src) {
        if (!is_array($src)) continue;
        foreach ($keys as $k) {
            if (!empty($src[$k]) && is_string($src[$k])) return $src[$k];
        }
    }
    return $fallback;
}

function akqr_wh_extract_datetime($status) {
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

// order_id format created by wallet.php: WLT_{phone}_{timestamp}
function akqr_wh_phone_from_order($orderId) {
    if (!is_string($orderId)) return '';
    if (strpos($orderId, 'WLT_') === 0) {
        $parts = explode('_', $orderId);
        // WLT_phone_timestamp  -> phone is index 1
        if (count($parts) >= 3) return $parts[1];
    }
    return '';
}

akqr_wh_log('--- webhook hit method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ---');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    akqr_wh_respond(405, false);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === '' || $rawBody === false) {
    akqr_wh_log('empty body');
    akqr_wh_respond(400, false);
}

$headers = function_exists('getallheaders') ? getallheaders() : array();
if (empty($headers)) {
    // nginx / some servers
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }
}

// ---- signature verification ----
$secretConfigured = (AKQR_WEBHOOK_SECRET !== '' && AKQR_WEBHOOK_SECRET !== 'PASTE_YOUR_AKQR_SIGNING_SECRET_HERE');

if ($secretConfigured) {
    try {
        $event = WebhookVerifier::verify($rawBody, $headers, AKQR_WEBHOOK_SECRET);
    } catch (Throwable $e) {
        akqr_wh_log('signature verify failed: ' . $e->getMessage());
        akqr_wh_respond(400, false);
    }
} else {
    // Secret not set yet. We still require independent provider verification
    // via the status API below, so a forged event cannot credit a wallet.
    akqr_wh_log('WARNING: webhook signing secret not configured. Signature skipped; relying on status API.');
    $event = json_decode($rawBody, true);
    if (!is_array($event)) {
        akqr_wh_log('invalid JSON body');
        akqr_wh_respond(400, false);
    }
}

// ---- forward to all sister sites ----
// Runs for every valid signed event (order.paid and otherwise). Each receiver
// processes independently against its own DB; idempotency prevents double credit.
akqr_wh_forward($rawBody, $headers);

$eventId = (string)($event['id'] ?? '');
$eventType = (string)($event['type'] ?? '');
$orderId = (string)($event['order_id'] ?? '');

akqr_wh_log('event id=' . $eventId . ' type=' . $eventType . ' order=' . $orderId);

// We only act on order.paid. Acknowledge everything else so AKQR stops retrying.
if ($eventType !== 'order.paid') {
    akqr_wh_respond(200, true);
}

if ($orderId === '') {
    akqr_wh_log('order.paid without order_id');
    akqr_wh_respond(200, true);
}

// ---- independent provider verification ----
try {
    $akqr = new AkqrClient(AKQR_API_KEY);
    $status = $akqr->status($orderId);
} catch (Throwable $e) {
    akqr_wh_log('status API failed for ' . $orderId . ': ' . $e->getMessage());
    // 500 so AKQR retries later
    akqr_wh_respond(500, false);
}

if (!AkqrClient::isPaid($status)) {
    akqr_wh_log('order ' . $orderId . ' not provider-verified as paid. status=' . ($status['status'] ?? 'unknown'));
    akqr_wh_respond(200, true);
}

$amount = akqr_wh_extract_amount($status, '');
$utr = akqr_wh_extract_utr($status, $orderId);
$txnDate = akqr_wh_extract_datetime($status);

if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
    akqr_wh_log('order ' . $orderId . ' has invalid amount=' . var_export($amount, true));
    akqr_wh_respond(200, true);
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
    $phone = akqr_wh_phone_from_order($orderId);
}

if ($phone === '') {
    akqr_wh_log('could not resolve phone for order ' . $orderId);
    akqr_wh_respond(200, true);
}

// ---- idempotent fulfillment ----
// Atomically flip pending -> success. If someone else (session verify / retry)
// already did it, affected_rows = 0 and we skip the credit.
$credited = false;
if (!empty($ahk_conn)) {
    $upd = @mysqli_prepare($ahk_conn, "UPDATE wallet SET BANKTXNID=?, amount=?, RESPMSG='AKQR', PAYMENTMODE='AKQR', status='success', txn_date=? WHERE txn_id=? AND status <> 'success' LIMIT 1");
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
        $ins = @mysqli_prepare($ahk_conn, "INSERT INTO wallet (txn_id, phone, BANKTXNID, amount, RESPMSG, PAYMENTMODE, status, txn_date, date) VALUES (?, ?, ?, ?, 'AKQR', 'AKQR', 'success', ?, ?)");
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
    // credit wallet balance
    if (!empty($ahk_conn)) {
        $bal = @mysqli_prepare($ahk_conn, "UPDATE users SET balance = balance + ? WHERE phone = ?");
        if ($bal) {
            $amtFlt = (float)$amount;
            @mysqli_stmt_bind_param($bal, 'ds', $amtFlt, $phone);
            @mysqli_stmt_execute($bal);
            @mysqli_stmt_close($bal);
        }
    }
    // referral bonus
    if (function_exists('gpGiveReferralBonusIfEligible')) {
        try { gpGiveReferralBonusIfEligible($phone, $ahk_conn); } catch (Throwable $e) { akqr_wh_log('referral error: '.$e->getMessage()); }
    }
    akqr_wh_log('CREDITED order=' . $orderId . ' phone=' . $phone . ' amount=' . $amount . ' utr=' . $utr);
} else {
    akqr_wh_log('already processed order=' . $orderId . ' (skipped credit)');
}

akqr_wh_respond(200, true);
