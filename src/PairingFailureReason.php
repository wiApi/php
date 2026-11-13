<?php

declare(strict_types=1);

namespace WiApi;

/**
 * `data.reason` of the `pairing.failed` webhook event: a closed set. A newer
 * server may add a value, so treat a reason for which
 * {@see PairingFailureReason::isKnown()} is false like {@see PairingFailureReason::ERROR}.
 */
final class PairingFailureReason
{
    /** Nobody read the QR or typed the code in time (the QR channel lasts about 160 s). */
    public const TIMEOUT = 'timeout';
    /** WhatsApp refused the link. */
    public const REJECTED = 'rejected';
    /** `disconnect` or `pause` ended the attempt. */
    public const CANCELLED = 'cancelled';
    /** WhatsApp refuses the connector's version. */
    public const CLIENT_OUTDATED = 'client_outdated';
    /** Any other failure. */
    public const ERROR = 'error';

    /** Every reason the server publishes today. */
    public const ALL = [
        self::TIMEOUT,
        self::REJECTED,
        self::CANCELLED,
        self::CLIENT_OUTDATED,
        self::ERROR,
    ];

    public static function isKnown(string $reason): bool
    {
        return in_array($reason, self::ALL, true);
    }
}
