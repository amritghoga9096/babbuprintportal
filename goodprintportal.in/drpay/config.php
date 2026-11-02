<?php
/**
 * DrPay (gateway.pdfgods.in) — Webhook config
 * -------------------------------------------
 * Gateway panel -> Webhooks se Webhook API URL aur Webhook Key yahan daalo.
 * Webhook URL (https://<CURRENT-DOMAIN>/drpay/webhook.php) apne aap register ho jata hai
 * jab koi wallet page kholta hai. Kuch aur configure nahi karna.
 *
 * Server-side only. Ye values kabhi browser me mat bhejo.
 */

if (!defined('DRPAY_WEBHOOK_KEY')) {
    // Gateway panel -> Webhooks -> Webhook API URL
    define('DRPAY_WEBHOOK_API', 'https://gateway.pdfgods.in/api/webhook');
    // Gateway panel -> Webhooks -> Webhook Key (signature verify bhi isi se hota hai)
    define('DRPAY_WEBHOOK_KEY', 'whk_1c81a0ee9c163d7d67d7bcb7527453fad8929f9cc1066f04');

    // PAYMENTMODE value stored in the `wallet` table (same as wallet2.php)
    define('DRPAY_PAYMENT_MODE', 'ApnaPayHere');

    // Optional: force the public URL of this module folder, e.g. 'https://example.com/drpay'.
    // Leave empty to auto-detect from the current domain (recommended).
    define('DRPAY_PUBLIC_BASE', '');

    // Webhook debug log. Empty string disables logging.
    define('DRPAY_LOG_FILE', __DIR__ . '/webhook.log');
}

require_once __DIR__ . '/functions.php';
