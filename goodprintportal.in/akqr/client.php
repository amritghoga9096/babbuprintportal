<?php
declare(strict_types=1);
require_once __DIR__ . '/src/AkqrClient.php';

use AKQR\AkqrClient;
use AKQR\AkqrException;

function usage(): never
{
    fwrite(STDERR, <<<TXT
AKQR PHP SDK CLI
  php client.php merchants
  php client.php create AMOUNT [ORDER_ID]
  php client.php draft [ORDER_ID]
  php client.php set-amount ORDER_ID AMOUNT
  php client.php status ORDER_ID
  php client.php orders [LIMIT] [OFFSET]
  php client.php phonepe [LIMIT] [MERCHANT_ID]

Set AKQR_API_KEY in the server environment. The key is never accepted as a CLI argument.
TXT);
    exit(2);
}

function output(array $value): void
{
    $copy = $value;
    if (isset($copy['payment']['qr_png'])) $copy['payment']['qr_png'] = '[base64 PNG omitted]';
    echo json_encode($copy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}

$key = getenv('AKQR_API_KEY') ?: '';
if ($key === '') {
    fwrite(STDERR, "Set AKQR_API_KEY in your server environment. Never embed it in PHP source.\n");
    exit(1);
}

$command = $argv[1] ?? '';
try {
    $akqr = new AkqrClient($key);
    switch ($command) {
        case 'merchants': output($akqr->merchants(true)); break;
        case 'create':
            $amount = $argv[2] ?? null;
            if ($amount === null) usage();
            $orderId = $argv[3] ?? ('AKQR' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4))));
            output($akqr->createOrder($orderId, $amount));
            break;
        case 'draft':
            $orderId = $argv[2] ?? ('AKQR' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4))));
            output($akqr->createOrder($orderId));
            break;
        case 'set-amount':
            if (!isset($argv[2], $argv[3])) usage();
            output($akqr->setAmount($argv[2], $argv[3]));
            break;
        case 'status':
            if (!isset($argv[2])) usage();
            $status = $akqr->status($argv[2]); output($status);
            fwrite(STDOUT, AkqrClient::isPaid($status) ? "PROVIDER VERIFIED PAYMENT: YES\n" : "PROVIDER VERIFIED PAYMENT: NO — do not fulfill\n");
            break;
        case 'orders': output($akqr->orders((int)($argv[2] ?? 50), (int)($argv[3] ?? 0))); break;
        case 'phonepe': output($akqr->phonePeTransactions((int)($argv[2] ?? 50), $argv[3] ?? null)); break;
        default: usage();
    }
} catch (AkqrException $error) {
    fwrite(STDERR, "AKQR HTTP {$error->httpStatus()}: {$error->getMessage()}\n"); exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, "AKQR SDK: {$error->getMessage()}\n"); exit(1);
}
