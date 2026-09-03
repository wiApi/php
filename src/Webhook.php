<?php

declare(strict_types=1);

namespace WiApi;

use WiApi\Exception\WiException;

/**
 * Webhook utilities for verifying and parsing wi-api webhook events.
 *
 * The server signs the raw request body with HMAC-SHA256 and sends:
 *   x-wi-signature: sha256=<hex>
 */
class Webhook
{
    /**
     * Verify the webhook signature and return the parsed event.
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
