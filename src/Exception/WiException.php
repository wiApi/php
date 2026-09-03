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
        $message = is_array($data) && isset($data['message'])
            ? (string) $data['message']
            : "HTTP {$statusCode}";
        $errorCode = is_array($data) && isset($data['error'])
            ? (string) $data['error']
            : '';

        return new self($message, $statusCode, $errorCode);
    }
}
