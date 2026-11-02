<?php
/**
 * DrPay (gateway.pdfgod.in) Webhook Endpoint
 * ------------------------------------------
 * URL: https://<CURRENT-DOMAIN>/drpay/webhook.php  (wallet page khulte hi apne aap register hota hai)
 *
 * payment.success -> verify X-Webhook-Signature -> credit the pending wallet row ONCE (amount must match)
 * webhook.test    -> sirf 200 OK (kuch credit nahi hota)
 *
 * Handled / duplicate events ko hamesha 200 deta hai taaki gateway retry band kare.
 */

@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
if (function_exists('ob_start') && !ob_get_level()) @ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/config.php';

function drpay_wh_respond($code, $msg)
{
    while (ob_get_level()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $msg;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Browser me URL khol ke check kar sakte ho ki reachable hai.
    drpay_wh_respond(200, 'DrPay webhook endpoint OK');
}

$rawBody = file_get_contents('php://input');
if ($rawBody === '' || $rawBody === false) {
    drpay_log('empty body');
    drpay_wh_respond(400, 'empty body');
}

$signature = (string)($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
if (!drpay_verify_signature($rawBody, $signature)) {
    drpay_log('signature verify failed ip=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    drpay_wh_respond(401, 'invalid signature');
}

$event = array();
parse_str($rawBody, $event);

$eventType = (string)($_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? ($event['event'] ?? ''));
$status    = strtoupper((string)($event['status'] ?? ''));
$orderId   = (string)($event['order_id'] ?? '');
$amount    = $event['amount'] ?? ($event['result']['amount'] ?? null);
$utr       = (string)($event['utr'] ?? ($event['result']['utr'] ?? ''));
$paidAt    = (string)($event['date'] ?? ($event['result']['date'] ?? ''));

drpay_log('event=' . $eventType . ' status=' . $status . ' order=' . $orderId . ' amount=' . (is_scalar($amount) ? $amount : '') . ' utr=' . $utr);

if ($eventType === 'webhook.test' || $status === 'TEST') {
    drpay_wh_respond(200, 'ok');
}

// Sirf apne site ke wallet orders (WLT_...) — doosre site/module ke order ignore
if ($orderId === '' || !preg_match('/^[A-Za-z0-9_\-]{1,80}$/', $orderId)) {
    drpay_wh_respond(200, 'ok');
}

if (empty($ahk_conn)) {
    drpay_log('no DB connection');
    drpay_wh_respond(500, 'db unavailable');
}

if ($status === 'SUCCESS') {
    $paid = (is_scalar($amount) && is_numeric($amount)) ? (float)$amount : null;
    $res = drpay_finalize_paid($ahk_conn, $orderId, $paid, $utr, $paidAt);
    drpay_log('payment.success ' . $orderId . ' => ' . $res['result'] . ' phone=' . $res['phone'] . ' amount=' . $res['amount']);

    if ($res['result'] === 'db_error') {
        // Gateway baad me retry karega.
        drpay_wh_respond(500, 'db error');
    }
    // credited / already / not_found (doosre site ka order) / amount_mismatch (log me check karo)
    drpay_wh_respond(200, 'ok');
}

drpay_wh_respond(200, 'ok');
