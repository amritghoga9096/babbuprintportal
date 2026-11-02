<?php
declare(strict_types=1);
namespace AKQR;

use InvalidArgumentException;

/** Authenticate the EXACT raw HTTP body before JSON decoding or fulfilling any order. */
final class WebhookVerifier
{
    public static function verify(string $rawBody, array $headers, string $secret, int $maxSkewSeconds = 300): array
    {
        if ($secret === '') throw new InvalidArgumentException('AKQR webhook signing secret is missing.');
        $normalized = [];
        foreach ($headers as $key => $value) $normalized[strtolower((string)$key)] = is_array($value) ? (string)reset($value) : (string)$value;
        $timestamp = $normalized['x-akqr-timestamp'] ?? '';
        $sig = $normalized['x-akqr-signature'] ?? '';
        if (!preg_match('/^[0-9]{10,13}$/D', $timestamp)
            || abs(time() - (int)$timestamp) > $maxSkewSeconds
            || !preg_match('/^sha256=[a-f0-9]{64}$/D', $sig)) {
            throw new InvalidArgumentException('Missing, stale or malformed AKQR webhook signature.');
        }
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        if (!hash_equals($expected, $sig)) {
            throw new InvalidArgumentException('AKQR webhook signature mismatch.');
        }
        $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($event) || !is_string($event['id'] ?? null)) {
            throw new InvalidArgumentException('Invalid signed webhook event.');
        }
        // Store event['id'] uniquely to ignore retries. For order.paid, verify
        // the order using AkqrClient::status() before providing goods/services.
        return $event;
    }
}
