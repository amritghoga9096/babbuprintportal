<?php
/**
 * VAPay (va.pdfgods.in) Webhook Endpoint
 * --------------------------------------
 * webhook_url is sent automatically with every order:  https://<CURRENT-DOMAIN>/vapay/webhook.php
 * Nothing to configure per domain.
 *
 * payment.success  -> verify HMAC -> credit the pending wallet row ONCE (amount must match)
 * payment.expired / payment.failed -> mark the pending row rejected (a late payment.success still credits it)
 *
 * Always answers 200 for events that were handled or are duplicates, so the gateway stops retrying.
 */

@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
if (function_exists('ob_start') && !ob_get_level()) @ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/config.php';

function vapay_wh_respond($code, $msg)
{
    while (ob_get_level()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $msg;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Lets you open the URL in a browser to confirm it is reachable.
    vapay_wh_respond(200, 'VAPay webhook endpoint OK');
}

$rawBody = file_get_contents('php://input');
if ($rawBody === '' || $rawBody === false) {
    vapay_log('empty body');
    vapay_wh_respond(400, 'empty body');
}

$headers = function_exists('getallheaders') ? getallheaders() : array();
if (empty($headers) || !is_array($headers)) {
    $headers = array();
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $headers[str_replace('_', '-', substr($k, 5))] = $v;
        }
    }
}

try {
    $event = \VAPAY\WebhookVerifier::verify($rawBody, $headers, VAPAY_WEBHOOK_SECRET);
} catch (\Throwable $e) {
    vapay_log('signature verify failed: ' . $e->getMessage());
    vapay_wh_respond(401, 'invalid signature');
}

$webhookId = (string)($_SERVER['HTTP_X_WEBHOOK_ID'] ?? '');
$eventType = (string)($event['event'] ?? '');
$merchantOrderId = (string)($event['merchant_order_id'] ?? '');
$gwOrderId = (string)($event['order_id'] ?? '');

vapay_log('event=' . $eventType . ' merchant_order=' . $merchantOrderId . ' gw_order=' . $gwOrderId
    . ' amount=' . ($event['amount'] ?? '') . ' utr=' . ($event['utr'] ?? '') . ' webhook_id=' . $webhookId
    . ' attempt=' . ($event['attempt'] ?? ''));

if ($merchantOrderId === '' || !preg_match('/^[A-Za-z0-9_\-]{1,80}$/', $merchantOrderId)) {
    vapay_wh_respond(200, 'ok');
}

if (empty($ahk_conn)) {
    vapay_log('no DB connection');
    vapay_wh_respond(500, 'db unavailable');
}

if ($eventType === 'payment.success') {
    $paid = (isset($event['amount']) && is_numeric($event['amount'])) ? (float)$event['amount'] : null;
    $res = vapay_finalize_paid($ahk_conn, $merchantOrderId, $paid, (string)($event['utr'] ?? ''), (string)($event['paid_at'] ?? ''));
    vapay_log('payment.success ' . $merchantOrderId . ' => ' . $res['result'] . ' phone=' . $res['phone'] . ' amount=' . $res['amount']);

    if ($res['result'] === 'db_error') {
        // Let the gateway retry later.
        vapay_wh_respond(500, 'db error');
    }
    // credited / already / not_found (order of another site) / amount_mismatch (logged for manual check)
    vapay_wh_respond(200, 'ok');
}

if ($eventType === 'payment.expired' || $eventType === 'payment.failed') {
    vapay_mark_unpaid($ahk_conn, $merchantOrderId, substr($eventType, 8));
}

vapay_wh_respond(200, 'ok');
