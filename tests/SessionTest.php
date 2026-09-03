<?php

declare(strict_types=1);

namespace WiApi\Tests;

use PHPUnit\Framework\TestCase;
use WiApi\Session;
use WiApi\Wi;

class SessionTest extends TestCase
{
    /** Build a Wi mock that expects exactly one request() call. */
    private function mockWi(
        string $method,
        string $path,
        mixed $bodyMatcher,
        array $returnValue,
    ): Wi {
        $wi = $this->getMockBuilder(Wi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();

        $wi->expects($this->once())
            ->method('request')
            ->with($this->equalTo($method), $this->equalTo($path), $bodyMatcher)
            ->willReturn($returnValue);

        return $wi;
    }

    // ─── sendText ─────────────────────────────────────────────────────────────

    public function testSendTextPostsCorrectPathAndBody(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s1/messages/send-text',
            $this->equalTo(['to' => '5511999999999', 'text' => 'Hello!']),
            ['messageId' => 'msg_1'],
        );
        $result = (new Session('s1', $wi))->sendText('5511999999999', 'Hello!');
        $this->assertSame('msg_1', $result['messageId']);
    }

    public function testSendTextIncludesQuotedWhenProvided(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-text',
            $this->equalTo(['to' => '55', 'text' => 'Re:', 'quoted' => 'prev_id']),
            ['messageId' => 'msg_2'],
        );
        (new Session('s', $wi))->sendText('55', 'Re:', 'prev_id');
    }

    public function testSendTextOmitsQuotedWhenNull(): void
    {
        $wi = $this->getMockBuilder(Wi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $wi->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/sessions/s/messages/send-text',
                $this->callback(fn($b) => !isset($b['quoted'])),
            )
            ->willReturn([]);
        (new Session('s', $wi))->sendText('55', 'Hi');
    }

    // ─── sendImage ────────────────────────────────────────────────────────────

    public function testSendImagePostsCorrectPath(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-image',
            $this->equalTo(['to' => '55', 'url' => 'https://img.com/a.jpg', 'caption' => 'look']),
            ['messageId' => 'm2'],
        );
        (new Session('s', $wi))->sendImage('55', 'https://img.com/a.jpg', 'look');
    }

    public function testSendImageOmitsCaptionWhenNull(): void
    {
        $wi = $this->getMockBuilder(Wi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $wi->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/sessions/s/messages/send-image',
                $this->callback(fn($b) => !isset($b['caption']) && $b['to'] === '55'),
            )
            ->willReturn([]);
        (new Session('s', $wi))->sendImage('55', 'https://img.com/a.jpg');
    }

    // ─── sendAudio ────────────────────────────────────────────────────────────

    public function testSendAudioWithPttTrue(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-audio',
            $this->equalTo(['to' => '55', 'url' => 'https://cdn/a.ogg', 'ptt' => true]),
            ['messageId' => 'm3'],
        );
        (new Session('s', $wi))->sendAudio('55', 'https://cdn/a.ogg', true);
    }

    public function testSendAudioAlwaysSendsPttField(): void
    {
        // ptt=false must still be present in body (not omitempty)
        $wi = $this->getMockBuilder(Wi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $wi->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/sessions/s/messages/send-audio',
                $this->callback(fn($b) => array_key_exists('ptt', $b) && $b['ptt'] === false),
            )
            ->willReturn([]);
        (new Session('s', $wi))->sendAudio('55', 'https://cdn/a.ogg', false);
    }

    // ─── sendDocument ─────────────────────────────────────────────────────────

    public function testSendDocumentWithFilename(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-document',
            $this->equalTo(['to' => '55', 'url' => 'https://cdn/f.pdf', 'filename' => 'report.pdf']),
            ['messageId' => 'm4'],
        );
        (new Session('s', $wi))->sendDocument('55', 'https://cdn/f.pdf', 'report.pdf');
    }

    public function testSendDocumentWithFilenameAndCaption(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-document',
            $this->equalTo(['to' => '55', 'url' => 'https://cdn/f.pdf', 'filename' => 'f.pdf', 'caption' => 'See attached']),
            ['messageId' => 'm5'],
        );
        (new Session('s', $wi))->sendDocument('55', 'https://cdn/f.pdf', 'f.pdf', 'See attached');
    }

    // ─── sendLocation ─────────────────────────────────────────────────────────

    public function testSendLocationWithTitle(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-location',
            $this->equalTo(['to' => '55', 'latitude' => -23.5505, 'longitude' => -46.6333, 'title' => 'SP']),
            ['messageId' => 'm6'],
        );
        (new Session('s', $wi))->sendLocation('55', -23.5505, -46.6333, 'SP');
    }

    public function testSendLocationOmitsTitleWhenNull(): void
    {
        $wi = $this->getMockBuilder(Wi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $wi->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/sessions/s/messages/send-location',
                $this->callback(fn($b) => !isset($b['title']) && $b['latitude'] === 1.0),
            )
            ->willReturn([]);
        (new Session('s', $wi))->sendLocation('55', 1.0, 2.0);
    }

    // ─── sendPoll ─────────────────────────────────────────────────────────────

    public function testSendPollCorrectBody(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-poll',
            $this->equalTo(['to' => '55', 'question' => 'Best?', 'options' => ['A', 'B'], 'multipleAnswers' => false]),
            ['messageId' => 'm7'],
        );
        (new Session('s', $wi))->sendPoll('55', 'Best?', ['A', 'B']);
    }

    public function testSendPollWithMultipleAnswers(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-poll',
            $this->callback(fn($b) => $b['multipleAnswers'] === true),
            ['messageId' => 'm8'],
        );
        (new Session('s', $wi))->sendPoll('55', 'Pick all', ['X', 'Y', 'Z'], true);
    }

    // ─── sendContact ──────────────────────────────────────────────────────────

    public function testSendContactPostsCorrectBody(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-contact',
            $this->equalTo(['to' => '55', 'contactName' => 'Alice', 'contactNumber' => '5511888']),
            ['messageId' => 'm9'],
        );
        (new Session('s', $wi))->sendContact('55', 'Alice', '5511888');
    }

    // ─── sendSticker ──────────────────────────────────────────────────────────

    public function testSendStickerPostsCorrectBody(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/messages/send-sticker',
            $this->equalTo(['to' => '55', 'url' => 'https://cdn/s.webp']),
            ['messageId' => 'm10'],
        );
        (new Session('s', $wi))->sendSticker('55', 'https://cdn/s.webp');
    }

    // ─── react ────────────────────────────────────────────────────────────────

    public function testReactPostsCorrectBody(): void
    {
        $wi = $this->getMockBuilder(Wi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $wi->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/sessions/s/chat/react',
                $this->callback(fn($b) => $b['to'] === '55' && $b['messageId'] === 'msg_x' && $b['emoji'] === '👍'),
            )
            ->willReturn([]);
        (new Session('s', $wi))->react('55', 'msg_x', '👍');
    }

    // ─── markRead ─────────────────────────────────────────────────────────────

    public function testMarkReadPostsCorrectBody(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/chat/markread',
            $this->equalTo(['chatId' => '55@s.whatsapp.net']),
            [],
        );
        (new Session('s', $wi))->markRead('55@s.whatsapp.net');
    }

    // ─── presence ─────────────────────────────────────────────────────────────

    public function testPresencePostsCorrectBody(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/chat/presence',
            $this->equalTo(['chatId' => '55@s', 'presence' => 'composing']),
            [],
        );
        (new Session('s', $wi))->presence('55@s', 'composing');
    }

    // ─── status ───────────────────────────────────────────────────────────────

    public function testStatusSendsGetRequest(): void
    {
        $wi = $this->mockWi(
            'GET',
            '/sessions/abc/status',
            $this->isNull(),
            ['connected' => true, 'phone' => '5511999'],
        );
        $result = (new Session('abc', $wi))->status();
        $this->assertTrue($result['connected']);
        $this->assertSame('5511999', $result['phone']);
    }

    // ─── pairPhone ────────────────────────────────────────────────────────────

    public function testPairPhoneSendsPhoneInBody(): void
    {
        $wi = $this->mockWi(
            'POST',
            '/sessions/s/pairphone',
            $this->equalTo(['phone' => '5511999999999']),
            ['code' => 'ABCD1234'],
        );
        $result = (new Session('s', $wi))->pairPhone('5511999999999');
        $this->assertSame('ABCD1234', $result['code']);
    }

    // ─── lifecycle: connect / disconnect / logout ──────────────────────────────

    public function testConnectPostsToCorrectPath(): void
    {
        $wi = $this->mockWi('POST', '/sessions/s/connect', $this->isNull(), []);
        (new Session('s', $wi))->connect();
    }

    public function testDisconnectPostsToCorrectPath(): void
    {
        $wi = $this->mockWi('POST', '/sessions/s/disconnect', $this->isNull(), []);
        (new Session('s', $wi))->disconnect();
    }

    public function testLogoutPostsToCorrectPath(): void
    {
        $wi = $this->mockWi('POST', '/sessions/s/logout', $this->isNull(), []);
        (new Session('s', $wi))->logout();
    }

    // ─── path isolation: session id is embedded correctly ─────────────────────

    public function testSessionIdIsEmbeddedInEveryPath(): void
    {
        $wi = $this->getMockBuilder(Wi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $wi->expects($this->once())
            ->method('request')
            ->with('POST', '/sessions/my-long-id/messages/send-text', $this->anything())
            ->willReturn([]);
        (new Session('my-long-id', $wi))->sendText('55', 'hi');
    }
}
