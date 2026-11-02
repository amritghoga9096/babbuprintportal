<?php
/**
 * VAPay (va.pdfgods.in) UPI Gateway — module config
 * --------------------------------------------------
 * Ye poora "vapay" folder kisi bhi domain ke root me copy karo, bas.
 * Webhook URL aur return URL apne aap current domain + folder se ban jaate hain.
 *
 * Server-side only. Ye values kabhi browser me mat bhejo.
 */

if (!defined('VAPAY_API_KEY')) {
    // Dashboard -> API Keys
    define('VAPAY_API_KEY', 'lk_live_1330cb911b2a241b99d0b5eb4881df4fe4aced32');
    // Dashboard -> API Keys -> Webhook secret
    define('VAPAY_WEBHOOK_SECRET', 'whsec_79ceaa5b38125c2f75d30426cdba4652ae42c9c3');
    // Gateway base URL (no trailing slash)
    define('VAPAY_BASE_URL', 'https://va.pdfgods.in');

    // PAYMENTMODE value stored in the `wallet` table for this gateway.
    define('VAPAY_PAYMENT_MODE', 'VAPay');
    // Minimum top-up amount (Rs).
    define('VAPAY_MIN_AMOUNT', 120);

    // Optional: force the public URL of this module folder, e.g. 'https://example.com/vapay'.
    // Leave empty to auto-detect from the current domain (recommended).
    define('VAPAY_PUBLIC_BASE', '');

    // Webhook debug log. Empty string disables logging.
    define('VAPAY_LOG_FILE', __DIR__ . '/webhook.log');
}

require_once __DIR__ . '/src/VapayClient.php';
require_once __DIR__ . '/src/WebhookVerifier.php';
require_once __DIR__ . '/src/functions.php';
