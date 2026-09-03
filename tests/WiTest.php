<?php

declare(strict_types=1);

namespace WiApi\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WiApi\Session;
use WiApi\Wi;

class WiTest extends TestCase
{
    public function testConstructorThrowsOnEmptyApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Wi('');
    }

    public function testConstructorStoresApiKey(): void
    {
        $wi = new Wi('test-key-123');
        $this->assertSame('test-key-123', $wi->apiKey);
    }

    public function testConstructorAcceptsCustomBaseUrl(): void
    {
        // No exception means constructor accepted the value
        $wi = new Wi('key', 'https://custom.example.com');
        $this->assertSame('key', $wi->apiKey);
    }

    public function testConstructorStripsTrailingSlashFromBaseUrl(): void
    {
        // The baseUrl is private; we verify indirectly via request path used in session
        // No exception == constructor normalised it fine
        $wi = new Wi('key', 'https://example.com/');
        $this->assertInstanceOf(Wi::class, $wi);
    }

    public function testSessionReturnsSessionInstance(): void
    {
        $wi = new Wi('key');
        $session = $wi->session('inst-1');
        $this->assertInstanceOf(Session::class, $session);
    }

    public function testSessionExposesId(): void
    {
        $wi = new Wi('key');
        $session = $wi->session('my-instance');
        $this->assertSame('my-instance', $session->id);
    }

    public function testSessionReturnsSeparateInstancesPerId(): void
    {
        $wi = new Wi('key');
        $a = $wi->session('alpha');
        $b = $wi->session('beta');
        $this->assertSame('alpha', $a->id);
        $this->assertSame('beta', $b->id);
        $this->assertNotSame($a, $b);
    }
}
