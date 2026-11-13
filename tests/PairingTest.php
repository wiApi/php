<?php

declare(strict_types=1);

namespace WiApi\Tests;

use PHPUnit\Framework\TestCase;
use WiApi\Exception\WiException;
use WiApi\PairingFailed;
use WiApi\PairingFailureReason;
use WiApi\PairingSucceeded;
use WiApi\Webhook;

class PairingTest extends TestCase
{
    private function event(string $name, array $data): array
    {
        return Webhook::parseEvent((string) json_encode([
            'event' => $name,
            'session_id' => 'sess-1',
            'data' => $data,
            'timestamp' => '2026-11-13T12:02:40Z',
        ]));
    }

    public function testReasonsAreTheClosedSet(): void
    {
        $this->assertSame(
            ['timeout', 'rejected', 'cancelled', 'client_outdated', 'error'],
            PairingFailureReason::ALL,
        );
        $this->assertTrue(PairingFailureReason::isKnown('timeout'));
        $this->assertFalse(PairingFailureReason::isKnown('brand_new'));
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function failures(): array
    {
        $at = '2026-11-13T12:02:40Z';

        return [
            'an unread QR' => [['method' => 'qr', 'reason' => 'timeout', 'at' => $at], 'qr', 'timeout'],
            'a code that expired' => [['method' => 'code', 'reason' => 'timeout', 'at' => $at], 'code', 'timeout'],
            'a refused link' => [['method' => 'qr', 'reason' => 'rejected', 'at' => $at], 'qr', 'rejected'],
            'a cancelled attempt' => [['method' => 'qr', 'reason' => 'cancelled', 'at' => $at], 'qr', 'cancelled'],
            'a reason a newer server adds' => [['method' => 'qr', 'reason' => 'brand_new', 'at' => $at], 'qr', 'brand_new'],
        ];
    }

    /** @dataProvider failures */
    public function testFromEventNeverRejectsAnUnknownReason(array $data, string $method, string $reason): void
    {
        $p = PairingFailed::fromEvent($this->event('pairing.failed', $data));
        $this->assertSame($method, $p->method);
        $this->assertSame($reason, $p->reason);
        $this->assertSame('2026-11-13T12:02:40Z', $p->at);
        $this->assertSame(PairingFailureReason::isKnown($reason), $p->isKnownReason());
    }

    public function testPairingSucceededCarriesMethodAndAt(): void
    {
        $p = PairingSucceeded::fromEvent($this->event('pairing.succeeded', ['method' => 'code', 'at' => '2026-11-13T12:02:40Z']));
        $this->assertSame('code', $p->method);
        $this->assertSame('2026-11-13T12:02:40Z', $p->at);
    }

    public function testAFailureWithoutReasonIsRejected(): void
    {
        $this->expectException(WiException::class);
        PairingFailed::fromEvent($this->event('pairing.failed', ['method' => 'qr', 'at' => '2026-11-13T12:02:40Z']));
    }

    public function testOtherEventsAreRejected(): void
    {
        $this->expectException(WiException::class);
        PairingFailed::fromEvent($this->event('pairing.succeeded', ['method' => 'qr', 'at' => 't']));
    }

    public function testSucceededRejectsAFailure(): void
    {
        $this->expectException(WiException::class);
        PairingSucceeded::fromEvent($this->event('pairing.failed', ['method' => 'qr', 'reason' => 'timeout', 'at' => 't']));
    }
}
