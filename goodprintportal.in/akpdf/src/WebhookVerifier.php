<?php

namespace AKPDF;

use InvalidArgumentException;

/**
 * Verify AutoUPI webhook signatures.
 *
 * Header:  X-Signature: t=<unix_timestamp>,v1=<hex_sha256>
 * Signed:  HMAC-SHA256( "<t>.<raw_body>", webhook_secret )
 */
final class WebhookVerifier
{
    /**
     * @param string $rawBody         Raw HTTP request body (before json_decode).
     * @param array  $headers         All request headers (keys case-insensitive).
     * @param string $secret          AutoUPI webhook secret.
     * @param int    $maxSkewSeconds  Max allowed clock skew (default 300s / 5 min).
     * @return array Decoded webhook event.
     * @throws InvalidArgumentException if signature is missing/stale/invalid.
     */
    public static function verify($rawBody, array $headers, $secret, $maxSkewSeconds = 300)
    {
        if (!is_string($rawBody) || $rawBody === '') {
            throw new InvalidArgumentException('Empty webhook body.');
        }
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('AutoUPI webhook secret is missing.');
        }

        // Normalize headers to lowercase keys.
        $normalized = array();
        foreach ($headers as $key => $value) {
            $k = strtolower((string)$key);
            $normalized[$k] = is_array($value) ? (string)reset($value) : (string)$value;
        }

        $sigHeader = isset($normalized['x-signature']) ? $normalized['x-signature'] : '';
        if ($sigHeader === '') {
            throw new InvalidArgumentException('Missing X-Signature header.');
        }

        // Parse t=...,v1=...
        $t = '';
        $v1 = '';
        foreach (explode(',', $sigHeader) as $part) {
            $part = trim($part);
            if (strpos($part, '=') === false) continue;
            list($k, $v) = explode('=', $part, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k === 't')  $t = $v;
            if ($k === 'v1') $v1 = $v;
        }

        if ($t === '' || !ctype_digit($t)) {
            throw new InvalidArgumentException('Missing or malformed webhook timestamp.');
        }
        if ($v1 === '' || !preg_match('/^[a-f0-9]{64}$/i', $v1)) {
            throw new InvalidArgumentException('Missing or malformed webhook signature.');
        }

        // Reject stale payloads.
        if (abs(time() - (int)$t) > $maxSkewSeconds) {
            throw new InvalidArgumentException('Webhook payload is stale (timestamp skew).');
        }

        // Expected signature: HMAC-SHA256( "<t>.<raw_body>", secret )
        $expected = hash_hmac('sha256', $t . '.' . $rawBody, $secret);
        if (!hash_equals($expected, strtolower($v1))) {
            throw new InvalidArgumentException('AutoUPI webhook signature mismatch.');
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            throw new InvalidArgumentException('Invalid webhook JSON body.');
        }

        return $event;
    }
}
