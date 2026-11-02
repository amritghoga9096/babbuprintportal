<?php

namespace VAPAY;

use InvalidArgumentException;
use RuntimeException;

/**
 * VAPay UPI Gateway (va.pdfgods.in) API client.
 * Backend-only. Never expose the API key to the browser.
 *
 * Endpoints:
 *   POST /api/v1/orders                       create order
 *   GET  /api/v1/orders/{order_id|merchant_order_id}  order status
 * Falls back to /api.php?r=/v1/... if the pretty URL is not routed.
 */
final class VapayClient
{
    private $baseUrl;
    private $apiKey;
    private $timeoutSeconds;

    public function __construct($apiKey, $baseUrl = 'https://va.pdfgods.in', $timeoutSeconds = 20)
    {
        if (!is_string($apiKey) || $apiKey === '') {
            throw new InvalidArgumentException('VAPay API key is required.');
        }
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new InvalidArgumentException('VAPay base URL is required.');
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
     * @param string $merchantOrderId Your unique order id (letters, digits, _ -, max 80).
     * @param string $amount          Amount in INR (min 1, max 2 decimals).
     * @param array  $options         success_url, failure_url, webhook_url, description,
     *                                customer_email, customer_phone, customer_name.
     * @return array Gateway order JSON (order_id, payment_url, upi_uri, payable_amount, ...).
     */
    public function createOrder($merchantOrderId, $amount, $options = array())
    {
        $this->validateOrderId($merchantOrderId);
        $this->validateAmount($amount);
        $this->validateOptions($options);

        $payload = array(
            'amount'            => round((float)$amount, 2),
            'merchant_order_id' => $merchantOrderId,
        );
        foreach (array('success_url', 'failure_url', 'webhook_url', 'description') as $k) {
            if (!empty($options[$k])) $payload[$k] = $options[$k];
        }
        $customer = array();
        if (!empty($options['customer_email'])) $customer['email'] = $options['customer_email'];
        if (!empty($options['customer_phone'])) $customer['phone'] = $options['customer_phone'];
        if (!empty($options['customer_name']))  $customer['name']  = $options['customer_name'];
        if ($customer) $payload['customer'] = $customer;

        return $this->request('POST', '/v1/orders', $payload);
    }

    /**
     * Current status of an order (gateway order_id or our merchant_order_id).
     */
    public function status($orderId)
    {
        $this->validateOrderId($orderId);
        return $this->request('GET', '/v1/orders/' . rawurlencode($orderId));
    }

    public static function isPaid($status)
    {
        return is_array($status) && strtolower(trim((string)($status['status'] ?? ''))) === 'paid';
    }

    /** Lower-cased status string ("pending", "paid", "expired", "failed", "manual_review"). */
    public static function statusOf($status)
    {
        return is_array($status) ? strtolower(trim((string)($status['status'] ?? ''))) : '';
    }

    private function validateOrderId($value)
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_\-]{1,80}$/D', $value)) {
            throw new InvalidArgumentException('Order ID must be 1-80 letters, digits, _ or -.');
        }
    }

    private function validateAmount($value)
    {
        if (!is_numeric($value) || (float)$value < 1 || (float)$value > 9999999.0) {
            throw new InvalidArgumentException('Amount must be INR 1 to 9999999.');
        }
    }

    private function validateOptions($options)
    {
        if (!is_array($options)) {
            throw new InvalidArgumentException('Invalid order options.');
        }
        $allowed = array('success_url', 'failure_url', 'webhook_url', 'description',
            'customer_email', 'customer_phone', 'customer_name');
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
     * Raw API call. $path is relative to /api, e.g. "/v1/orders".
     * Tries the pretty URL first, then api.php?r= if the server answers 404 with non-JSON.
     */
    public function request($method, $path, $payload = null)
    {
        $method = strtoupper((string)$method);
        if (!in_array($method, array('GET', 'POST'), true)) {
            throw new InvalidArgumentException('Unsupported HTTP method: ' . $method);
        }

        $res = $this->send($method, $this->baseUrl . '/api' . $path, $payload);
        if ($res['code'] === 404 && !is_array($res['json'])) {
            $res = $this->send($method, $this->baseUrl . '/api.php?r=' . $path, $payload);
        }

        $decoded = $res['json'];
        if (!is_array($decoded)) {
            throw new VapayException('VAPay returned an invalid response (HTTP ' . $res['code'] . ').', $res['code']);
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            $code = '';
            $msg = 'VAPay request failed';
            if (isset($decoded['error']) && is_array($decoded['error'])) {
                $code = (string)($decoded['error']['code'] ?? '');
                $msg  = (string)($decoded['error']['message'] ?? $msg);
            } elseif (isset($decoded['error'])) {
                $msg = (string)$decoded['error'];
            } elseif (isset($decoded['message'])) {
                $msg = (string)$decoded['message'];
            }
            throw new VapayException($msg, $res['code'], $code);
        }
        return $decoded;
    }

    private function send($method, $url, $payload)
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Failed to initialize HTTP client.');
        }
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ),
            CURLOPT_CONNECTTIMEOUT => min($this->timeoutSeconds, 10),
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        );
        if ($payload !== null) {
            $encoded = json_encode($payload);
            if ($encoded === false) {
                curl_close($handle);
                throw new VapayException('Failed to encode request JSON.');
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        // Some shared hosts ship an outdated CA bundle; retry once without peer verification.
        if ($body === false && in_array(curl_errno($handle), array(60, 77), true)) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
            $body = curl_exec($handle);
        }
        $curlErr = curl_errno($handle) ? curl_error($handle) : '';
        $httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new VapayException('Network error: ' . ($curlErr ? $curlErr : 'unknown cURL failure'));
        }
        return array('code' => $httpCode, 'json' => json_decode((string)$body, true));
    }
}

final class VapayException extends RuntimeException
{
    private $httpStatus;
    private $errorCode;

    public function __construct($message, $httpStatus = 0, $errorCode = '', $previous = null)
    {
        $this->httpStatus = (int)$httpStatus;
        $this->errorCode = (string)$errorCode;
        parent::__construct((string)$message, (int)$httpStatus, $previous);
    }

    public function httpStatus() { return $this->httpStatus; }

    /** API error code, e.g. "subscription_inactive", "amount_busy". */
    public function errorCode() { return $this->errorCode; }
}
