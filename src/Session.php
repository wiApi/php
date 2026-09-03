<?php

declare(strict_types=1);

namespace WiApi;

use WiApi\Exception\WiException;

class Session
{
    public function __construct(
        public readonly string $id,
        private readonly Wi $client,
    ) {}

    // ─── Internal helpers ──────────────────────────────────────────────────────

    private function path(string $suffix): string
    {
        return "/sessions/{$this->id}{$suffix}";
    }

    /** @return array<string, mixed> */
    private function get(string $suffix): array
    {
        return $this->client->request('GET', $this->path($suffix));
    }

    /** @param array<string, mixed>|null $body */
    private function post(string $suffix, ?array $body = null): array
    {
        return $this->client->request('POST', $this->path($suffix), $body);
    }

    // ─── Session lifecycle ─────────────────────────────────────────────────────

    /**
     * Get current session status and phone number if connected.
     *
     * @return array{status: string, phone?: string}
     */
    public function status(): array
    {
        return $this->get('/status');
    }

    /**
     * Get QR code image (base64 PNG) for scanning.
     *
     * @return array{qr: string}
     */
    public function qr(): array
    {
        return $this->get('/qr');
    }

    /**
     * Start the connection flow. Poll qr() or status() for updates.
     */
    public function connect(): void
    {
        $this->post('/connect');
    }

    /**
     * Disconnect from WhatsApp (keeps session keys).
     */
    public function disconnect(): void
    {
        $this->post('/disconnect');
    }

    /**
     * Logout and delete session keys.
     */
    public function logout(): void
    {
        $this->post('/logout');
    }

    /**
     * Pair with a phone number instead of scanning QR.
     * Returns an 8-character pairing code to enter on the phone.
     *
     * @return array{code: string}
     */
    public function pairPhone(string $phone): array
    {
        return $this->post('/pairphone', ['phone' => $phone]);
    }

    // ─── Send ──────────────────────────────────────────────────────────────────

    /**
     * Send a text message.
     *
     * @return array{messageId: string}
     */
    public function sendText(string $to, string $text, ?string $quoted = null): array
    {
        $body = ['to' => $to, 'text' => $text];
        if ($quoted !== null) {
            $body['quoted'] = $quoted;
        }
        return $this->post('/messages/send-text', $body);
    }

    /**
     * Send an image message.
     *
     * @return array{messageId: string}
     */
    public function sendImage(string $to, string $url, ?string $caption = null): array
    {
        $body = ['to' => $to, 'url' => $url];
        if ($caption !== null) {
            $body['caption'] = $caption;
        }
        return $this->post('/messages/send-image', $body);
    }

    /**
     * Send an audio message.
     *
     * @param bool $ptt Send as voice note (push-to-talk)
     * @return array{messageId: string}
     */
    public function sendAudio(string $to, string $url, bool $ptt = false): array
    {
        return $this->post('/messages/send-audio', ['to' => $to, 'url' => $url, 'ptt' => $ptt]);
    }

    /**
     * Send a video message.
     *
     * @return array{messageId: string}
     */
    public function sendVideo(string $to, string $url, ?string $caption = null): array
    {
        $body = ['to' => $to, 'url' => $url];
        if ($caption !== null) {
            $body['caption'] = $caption;
        }
        return $this->post('/messages/send-video', $body);
    }

    /**
     * Send a document/file message.
     *
     * @return array{messageId: string}
     */
    public function sendDocument(
        string $to,
        string $url,
        ?string $filename = null,
        ?string $caption = null,
    ): array {
        $body = ['to' => $to, 'url' => $url];
        if ($filename !== null) {
            $body['filename'] = $filename;
        }
        if ($caption !== null) {
            $body['caption'] = $caption;
        }
        return $this->post('/messages/send-document', $body);
    }

    /**
     * Send a location pin.
     *
     * @return array{messageId: string}
     */
    public function sendLocation(
        string $to,
        float $latitude,
        float $longitude,
        ?string $title = null,
    ): array {
        $body = ['to' => $to, 'latitude' => $latitude, 'longitude' => $longitude];
        if ($title !== null) {
            $body['title'] = $title;
        }
        return $this->post('/messages/send-location', $body);
    }

    /**
     * Send a contact card.
     *
     * @return array{messageId: string}
     */
    public function sendContact(string $to, string $contactName, string $contactNumber): array
    {
        return $this->post('/messages/send-contact', [
            'to'            => $to,
            'contactName'   => $contactName,
            'contactNumber' => $contactNumber,
        ]);
    }

    /**
     * Send a sticker.
     *
     * @return array{messageId: string}
     */
    public function sendSticker(string $to, string $url): array
    {
        return $this->post('/messages/send-sticker', ['to' => $to, 'url' => $url]);
    }

    /**
     * Send a poll.
     *
     * @param string[] $options
     * @return array{messageId: string}
     */
    public function sendPoll(
        string $to,
        string $question,
        array $options,
        bool $multipleAnswers = false,
    ): array {
        return $this->post('/messages/send-poll', [
            'to'              => $to,
            'question'        => $question,
            'options'         => $options,
            'multipleAnswers' => $multipleAnswers,
        ]);
    }

    /**
     * React to a message with an emoji.
     */
    public function react(string $to, string $messageId, string $emoji): void
    {
        $this->post('/chat/react', ['to' => $to, 'messageId' => $messageId, 'emoji' => $emoji]);
    }

    // ─── Chat ──────────────────────────────────────────────────────────────────

    /**
     * Mark a chat as read.
     */
    public function markRead(string $chatId): void
    {
        $this->post('/chat/markread', ['chatId' => $chatId]);
    }

    /**
     * Set presence status in a chat (e.g. "composing", "recording", "paused").
     */
    public function presence(string $chatId, string $presence): void
    {
        $this->post('/chat/presence', ['chatId' => $chatId, 'presence' => $presence]);
    }
}
