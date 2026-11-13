# wi-api PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/wi-api/php?style=flat-square)](https://packagist.org/packages/wi-api/php)
[![license](https://img.shields.io/badge/license-MIT-informational?style=flat-square)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-informational?style=flat-square)](https://www.php.net/)

PHP SDK for [wi-api](https://wi.api.br). Send and receive WhatsApp messages with no external dependencies.

Requires PHP 8.1+, `ext-curl`, and `ext-json`.

## Install

```bash
composer require wi-api/php
```

## Quick start

```php
use WiApi\Wi;

$wi = new Wi(apiKey: $_ENV['WI_API_KEY']);
$msg = $wi->session('my-instance')->sendText(
    to:   '5511999999999',
    text: 'Hello from wi-api',
);
echo $msg['messageId'];
```

## Sessions

```php
$session = $wi->session('my-instance');

$session->connect();

$qr = $session->qr();
echo $qr['qr']; // base64 PNG

$pair = $session->pairPhone('5511999999999');
echo $pair['pairCode']; // 8-character code

$status = $session->status();
echo $status['connected'] ? 'connected' : 'disconnected';

$session->disconnect(); // keeps session keys
$session->logout();     // removes session keys
```

## Sending messages

```php
// Text
$session->sendText(to: '5511999999999', text: 'Hello!');

// Reply
$session->sendText(to: '5511999999999', text: 'Got it', quoted: $messageId);

// Image
$session->sendImage(
    to:      '5511999999999',
    url:     'https://example.com/photo.jpg',
    caption: 'Look at this',
);

// Voice note
$session->sendAudio(to: '5511999999999', url: 'https://example.com/audio.ogg', ptt: true);

// Document
$session->sendDocument(
    to:       '5511999999999',
    url:      'https://example.com/report.pdf',
    filename: 'report.pdf',
);

// Location
$session->sendLocation(
    to:        '5511999999999',
    latitude:  -23.5505,
    longitude: -46.6333,
    title:     'São Paulo',
);

// Poll
$session->sendPoll(
    to:       '5511999999999',
    question: 'Best stack?',
    options:  ['PHP', 'Go', 'Rust', 'Node.js'],
);

// Reaction
$session->react(to: '5511999999999', messageId: $messageId, emoji: '');
```

## Webhooks

```php
use WiApi\Webhook;
use WiApi\Exception\WiException;

$body = file_get_contents('php://input');

try {
    // x-wi-signature-v2 (HMAC of "<timestamp>.<body>"), 5 minute replay tolerance.
    $event = Webhook::verifyDelivery(
        rawBody: $body,
        headers: getallheaders(),
        secret:  $_ENV['WI_WEBHOOK_SECRET'],
    );

    match ($event['event']) {
        'message'   => handleMessage($event['data']),
        'connected' => handleConnected($event['data']),
        default     => null,
    };

    http_response_code(204);
} catch (WiException $e) {
    http_response_code($e->statusCode ?: 400);
}
```

`disconnected` carries `willReconnect` on every delivery, so you never need to know each `reason`:

```php
use WiApi\Disconnected;

if ($event['event'] === 'disconnected') {
    $d = Disconnected::fromEvent($event);
    if (!$d->willReconnect) {
        // offline until you act: $d->resume is "connect" or "resume"
        alertOwner($event['session_id'], $d->reason);
    }
    // an unknown reason ($d->isKnownReason() === false) follows willReconnect too
}
```

`pairing.failed` says how a pairing attempt ended without linking the number (`method` is `qr` or `code`):

```php
use WiApi\PairingFailed;
use WiApi\PairingFailureReason;

if ($event['event'] === 'pairing.failed') {
    $p = PairingFailed::fromEvent($event);
    if ($p->reason === PairingFailureReason::TIMEOUT) {
        // nobody read the QR or typed the code in time: call connect again
    }
    // an unknown reason ($p->isKnownReason() === false) is handled like "error"
}
```

Laravel:

```php
Route::post('/webhook', function (Request $request) {
    try {
        $event = Webhook::verifyDelivery(
            rawBody: $request->getContent(),
            headers: $request->headers->all(),
            secret:  config('services.wiapi.webhook_secret'),
        );
    } catch (WiException) {
        abort(401);
    }

    match ($event['event']) {
        'message' => MessageReceived::dispatch($event['data']),
        default   => null,
    };

    return response()->noContent();
});
```

## Error handling

```php
use WiApi\Exception\WiException;

try {
    $msg = $session->sendText(to: '5511999999999', text: 'Hello');
} catch (WiException $e) {
    echo $e->getMessage();
    echo $e->getStatusCode();
}
```

## Configuration

```php
$wi = new Wi(
    apiKey:  $_ENV['WI_API_KEY'],
    baseUrl: 'https://endpoint.wi.api.br', // default
    timeout: 30,                            // seconds
);
```

## Resources

- [Packagist](https://packagist.org/packages/wi-api/php)
- [Dashboard](https://wi.api.br)
- [Docs](https://docs.wi.api.br)
- [Changelog](https://github.com/wiApi/php/releases)
