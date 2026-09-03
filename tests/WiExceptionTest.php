<?php

declare(strict_types=1);

namespace WiApi\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WiApi\Exception\WiException;

class WiExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $e = new WiException('error', 400);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testMessageIsSet(): void
    {
        $e = new WiException('Not found', 404);
        $this->assertSame('Not found', $e->getMessage());
    }

    public function testStatusCodeIsStored(): void
    {
        $e = new WiException('error', 429);
        $this->assertSame(429, $e->statusCode);
    }

    public function testErrorCodeIsStored(): void
    {
        $e = new WiException('error', 400, 'INVALID_PARAM');
        $this->assertSame('INVALID_PARAM', $e->errorCode);
    }

    public function testErrorCodeDefaultsToEmpty(): void
    {
        $e = new WiException('error', 400);
        $this->assertSame('', $e->errorCode);
    }

    public function testStatusCodeDefaultsToZero(): void
    {
        $e = new WiException('error');
        $this->assertSame(0, $e->statusCode);
    }

    public function testPreviousThrowableIsChained(): void
    {
        $cause = new \RuntimeException('root cause');
        $e = new WiException('wrapped', 500, 'INTERNAL', $cause);
        $this->assertSame($cause, $e->getPrevious());
    }

    // ─── fromResponse ─────────────────────────────────────────────────────────

    public function testFromResponseExtractsMessage(): void
    {
        $body = json_encode(['message' => 'Bad input', 'error' => 'validation']);
        $e = WiException::fromResponse(400, $body);
        $this->assertSame('Bad input', $e->getMessage());
        $this->assertSame(400, $e->statusCode);
    }

    public function testFromResponseExtractsErrorCode(): void
    {
        $body = json_encode(['message' => 'Rate limited', 'error' => 'RATE_LIMIT']);
        $e = WiException::fromResponse(429, $body);
        $this->assertSame('RATE_LIMIT', $e->errorCode);
    }

    public function testFromResponseFallsBackToHttpStatusOnInvalidJson(): void
    {
        $e = WiException::fromResponse(500, 'Internal Server Error');
        $this->assertSame(500, $e->statusCode);
        $this->assertSame('HTTP 500', $e->getMessage());
    }

    public function testFromResponseErrorCodeEmptyWhenMissing(): void
    {
        $body = json_encode(['message' => 'Unauthorized']);
        $e = WiException::fromResponse(401, $body);
        $this->assertSame('', $e->errorCode);
    }

    public function testFromResponseHandlesEmptyBody(): void
    {
        $e = WiException::fromResponse(503, '');
        $this->assertSame(503, $e->statusCode);
        $this->assertSame('HTTP 503', $e->getMessage());
    }
}
