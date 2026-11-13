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

    public function testParseEventKeepsTheSessionMetadata(): void
    {
        $event = Webhook::parseEvent(
            '{"event":"connected","session_id":"s","session_metadata":{"purpose":"briefing"},"data":{},"timestamp":"t"}'
        );
        $this->assertSame(['purpose' => 'briefing'], $event['session_metadata']);
        // A server older than the field does not send it: read it with a default.
        $this->assertSame([], Webhook::parseEvent(self::PAYLOAD)['session_metadata'] ?? []);
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

    // ─── verifyWebhook (v2: signed timestamp) ─────────────────────────────────

    private const NOW = 1_800_000_000;

    private function makeSignatureV2(string $payload, string $secret, int $ts): string
    {
        return sprintf('t=%d,v1=%s', $ts, hash_hmac('sha256', $ts . '.' . $payload, $secret));
    }

    public function testVerifyWebhookAcceptsFreshV2(): void
    {
        $h = ['X-WI-Signature-V2' => $this->makeSignatureV2(self::PAYLOAD, self::SECRET, self::NOW)];
        $this->assertTrue(Webhook::verifyWebhook(self::PAYLOAD, $h, self::SECRET, 300, self::NOW + 299));
    }

    public function testVerifyWebhookRejectsReplayOutsideTolerance(): void
    {
        $h = ['x-wi-signature-v2' => $this->makeSignatureV2(self::PAYLOAD, self::SECRET, self::NOW)];
        $this->assertFalse(Webhook::verifyWebhook(self::PAYLOAD, $h, self::SECRET, 300, self::NOW + 301));
        $this->assertFalse(Webhook::verifyWebhook(self::PAYLOAD, $h, self::SECRET, 300, self::NOW - 301));
        $this->assertFalse(Webhook::verifyWebhook(self::PAYLOAD, $h, self::SECRET, 30, self::NOW + 60));
        $this->assertTrue(Webhook::verifyWebhook(self::PAYLOAD, $h, self::SECRET, 120, self::NOW + 60));
    }

    public function testVerifyWebhookRejectsTamperedTimestampBodyAndSecret(): void
    {
        $sig = $this->makeSignatureV2(self::PAYLOAD, self::SECRET, self::NOW);
        $v1 = explode(',', $sig)[1];
        $this->assertFalse(Webhook::verifyWebhook(
            self::PAYLOAD,
            ['x-wi-signature-v2' => 't=' . (self::NOW + 1) . ',' . $v1],
            self::SECRET,
            300,
            self::NOW,
        ));
        $this->assertFalse(Webhook::verifyWebhook('tampered', ['x-wi-signature-v2' => $sig], self::SECRET, 300, self::NOW));
        $this->assertFalse(Webhook::verifyWebhook(self::PAYLOAD, ['x-wi-signature-v2' => $sig], 'wrong', 300, self::NOW));
    }

    public function testVerifyWebhookMalformedV2NeverFallsBackToLegacy(): void
    {
        $legacy = $this->makeSignature(self::PAYLOAD, self::SECRET);
        foreach (['', 'garbage', 't=abc,v1=00', 't=' . self::NOW, 'v1=00'] as $v2) {
            $this->assertFalse(Webhook::verifyWebhook(
                self::PAYLOAD,
                ['x-wi-signature-v2' => $v2, 'x-wi-signature' => $legacy],
                self::SECRET,
                300,
                self::NOW,
            ));
        }
    }

    public function testVerifyWebhookFallsBackToLegacyWhenNoV2Header(): void
    {
        $legacy = $this->makeSignature(self::PAYLOAD, self::SECRET);
        $this->assertTrue(Webhook::verifyWebhook(self::PAYLOAD, ['x-wi-signature' => $legacy], self::SECRET));
        $this->assertFalse(Webhook::verifyWebhook(self::PAYLOAD, [], self::SECRET));
    }

    public function testVerifyDeliveryReturnsEventOrThrows401(): void
    {
        $now = time();
        $h = ['x-wi-signature-v2' => $this->makeSignatureV2(self::PAYLOAD, self::SECRET, $now)];
        $this->assertSame('message', Webhook::verifyDelivery(self::PAYLOAD, $h, self::SECRET)['event']);

        $stale = ['x-wi-signature-v2' => $this->makeSignatureV2(self::PAYLOAD, self::SECRET, $now - 3600)];
        try {
            Webhook::verifyDelivery(self::PAYLOAD, $stale, self::SECRET);
            $this->fail('expected WiException');
        } catch (WiException $e) {
            $this->assertSame(401, $e->statusCode);
        }
    }

    // ─── receipt ──────────────────────────────────────────────────────────────

    public function testParseEventReceiptPayloads(): void
    {
        $cases = [
            'delivered' => ['', 'delivered', ['A', 'B']],
            'read' => ['read', 'read', ['A']],
            'played' => ['played', 'played', ['C']],
        ];
        foreach ($cases as $name => [$raw, $status, $ids]) {
            $body = json_encode([
                'event' => 'receipt',
                'session_id' => 'sess-1',
                'data' => [
                    'type' => $raw,
                    'rawType' => $raw,
                    'status' => $status,
                    'chat' => '5511998765432@s.whatsapp.net',
                    'sender' => '5511998765432@s.whatsapp.net',
                    'messageIds' => $ids,
                    'timestamp' => 1748790003,
                    'isFromMe' => false,
                    'messageFromMe' => true,
                ],
                'timestamp' => '2026-06-01T15:00:03.000Z',
            ], JSON_THROW_ON_ERROR);
            $event = Webhook::parseEvent($body);
            $this->assertSame('receipt', $event['event'], $name);
            $this->assertSame($status, $event['data']['status'], $name);
            $this->assertSame($ids, $event['data']['messageIds'], $name);
            $this->assertSame($event['data']['type'], $event['data']['rawType'], $name);
        }
    }
}
