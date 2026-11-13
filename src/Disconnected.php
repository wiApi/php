<?php

declare(strict_types=1);

namespace WiApi;

use WiApi\Exception\WiException;

/**
 * Typed `data` of the `disconnected` webhook event.
 *
 * `willReconnect` is always present: `true` means the engine reconnects by
 * itself, `false` means the session stays offline until you act (`resume`
 * names the call). `reason` stays a plain string so a value a newer server adds
 * never fails decoding; use {@see Disconnected::isKnownReason()} to tell.
 */
final class Disconnected
{
    public function __construct(
        public readonly string $reason,
        public readonly bool $willReconnect,
        /** Call that brings the session back (`connect` or `resume`). Only with `willReconnect: false`. */
        public readonly ?string $resume = null,
        /** Code WhatsApp reported (`logged_out`, `temporary_ban`, `connect_failure`). */
        public readonly ?int $code = null,
        /** Diagnostic text; do not branch on it. `local` marks a logout you asked for. */
        public readonly ?string $detail = null,
        /** `temporary_ban`: length of the ban, in seconds. */
        public readonly ?int $expireSeconds = null,
        /** `temporary_ban`: seconds until the engine reconnects by itself. */
        public readonly ?int $retryAfterSeconds = null,
    ) {
    }

    /**
     * @param array<string, mixed> $event a parsed webhook event ({@see Webhook::parseEvent()})
     * @throws WiException when it is not a `disconnected` event or `data` lacks `reason`/`willReconnect`
     */
    public static function fromEvent(array $event): self
    {
        $data = $event['data'] ?? null;
        if (($event['event'] ?? null) !== 'disconnected' || !is_array($data)) {
            throw new WiException('Not a disconnected event');
        }
        if (!isset($data['reason']) || !is_string($data['reason']) || !isset($data['willReconnect']) || !is_bool($data['willReconnect'])) {
            throw new WiException('disconnected event without reason and willReconnect');
        }

        return new self(
            $data['reason'],
            $data['willReconnect'],
            isset($data['resume']) && is_string($data['resume']) ? $data['resume'] : null,
            isset($data['code']) && is_int($data['code']) ? $data['code'] : null,
            isset($data['detail']) && is_string($data['detail']) ? $data['detail'] : null,
            isset($data['expireSeconds']) && is_int($data['expireSeconds']) ? $data['expireSeconds'] : null,
            isset($data['retryAfterSeconds']) && is_int($data['retryAfterSeconds']) ? $data['retryAfterSeconds'] : null,
        );
    }

    /** False for a reason a newer server added: decide by `willReconnect`. */
    public function isKnownReason(): bool
    {
        return DisconnectReason::isKnown($this->reason);
    }
}
