<?php

declare(strict_types=1);

namespace WiApi\Tests;

use PHPUnit\Framework\TestCase;
use WiApi\Exception\WiException;
use WiApi\Webhook;

class WebhookTest extends TestCase
{
    private const SECRET = 'test-webhook-secret-32-chars!!!';
    private const PAYLOAD = '{"event":"message","session_id":"sess-1","data":{},"timestamp":"2026-09-03T00:00:00Z"}';

    private function makeSignature(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    // ─── verifySignature ──────────────────────────────────────────────────────

    public function testVerifySignatureReturnsTrueForValidSignature(): void
    {
        $sig = $this->makeSignature(self::PAYLOAD, self::SECRET);
        $this->assertTrue(Webhook::verifySignature(self::PAYLOAD, $sig, self::SECRET));
    }

    public function testVerifySignatureReturnsFalseForWrongHex(): void
    {
        $bad = 'sha256=' . str_repeat('0', 64);
        $this->assertFalse(Webhook::verifySignature(self::PAYLOAD, $bad, self::SECRET));
    }

    public function testVerifySignatureReturnsFalseForTamperedBody(): void
    {
        $sig = $this->makeSignature(self::PAYLOAD, self::SECRET);
        $this->assertFalse(Webhook::verifySignature('tampered body', $sig, self::SECRET));
    }

    public function testVerifySignatureReturnsFalseForWrongScheme(): void
    {
        $validHex = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        // sha512= prefix not accepted — must be sha256=
        $this->assertFalse(Webhook::verifySignature(self::PAYLOAD, 'sha512=' . $validHex, self::SECRET));
    }

    public function testVerifySignatureReturnsFalseForEmptySignature(): void
    {
        $this->assertFalse(Webhook::verifySignature(self::PAYLOAD, '', self::SECRET));
    }

    public function testVerifySignatureReturnsFalseForMissingPrefixOnly(): void
    {
        // Bare hex without "sha256=" prefix
        $hex = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $this->assertFalse(Webhook::verifySignature(self::PAYLOAD, $hex, self::SECRET));
    }

    public function testVerifySignatureReturnsFalseForWrongSecret(): void
    {
        $sig = $this->makeSignature(self::PAYLOAD, self::SECRET);
        $this->assertFalse(Webhook::verifySignature(self::PAYLOAD, $sig, 'wrong-secret'));
    }

    public function testVerifySignatureReturnsFalseForSha256PrefixWithEmptyHex(): void
    {
        // "sha256=" alone — providedHex is ''
        $this->assertFalse(Webhook::verifySignature(self::PAYLOAD, 'sha256=', self::SECRET));
    }

    // ─── parseEvent ───────────────────────────────────────────────────────────

    public function testParseEventReturnsDecodedArray(): void
    {
        $event = Webhook::parseEvent(self::PAYLOAD);
        $this->assertIsArray($event);
        $this->assertSame('message', $event['event']);
        $this->assertSame('sess-1', $event['session_id']);
    }

    public function testParseEventThrowsWiExceptionOnInvalidJson(): void
    {
        $this->expectException(WiException::class);
        Webhook::parseEvent('not-valid-json');
    }

    public function testParseEventThrowsWith400StatusCode(): void
    {
        try {
            Webhook::parseEvent('{broken');
            $this->fail('Expected WiException');
        } catch (WiException $e) {
            $this->assertSame(400, $e->statusCode);
            $this->assertSame('INVALID_PAYLOAD', $e->errorCode);
        }
    }

    public function testParseEventChainsOriginalJsonException(): void
    {
        try {
            Webhook::parseEvent('not-json');
            $this->fail('Expected WiException');
        } catch (WiException $e) {
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    // ─── verify ───────────────────────────────────────────────────────────────

    public function testVerifyReturnsEventForValidSignature(): void
    {
        $sig = $this->makeSignature(self::PAYLOAD, self::SECRET);
        $event = Webhook::verify(self::PAYLOAD, $sig, self::SECRET);
        $this->assertSame('message', $event['event']);
        $this->assertSame('sess-1', $event['session_id']);
    }

    public function testVerifyThrows401ForInvalidSignature(): void
    {
        $this->expectException(WiException::class);
        $this->expectExceptionCode(401);
        Webhook::verify(self::PAYLOAD, 'sha256=bad', self::SECRET);
    }

    public function testVerifyThrowsWithInvalidSignatureErrorCode(): void
    {
        try {
            Webhook::verify(self::PAYLOAD, 'sha256=bad', self::SECRET);
            $this->fail('Expected WiException');
        } catch (WiException $e) {
            $this->assertSame('INVALID_SIGNATURE', $e->errorCode);
            $this->assertSame(401, $e->statusCode);
        }
    }

    public function testVerifyThrowsWhenSignatureIsEmpty(): void
    {
        $this->expectException(WiException::class);
        Webhook::verify(self::PAYLOAD, '', self::SECRET);
    }
}
