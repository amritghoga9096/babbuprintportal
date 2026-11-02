<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/WebhookVerifier.php';
require_once __DIR__ . '/../src/AkqrClient.php';

use AKQR\WebhookVerifier;
use AKQR\AkqrClient;

try {
    $body = file_get_contents('php://input');
    $event = WebhookVerifier::verify($body ?: '', getallheaders(), getenv('AKQR_WEBHOOK_SECRET') ?: '');

    // IMPORTANT: insert event['id'] in your database using a UNIQUE constraint.
    // Ignore duplicate deliveries. For order.paid confirm with the status API.
    if (($event['type'] ?? '') === 'order.paid') {
        $akqr = new AkqrClient(getenv('AKQR_API_KEY') ?: '');
        $status = $akqr->status((string)$event['order_id']);
        if (!AkqrClient::isPaid($status)) throw new RuntimeException('Payment is not provider-verified.');
        // Mark the order paid in your own database, transactionally and once.
    }
    http_response_code(200);
    header('Content-Type: application/json');
    echo '{"ok":true}';
} catch (Throwable $error) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo '{"ok":false}';
}
