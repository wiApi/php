<?php

declare(strict_types=1);

namespace WiApi\Tests;

use PHPUnit\Framework\TestCase;
use WiApi\DisconnectReason;
use WiApi\Disconnected;
use WiApi\Exception\WiException;
use WiApi\Webhook;

class DisconnectedTest extends TestCase
{
    private function event(array $data): array
    {
        return Webhook::parseEvent((string) json_encode([
            'event' => 'disconnected',
            'session_id' => 'sess-1',
            'data' => $data,
            'timestamp' => '2026-11-13T12:00:00Z',
        ]));
    }

    public function testReasonsAreTheClosedSet(): void
    {
        $this->assertSame(
            ['transient', 'manual', 'paused', 'logged_out', 'replaced', 'temporary_ban', 'client_outdated', 'connect_failure'],
            DisconnectReason::ALL,
        );
        $this->assertTrue(DisconnectReason::isKnown('replaced'));
        $this->assertFalse(DisconnectReason::isKnown('brand_new'));
    }

    /** @return array<string, array{array<string, mixed>, string, bool, ?string, ?int}> */
    public static function payloads(): array
    {
        return [
            'transient' => [['reason' => 'transient', 'willReconnect' => true], 'transient', true, null, null],
            'logged_out' => [['reason' => 'logged_out', 'willReconnect' => false, 'resume' => 'connect', 'code' => 401, 'detail' => '401: logged out'], 'logged_out', false, 'connect', null],
            'temporary_ban' => [['reason' => 'temporary_ban', 'willReconnect' => false, 'resume' => 'connect', 'code' => 101, 'expireSeconds' => 3600, 'retryAfterSeconds' => 3600], 'temporary_ban', false, 'connect', 3600],
            'paused' => [['reason' => 'paused', 'willReconnect' => false, 'resume' => 'resume'], 'paused', false, 'resume', null],
            'a reason a newer server adds' => [['reason' => 'brand_new', 'willReconnect' => true], 'brand_new', true, null, null],
        ];
    }

    /** @dataProvider payloads */
    public function testFromEventKeepsWillReconnectAndNeverRejectsAnUnknownReason(
        array $data,
        string $reason,
        bool $will,
        ?string $resume,
        ?int $retry,
    ): void {
        $d = Disconnected::fromEvent($this->event($data));
        $this->assertSame($reason, $d->reason);
        $this->assertSame($will, $d->willReconnect);
        $this->assertSame($resume, $d->resume);
        $this->assertSame($retry, $d->retryAfterSeconds);
        $this->assertSame(DisconnectReason::isKnown($reason), $d->isKnownReason());
    }

    public function testAnEventWithoutWillReconnectIsRejected(): void
    {
        $this->expectException(WiException::class);
        Disconnected::fromEvent($this->event(['reason' => 'transient']));
    }

    public function testOtherEventsAreRejected(): void
    {
        $this->expectException(WiException::class);
        Disconnected::fromEvent(['event' => 'message', 'session_id' => 's', 'data' => [], 'timestamp' => 't']);
    }
}
