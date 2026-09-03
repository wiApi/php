<?php

declare(strict_types=1);

namespace WiApi;

use WiApi\Exception\WiException;

class Wi
{
    private readonly string $baseUrl;

    public function __construct(
        public readonly string $apiKey,
        string $baseUrl = 'https://endpoint.wi.api.br',
        private readonly int $timeout = 30,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('wi-api: apiKey is required');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Get a Session client scoped to the given session ID.
     *
     * @example
     * $session = $wi->session('my-instance');
     * $msg = $session->sendText(to: '5511999999999', text: 'Hello!');
     */
    public function session(string $id): Session
    {
        return new Session($id, $this);
    }

    /**
     * Execute an HTTP request against the wi-api endpoint.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     * @throws WiException on 4xx/5xx responses or network errors
     * @internal
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;

        $headers = [
            'x-api-key: ' . $this->apiKey,
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new WiException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);

        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
        } elseif (strtoupper($method) === 'POST') {
            // POST with empty body
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            $headers[] = 'Content-Length: 0';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new WiException('cURL error: ' . $curlError);
        }

        /** @var string $response */
        if ($statusCode >= 400) {
            throw WiException::fromResponse($statusCode, $response);
        }

        if ($response === '' || $response === 'null') {
            return [];
        }

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
