<?php

declare(strict_types=1);

namespace WiApi;

use WiApi\Exception\WiException;

/**
 * Webhook utilities for verifying and parsing wi-api webhook events.
 *
 * The server signs every delivery with HMAC-SHA256 and sends:
 *   x-wi-signature-v2: t=<unix seconds>,v1=<hex>   (HMAC of "<t>.<rawBody>", replay-protected)
 *   x-wi-signature:    sha256=<hex>                (HMAC of the raw body only, legacy)
 *
 * Prefer {@see Webhook::verifyWebhook()} / {@see Webhook::verifyDelivery()}, which check the v2 timestamp.
 *
 * `receipt` events carry `data.status` ("delivered" | "read" | "played", absent on
 * types that say nothing about delivery), `data.rawType` (raw WhatsApp type, same as
 * `data.type`) and `data.messageIds` (one receipt can acknowledge several messages).
 */
class Webhook
{
    /** Default max age of the signed timestamp, in seconds (5 minutes). */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Verify a delivery with replay protection, without throwing.
     *
     * Checks `x-wi-signature-v2` (HMAC-SHA256 of "<t>.<rawBody>") and rejects it when `t` is
     * further than `$toleranceSeconds` from now, so a captured request cannot be replayed later
     * and a tampered timestamp fails the signature. With no v2 header (older servers) it falls
     * back to the legacy `x-wi-signature` body signature, which has no replay protection. A
     * malformed v2 header is rejected, never downgraded.
     *
     * @param string               $rawBody          Raw request body, exactly as received
     * @param array<string,string> $headers          Request headers (any case), e.g. getallheaders()
     * @param string               $secret           Your webhook secret
     * @param int                  $toleranceSeconds Max age/skew of the timestamp (default 300)
     * @param int|null             $now              Current unix time; for tests
     */
    public static function verifyWebhook(
        string $rawBody,
        array $headers,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): bool {
        $lower = [];
        foreach ($headers as $name => $value) {
            $lower[strtolower((string) $name)] = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }

        if (!array_key_exists('x-wi-signature-v2', $lower)) {
            $legacy = $lower['x-wi-signature'] ?? '';
            return $legacy !== '' && self::verifySignature($rawBody, $legacy, $secret);
        }

        $t = null;
        $v1 = null;
        foreach (explode(',', $lower['x-wi-signature-v2']) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $t = $value;
            } elseif ($key === 'v1') {
                $v1 = $value;
            }
        }
        if ($t === null || $v1 === null || $v1 === '' || !preg_match('/^-?\d+$/', $t)) {
            return false;
        }

        if (abs(($now ?? time()) - (int) $t) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $t . '.' . $rawBody, $secret), $v1);
    }

    /**
     * Like {@see Webhook::verifyWebhook()} but returns the parsed event.
     *
     * @param array<string,string> $headers
     * @return array<string, mixed>
     * @throws WiException with status 401 if verification fails
     */
    public static function verifyDelivery(
        string $rawBody,
        array $headers,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): array {
        if (!self::verifyWebhook($rawBody, $headers, $secret, $toleranceSeconds)) {
            throw new WiException('Invalid webhook signature', 401, 'INVALID_SIGNATURE');
        }
        return self::parseEvent($rawBody);
    }

    /**
     * Verify the legacy body-only signature and return the parsed event (no replay protection;
     * prefer {@see Webhook::verifyDelivery()}).
     *
     * @param string $body      Raw request body (file_get_contents('php://input'))
     * @param string $signature Value of the x-wi-signature header
     * @param string $secret    Your webhook secret
     * @return array<string, mixed> Parsed webhook event
     * @throws WiException with status 401 if the signature is invalid
     */
    public static function verify(string $body, string $signature, string $secret): array
    {
        if (!self::verifySignature($body, $signature, $secret)) {
            throw new WiException('Invalid webhook signature', 401, 'INVALID_SIGNATURE');
        }
        return self::parseEvent($body);
    }

    /**
     * Verify an HMAC-SHA256 webhook signature without throwing.
     *
     * @param string $body      Raw request body
     * @param string $signature Value of the x-wi-signature header (sha256=<hex>)
     * @param string $secret    Your webhook secret
     */
    public static function verifySignature(string $body, string $signature, string $secret): bool
    {
        // Expect "sha256=<hex>"
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $providedHex = substr($signature, 7);
        if ($providedHex === '') {
            return false;
        }

        $expectedHex = hash_hmac('sha256', $body, $secret);

        // Constant-time comparison to prevent timing attacks
        return hash_equals($expectedHex, $providedHex);
    }

    /**
     * Parse a raw webhook body into an event array without signature verification.
     *
     * @return array<string, mixed>
     * @throws WiException if the body is not valid JSON
     */
    public static function parseEvent(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WiException('Invalid webhook payload: ' . $e->getMessage(), 400, 'INVALID_PAYLOAD', $e);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
