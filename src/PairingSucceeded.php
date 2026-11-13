<?php

declare(strict_types=1);

namespace WiApi;

use WiApi\Exception\WiException;

/**
 * Typed `data` of the `pairing.succeeded` webhook event, sent just before
 * `connected`.
 */
final class PairingSucceeded
{
    public function __construct(
        /** `qr` (scan) or `code` (pairing code typed on the phone). */
        public readonly string $method,
        /** End of the attempt, ISO 8601. */
        public readonly string $at,
    ) {
    }

    /**
     * @param array<string, mixed> $event a parsed webhook event ({@see Webhook::parseEvent()})
     * @throws WiException when it is not a `pairing.succeeded` event or `data` lacks `method`/`at`
     */
    public static function fromEvent(array $event): self
    {
        $data = $event['data'] ?? null;
        if (($event['event'] ?? null) !== 'pairing.succeeded' || !is_array($data)) {
            throw new WiException('Not a pairing.succeeded event');
        }
        foreach (['method', 'at'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                throw new WiException('pairing.succeeded event without method and at');
            }
        }

        return new self($data['method'], $data['at']);
    }
}
