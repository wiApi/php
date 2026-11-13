<?php

declare(strict_types=1);

namespace WiApi\Exception;

class WiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly string $errorCode = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function fromResponse(int $statusCode, string $body): self
    {
        $data = json_decode($body, true);
        $message = "HTTP {$statusCode}";
        $errorCode = '';
        if (is_array($data)) {
            // Current shape: {"error":{"code","message"}}; legacy flat shape still read.
            if (isset($data['error']) && is_array($data['error'])) {
                $message = isset($data['error']['message']) ? (string) $data['error']['message'] : $message;
                $errorCode = isset($data['error']['code']) ? (string) $data['error']['code'] : '';
            } else {
                // Legacy flat shape: `message` is the text, `error` the code.
                if (isset($data['message'])) {
                    $message = (string) $data['message'];
                }
                $errorCode = isset($data['error'])
                    ? (string) $data['error']
                    : (isset($data['code']) ? (string) $data['code'] : '');
            }
        }

        return new self($message, $statusCode, $errorCode);
    }
}
