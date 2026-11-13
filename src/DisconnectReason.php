<?php

declare(strict_types=1);

namespace WiApi;

/**
 * `data.reason` of the `disconnected` webhook event: a closed set. A newer
 * server may add a value, so decide by `willReconnect` when
 * {@see DisconnectReason::isKnown()} is false.
 */
final class DisconnectReason
{
    /** Network or WhatsApp drop the engine retries by itself. */
    public const TRANSIENT = 'transient';
    /** A disconnect you asked for. */
    public const MANUAL = 'manual';
    /** A durable pause you asked for; resume it explicitly. */
    public const PAUSED = 'paused';
    /** The link was removed; pair again. */
    public const LOGGED_OUT = 'logged_out';
    /** Another client took over the session. */
    public const REPLACED = 'replaced';
    /** Temporary ban; the engine reconnects after `retryAfterSeconds`. */
    public const TEMPORARY_BAN = 'temporary_ban';
    /** WhatsApp refused the engine's client version. */
    public const CLIENT_OUTDATED = 'client_outdated';
    /** A connect error WhatsApp reported (see `code`). */
    public const CONNECT_FAILURE = 'connect_failure';

    /** Every reason the server publishes today. */
    public const ALL = [
        self::TRANSIENT,
        self::MANUAL,
        self::PAUSED,
        self::LOGGED_OUT,
        self::REPLACED,
        self::TEMPORARY_BAN,
        self::CLIENT_OUTDATED,
        self::CONNECT_FAILURE,
    ];

    public static function isKnown(string $reason): bool
    {
        return in_array($reason, self::ALL, true);
    }
}
