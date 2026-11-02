# AKQR Payment API v2 — Complete PHP SDK (PHP 8.1+)

Methods: `merchants()`, `createOrder()`, `setAmount()`, `status()`, `orders()` and `phonePeTransactions()`. Use `AkqrClient::isPaid()` to check an independently provider-verified payment. Includes strict error handling, TLS verification, timeout limits, order/amount validation, and HMAC-SHA256 signed webhook verification.

**Setup:** Enable PHP `curl` and `json`. Set `AKQR_API_KEY` from your AKQR dashboard in a server-only environment. Composer users can run `composer install` and include `vendor/autoload.php`.

```bash
export AKQR_API_KEY='your_live_key'
php client.php merchants
php client.php create 2.00
php client.php status AKQR20261009222958931A7BD4
php client.php orders 50 0
php client.php phonepe 20
```

```php
require 'src/AkqrClient.php';
$api = new \AKQR\AkqrClient(getenv('AKQR_API_KEY'));
$merchants = $api->merchants(true); // include linked UPI IDs
$order = $api->createOrder('SHOP' . strtoupper(bin2hex(random_bytes(6))), '1.00');
echo $order['payment']['checkout_url'];
$status = $api->status($order['order_id']);
if (\AKQR\AkqrClient::isPaid($status)) { /* fulfill once */ }
```

Optionally pass `['merchant_id' => '...', 'upi_id' => '...']` with `createOrder()`. Only VPAs linked to your connected merchant are accepted. A draft: `createOrder('SHOP-001')` then `setAmount('SHOP-001', '1.00')`. Existing payable orders cannot silently change amount/UPI.

**Webhook:** Place `examples/webhook.php` behind HTTPS, set `AKQR_WEBHOOK_SECRET`. Validate `X-AKQR-Timestamp` and `X-AKQR-Signature` against the **raw body**, deduplicate by signed event ID, confirm `order.paid` using `status()`. An `order.pending` webhook is **not proof of payment**. Never place API keys in a client app/browser.
