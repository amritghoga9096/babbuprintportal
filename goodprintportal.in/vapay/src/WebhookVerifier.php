<?php

namespace VAPAY;

use InvalidArgumentException;

/**
 * Verify VAPay webhook signatures and signed return URLs.
 *
 * Webhook header: X-Signature: t=<unix_timestamp>,v1=<hex_sha256>
 * Signed string:  "<t>.<raw_body>"   (HMAC-SHA256 with the webhook secret)
 *
 * Return URL:     ?order_id&merchant_order_id&status&ts&signature
 * Signed string:  "order_id|merchant_order_id|status|ts"
 */
final class WebhookVerifier
{
    /**
     * @return array Decoded webhook event.
     * @throws InvalidArgumentException if signature is missing/stale/invalid.
     */
    public static function verify($rawBody, array $headers, $secret, $maxSkewSeconds = 300)
    {
        if (!is_string($rawBody) || $rawBody === '') {
            throw new InvalidArgumentException('Empty webhook body.');
        }
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('VAPay webhook secret is missing.');
        }

        $normalized = array();
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string)$key)] = is_array($value) ? (string)reset($value) : (string)$value;
        }
        $sigHeader = $normalized['x-signature'] ?? '';
        if ($sigHeader === '') {
            throw new InvalidArgumentException('Missing X-Signature header.');
        }

        $t = '';
        $v1 = '';
        foreach (explode(',', $sigHeader) as $part) {
            $part = trim($part);
            if (strpos($part, '=') === false) continue;
            list($k, $v) = explode('=', $part, 2);
            $k = trim($k);
            if ($k === 't')  $t = trim($v);
            if ($k === 'v1') $v1 = trim($v);
        }

        if ($t === '' || !ctype_digit($t)) {
            throw new InvalidArgumentException('Missing or malformed webhook timestamp.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $v1)) {
            throw new InvalidArgumentException('Missing or malformed webhook signature.');
        }
        if (abs(time() - (int)$t) > $maxSkewSeconds) {
            throw new InvalidArgumentException('Webhook payload is stale (timestamp skew).');
        }

        $expected = hash_hmac('sha256', $t . '.' . $rawBody, $secret);
        if (!hash_equals($expected, strtolower($v1))) {
            throw new InvalidArgumentException('VAPay webhook signature mismatch.');
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            throw new InvalidArgumentException('Invalid webhook JSON body.');
        }
        return $event;
    }

    /**
     * Check the signature VAPay adds to success_url / failure_url.
     * Only proves the redirect is genuine — still confirm via the status API before crediting.
     */
    public static function verifyReturn(array $q, $secret, $maxAgeSeconds = 900)
    {
        foreach (array('order_id', 'merchant_order_id', 'status', 'ts', 'signature') as $k) {
            if (!isset($q[$k]) || !is_string($q[$k]) || $q[$k] === '') return false;
        }
        if (!ctype_digit($q['ts']) || abs(time() - (int)$q['ts']) > $maxAgeSeconds) return false;
        $expected = hash_hmac('sha256', $q['order_id'] . '|' . $q['merchant_order_id'] . '|' . $q['status'] . '|' . $q['ts'], $secret);
        return hash_equals($expected, strtolower($q['signature']));
    }
}
