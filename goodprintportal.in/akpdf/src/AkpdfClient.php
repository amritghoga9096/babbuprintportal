<?php

namespace AKPDF;

use InvalidArgumentException;
use RuntimeException;

/**
 * AutoUPI (akpdf.site) API client.
 * Backend-only. Never expose the API key to the browser.
 */
final class AkpdfClient
{
    /** Official base URL (no trailing slash). */
    private $baseUrl;
    /** API key used in Authorization: Bearer header. */
    private $apiKey;
    /** Request timeout in seconds. */
    private $timeoutSeconds;

    /**
     * @param string $apiKey AutoUPI API key (lk_live_...).
     * @param string $baseUrl Override for testing only.
     * @param int    $timeoutSeconds 1-120.
     */
    public function __construct($apiKey, $baseUrl = 'https://www.akpdf.site', $timeoutSeconds = 20)
    {
        if (!is_string($apiKey) || $apiKey === '') {
            throw new InvalidArgumentException('AutoUPI API key is required.');
        }
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new InvalidArgumentException('AutoUPI base URL is required.');
        }
        if (!is_int($timeoutSeconds) || $timeoutSeconds < 1 || $timeoutSeconds > 120) {
            throw new InvalidArgumentException('Timeout must be between 1 and 120 seconds.');
        }
        if (!extension_loaded('curl')) {
            throw new RuntimeException('PHP cURL extension required.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Create a payment order.
     *
     * @param string $merchantOrderId Your unique order id (used for idempotency).
     * @param string $amount          Amount in INR, e.g. "100.00".
     * @param array  $options         success_url, failure_url, webhook_url, customer_email.
     * @return array Gateway order JSON.
     */
    public function createOrder($merchantOrderId, $amount, $options = array())
    {
        $this->validateOrderId($merchantOrderId);
        $this->validateAmount($amount);
        $this->validateOptions($options);

        $payload = array(
            'amount'           => (float)$amount,
            'merchant_order_id' => $merchantOrderId,
            'success_url'      => isset($options['success_url']) ? $options['success_url'] : '',
            'failure_url'      => isset($options['failure_url']) ? $options['failure_url'] : '',
            'webhook_url'      => isset($options['webhook_url']) ? $options['webhook_url'] : '',
        );
        if (!empty($options['customer_email'])) {
            $payload['customer'] = array('email' => $options['customer_email']);
        }

        return $this->request('POST', '/api/public/v1/orders', $payload);
    }

    /**
     * Retrieve the current status of an order.
     *
     * @param string $orderId Gateway order_id (PANME-...) or merchant_order_id.
     * @return array Order status JSON.
     */
    public function status($orderId)
    {
        $this->validateOrderId($orderId);
        return $this->request('GET', '/api/public/v1/orders/' . rawurlencode($orderId));
    }

    /**
     * Whether the order is confirmed paid. AutoUPI returns status "paid".
     *
     * @param array $status Decoded status response.
     * @return bool
     */
    public static function isPaid($status)
    {
        if (!is_array($status)) return false;
        $s = isset($status['status']) ? strtolower(trim((string)$status['status'])) : '';
        return ($s === 'paid');
    }

    private function validateOrderId($value)
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9._\-]{1,120}$/D', $value)) {
            throw new InvalidArgumentException('Order ID must contain 1-120 letters, digits, dots, underscores or hyphens.');
        }
    }

    private function validateAmount($value)
    {
        if (!is_numeric($value) || (float)$value <= 0 || (float)$value > 9999999.0) {
            throw new InvalidArgumentException('Amount must be INR 1 to 9999999.');
        }
    }

    private function validateOptions($options)
    {
        if (!is_array($options)) {
            throw new InvalidArgumentException('Invalid order options.');
        }
        $allowed = array('success_url', 'failure_url', 'webhook_url', 'customer_email');
        foreach ($options as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                throw new InvalidArgumentException('Invalid order option: ' . (string)$name);
            }
            if ($value !== null && !is_string($value)) {
                throw new InvalidArgumentException('Order option value must be a string: ' . (string)$name);
            }
        }
    }

    /**
     * Raw HTTP request to the AutoUPI API.
     */
    public function request($method, $path, $payload = null)
    {
        $method = strtoupper((string)$method);
        if (!in_array($method, array('GET', 'POST'), true)) {
            throw new InvalidArgumentException('Unsupported HTTP method: ' . $method);
        }

        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Failed to initialize HTTP client.');
        }

        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        );

        $connTimeout = ($this->timeoutSeconds < 10) ? $this->timeoutSeconds : 10;
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $connTimeout,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        );

        if ($payload !== null) {
            $encoded = json_encode($payload);
            if ($encoded === false) {
                curl_close($handle);
                throw new AkpdfException('Failed to encode request JSON.');
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }

        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $curlErr = curl_errno($handle) ? curl_error($handle) : '';
        $httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new AkpdfException('Network error: ' . ($curlErr ? $curlErr : 'unknown cURL failure'));
        }

        $decoded = json_decode($body, true);
        if (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE) {
            throw new AkpdfException('AutoUPI returned invalid JSON.', $httpCode);
        }
        if (!is_array($decoded)) {
            throw new AkpdfException('AutoUPI returned an invalid response.', $httpCode);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errMsg = isset($decoded['error']) ? (string)$decoded['error']
                : (isset($decoded['message']) ? (string)$decoded['message']
                : 'AutoUPI request failed');
            throw new AkpdfException($errMsg, $httpCode);
        }

        return $decoded;
    }
}

final class AkpdfException extends \RuntimeException
{
    /** @var int */
    private $httpStatus;

    public function __construct($message, $httpStatus = 0, $previous = null)
    {
        $this->httpStatus = (int)$httpStatus;
        parent::__construct((string)$message, (int)$httpStatus, $previous);
    }

    public function httpStatus() { return (int)$this->httpStatus; }
}
