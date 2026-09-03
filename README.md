# wi-api PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/wi-api/php?style=flat-square&color=0d9373)](https://packagist.org/packages/wi-api/php)
[![License](https://img.shields.io/badge/license-MIT-0d9373?style=flat-square)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-0d9373?style=flat-square)](https://www.php.net/)

Official PHP SDK for the [wi-api](https://wi.api.br) WhatsApp platform. Send and receive messages, manage sessions, and handle webhooks — no extra dependencies beyond `ext-curl` and `ext-json`.

---

## Requirements

- PHP 8.1+
- `ext-curl`
- `ext-json`

## Installation

```bash
composer require wi-api/php
```

---

## Quick start

```php
use WiApi\Wi;

$wi = new Wi(apiKey: $_ENV['WI_API_KEY']);
$session = $wi->session('my-instance');

$msg = $session->sendText(to: '5511999999999', text: 'Hello from wi-api');
echo $msg['messageId'];
```

---

## Configuration

```php
use WiApi\Wi;

$wi = new Wi(
    apiKey:  $_ENV['WI_API_KEY'],
    baseUrl: 'https://endpoint.wi.api.br', // optional, this is the default
    timeout: 30,                            // seconds, default 30
);
```

---

## Session lifecycle

```php
$session = $wi->session('my-instance');

// Start the connection flow
$session->connect();

// Poll for the QR code to display to the user
$qr = $session->qr();
echo $qr['qr']; // base64 PNG

// Or pair by phone number instead of QR
$pair = $session->pairPhone('5511999999999');
echo $pair['code']; // 8-character code to enter on the phone

// Check status
$status = $session->status();
echo $status['status']; // "open", "connecting", "close", etc.

// Disconnect (keeps session keys)
$session->disconnect();

// Logout and delete session keys
$session->logout();
```

---

## Sending messages

### Text

```php
$msg = $session->sendText(to: '5511999999999', text: 'Hello!');
echo $msg['messageId'];

// Reply to a message
$msg = $session->sendText(
    to:     '5511999999999',
    text:   'Got your message!',
    quoted: $originalMessageId,
);
```

### Image

```php
$msg = $session->sendImage(
    to:      '5511999999999',
    url:     'https://example.com/photo.jpg',
    caption: 'Check this out',
);
```

### Audio

```php
// Regular audio
$session->sendAudio(to: '5511999999999', url: 'https://example.com/audio.mp3');

// Voice note (push-to-talk)
$session->sendAudio(to: '5511999999999', url: 'https://example.com/voice.ogg', ptt: true);
```

### Video

```php
$session->sendVideo(
    to:      '5511999999999',
    url:     'https://example.com/video.mp4',
    caption: 'Watch this',
);
```

### Document

```php
$session->sendDocument(
    to:       '5511999999999',
    url:      'https://example.com/report.pdf',
    filename: 'monthly-report.pdf',
    caption:  'Here is the report',
);
```

### Location

```php
$session->sendLocation(
    to:        '5511999999999',
    latitude:  -23.5505,
    longitude: -46.6333,
    title:     'wi-api HQ',
);
```

### Contact

```php
$session->sendContact(
    to:            '5511999999999',
    contactName:   'John Doe',
    contactNumber: '5511888888888',
);
```

### Sticker

```php
$session->sendSticker(to: '5511999999999', url: 'https://example.com/sticker.webp');
```

### Poll

```php
$session->sendPoll(
    to:              '5511999999999',
    question:        'Favourite language?',
    options:         ['PHP', 'Go', 'Rust', 'Node.js'],
    multipleAnswers: false,
);
```

### Reactions

```php
$session->react(to: '5511999999999', messageId: $messageId, emoji: '👍');
```

---

## Chat

```php
// Mark a chat as read
$session->markRead(chatId: '5511999999999@s.whatsapp.net');

// Set presence ("composing", "recording", "paused")
$session->presence(chatId: '5511999999999@s.whatsapp.net', presence: 'composing');
```

---

## Webhooks

### Plain PHP

```php
use WiApi\Webhook;
use WiApi\Exception\WiException;

$body      = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WI_SIGNATURE'] ?? '';

try {
    $event = Webhook::verify(
        body:      $body,
        signature: $signature,
        secret:    $_ENV['WI_WEBHOOK_SECRET'],
    );

    match ($event['event']) {
        'message.received' => handleMessage($event['data']),
        'session.status'   => handleStatus($event['data']),
        default            => null,
    };

    http_response_code(200);
} catch (WiException $e) {
    http_response_code($e->getStatusCode() ?: 400);
}
```

### Laravel

```php
// routes/api.php
use Illuminate\Http\Request;
use WiApi\Webhook;
use WiApi\Exception\WiException;

Route::post('/webhook/whatsapp', function (Request $request) {
    try {
        $event = Webhook::verify(
            body:      $request->getContent(),
            signature: $request->header('x-wi-signature', ''),
            secret:    config('services.wiapi.webhook_secret'),
        );
    } catch (WiException $e) {
        abort(401, 'Invalid signature');
    }

    match ($event['event']) {
        'message.received' => MessageReceived::dispatch($event['data']),
        'session.status'   => SessionStatusChanged::dispatch($event['data']),
        default            => null,
    };

    return response()->noContent();
});
```

### Signature verification only (no throw)

```php
use WiApi\Webhook;

$valid = Webhook::verifySignature(
    body:      $rawBody,
    signature: $signatureHeader,
    secret:    $secret,
);
```

---

## Error handling

All API and network errors throw `WiApi\Exception\WiException`:

```php
use WiApi\Wi;
use WiApi\Exception\WiException;

try {
    $msg = $session->sendText(to: '5511999999999', text: 'Hello');
} catch (WiException $e) {
    echo $e->getMessage();    // human-readable error
    echo $e->getStatusCode(); // HTTP status (400, 401, 404, 429, 500, …)
    echo $e->errorCode;       // machine-readable code from the API
}
```

---

## Resources

- [Dashboard](https://wi.api.br) — manage sessions and webhooks
- [Docs](https://docs.wi.api.br) — full API reference
- [Changelog](https://github.com/wiApi/php/releases) — what's new
- [Packagist](https://packagist.org/packages/wi-api/php) — package registry
