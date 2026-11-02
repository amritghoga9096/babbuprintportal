<?php

namespace AKQR;

use InvalidArgumentException;
use RuntimeException;

/** AKQR API v2 client — backward compatible down to PHP 5.4. Use from backend only, never browser JS. */
final class AkqrClient
{
    static $ORDER_ID_PATTERN = '/^[A-Za-z0-9._-]{1,100}$/D';
    static $AMOUNT_PATTERN = '/^[1-9][0-9]{0,6}(?:\.[0-9]{1,2})?$/D';

    private $apiKey;
    private $baseUrl;
    private $timeoutSeconds;

    public function __construct($apiKey, $baseUrl = 'https://akqr.in/api/v2', $timeoutSeconds = 20)
    {
        if (!is_string($apiKey) || !preg_match('/^(?:akqr_live_|skp_test_)[a-f0-9]{40,80}$/D', $apiKey)) {
            throw new InvalidArgumentException('Invalid AKQR API key format.');
        }
        if (!is_string($baseUrl) || $baseUrl !== 'https://akqr.in/api/v2') {
            throw new InvalidArgumentException('Only the official HTTPS AKQR v2 API is allowed.');
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

    /** Return all owner-connected merchant accounts; opt in to full provider-linked UPI IDs. */
    public function merchants($includeUpiIds = false)
    {
        return $this->request('GET', '/merchants' . ($includeUpiIds ? '?include_upi_ids=1' : ''));
    }

    /** $amount=null creates a DRAFT; e.g. "1.00" creates a payable ₹1 order. */
    public function createOrder($orderId, $amount = null, $options = array())
    {
        $this->validateOrderId($orderId);
        if ($amount !== null) {
            $this->validateAmount($amount);
        }
        $this->validateOptions($options);
        $payload = $options;
        $payload['order_id'] = $orderId;
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }
        return $this->request('POST', '/orders', $payload);
    }

    /** Add an amount to a draft, or re-check an existing immutable order. */
    public function setAmount($orderId, $amount, $options = array())
    {
        $this->validateOrderId($orderId);
        $this->validateAmount($amount);
        $this->validateOptions($options);
        $payload = $options;
        $payload['amount'] = $amount;
        return $this->request('PATCH', '/orders/' . rawurlencode($orderId), $payload);
    }

    public function status($orderId)
    {
        $this->validateOrderId($orderId);
        return $this->request('GET', '/orders/' . rawurlencode($orderId) . '/status');
    }

    /** Owner-scoped AKQR order history with truthful provider verification state. */
    public function orders($limit = 50, $offset = 0)
    {
        if (!is_int($limit) || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Limit must be between 1 and 100.');
        }
        if (!is_int($offset) || $offset < 0 || $offset > 10000) {
            throw new InvalidArgumentException('Offset must be between 0 and 10000.');
        }
        $query = array('limit' => $limit, 'offset' => $offset);
        return $this->request('GET', '/orders?' . http_build_query($query, '', '&'));
    }

    /** Recent transactions from connected PhonePe Business merchant ledgers. */
    public function phonePeTransactions($limit = 50, $merchantId = null)
    {
        if (!is_int($limit) || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Limit must be between 1 and 100.');
        }
        if ($merchantId !== null && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $merchantId)) {
            throw new InvalidArgumentException('Invalid PhonePe merchant ID.');
        }
        $query = array('limit' => $limit);
        if ($merchantId !== null) {
            $query['merchant_id'] = $merchantId;
        }
        return $this->request('GET', '/phonepe/transactions?' . http_build_query($query, '', '&'));
    }

    /** Only verified provider-backed SUCCESS is payment proof. */
    public static function isPaid($status)
    {
        if (!is_array($status)) return false;
        $statusOk = (isset($status['status']) && $status['status'] === 'SUCCESS');
        $verifiedOk = (isset($status['verified']) && $status['verified'] === true);
        $confirmedOk = (isset($status['payment_confirmed']) && $status['payment_confirmed'] === true);
        return $statusOk && $verifiedOk && $confirmedOk;
    }

    private function validateOrderId($value)
    {
        if (!is_string($value) || !preg_match(self::$ORDER_ID_PATTERN, $value)) {
            throw new InvalidArgumentException('Order ID must contain 1-100 letters, digits, dots, underscores or hyphens.');
        }
    }

    private function validateAmount($value)
    {
        if (!is_string($value) || !preg_match(self::$AMOUNT_PATTERN, $value) || (float)$value > 9999999.0) {
            throw new InvalidArgumentException('Amount must be INR 1.00 to 9999999.00 with up to two decimals.');
        }
    }

    private function validateOptions($options)
    {
        if (!is_array($options)) {
            throw new InvalidArgumentException('Invalid order options.');
        }
        $allowed = array('merchant_id', 'upi_id', 'mobile', 'name', 'payer_email', 'callback_url', 'redirect_url');
        foreach ($options as $name => $value) {
            if (!in_array($name, $allowed, true) || !is_string($value)) {
                throw new InvalidArgumentException('Invalid order option: ' . (string)$name);
            }
        }
    }

    /** Raw request, preserved for advanced integrations. */
    public function request($method, $path, $payload = null)
    {
        $allowedPath = preg_match('~^/merchants(?:\?include_upi_ids=1)?$~D', $path)
            || preg_match('~^/orders(?:\?limit=[1-9][0-9]{0,2}&offset=(?:0|[1-9][0-9]{0,4}))?$~D', $path)
            || preg_match('~^/orders/[A-Za-z0-9._%-]{1,300}(?:/status)?$~D', $path)
            || preg_match('~^/phonepe/transactions\?limit=[1-9][0-9]{0,2}(?:&merchant_id=[0-9a-f-]{36})?$~Di', $path);
        $methodsOk = array('GET', 'POST', 'PATCH');
        if (!in_array($method, $methodsOk, true) || !$allowedPath) {
            throw new InvalidArgumentException('Unsupported AKQR API method or endpoint.');
        }
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Failed to initialize HTTP client.');
        }
        $headers = array('x-smilepay-key: ' . $this->apiKey, 'Accept: application/json', 'Content-Type: application/json');
        $connTimeout = ($this->timeoutSeconds < 10) ? $this->timeoutSeconds : 10;
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $connTimeout,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        );
        if ($payload !== null) {
            if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
                $jsonOptions = (defined('JSON_UNESCAPED_SLASHES')) ? JSON_UNESCAPED_SLASHES : 0;
                $encoded = json_encode($payload, $jsonOptions);
            } else {
                $encoded = json_encode($payload);
            }
            if ($encoded === false) {
                curl_close($handle);
                throw new AkqrException('Failed to encode request JSON.', 0);
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $curlErr = curl_errno($handle) ? curl_error($handle) : '';
        $httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new AkqrException('Network error: ' . ($curlErr ? $curlErr : 'unknown cURL failure'), 0);
        }

        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            $decoded = json_decode($body, true, 512, (defined('JSON_THROW_ON_ERROR') ? 0 : 0));
        } else {
            $decoded = json_decode($body, true);
        }

        if (function_exists('json_last_error')) {
            $jsonErrCode = json_last_error();
            if ($jsonErrCode !== 0) {
                $jsonErrMsg = function_exists('json_last_error_msg') ? json_last_error_msg() : ('code #'.$jsonErrCode);
                throw new AkqrException('AKQR returned invalid JSON ('.$jsonErrMsg.').', $httpCode);
            }
        } elseif ($decoded === null && trim($body) !== 'null') {
            throw new AkqrException('AKQR returned invalid JSON.', $httpCode);
        }

        if (!is_array($decoded)) {
            throw new AkqrException('AKQR returned an invalid response.', $httpCode);
        }

        $okFlag = isset($decoded['ok']) ? $decoded['ok'] : true;
        if ($httpCode < 200 || $httpCode >= 300 || $okFlag === false) {
            $errMsg = isset($decoded['error']) ? (string)$decoded['error'] : 'AKQR request failed';
            throw new AkqrException($errMsg, $httpCode);
        }
        return $decoded;
    }
}

final class AkqrException extends \RuntimeException
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
