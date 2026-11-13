<?php

declare(strict_types=1);

namespace WiApi;

use WiApi\Exception\WiException;

/**
 * Typed `data` of the `pairing.failed` webhook event: how a pairing attempt
 * ended without linking the number. The same result is on the session as
 * `lastPairing`.
 *
 * `reason` and `method` stay plain strings so a value a newer server adds never
 * fails decoding; use {@see PairingFailed::isKnownReason()} to tell.
 */
final class PairingFailed
{
    public function __construct(
        /** `qr` (scan) or `code` (pairing code typed on the phone). */
        public readonly string $method,
        /** One of {@see PairingFailureReason::ALL}, or a value a newer server added. */
        public readonly string $reason,
        /** End of the attempt, ISO 8601. */
        public readonly string $at,
    ) {
    }

    /**
     * @param array<string, mixed> $event a parsed webhook event ({@see Webhook::parseEvent()})
     * @throws WiException when it is not a `pairing.failed` event or `data` lacks `method`/`reason`/`at`
     */
    public static function fromEvent(array $event): self
    {
        $data = $event['data'] ?? null;
        if (($event['event'] ?? null) !== 'pairing.failed' || !is_array($data)) {
            throw new WiException('Not a pairing.failed event');
        }
        foreach (['method', 'reason', 'at'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                throw new WiException('pairing.failed event without method, reason and at');
            }
        }

        return new self($data['method'], $data['reason'], $data['at']);
    }

    /** False for a reason a newer server added: treat it like `error`. */
    public function isKnownReason(): bool
    {
        return PairingFailureReason::isKnown($this->reason);
    }
}
