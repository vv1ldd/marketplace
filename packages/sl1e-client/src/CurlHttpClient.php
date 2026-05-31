<?php

namespace SimpleLayer\Sl1e;

use SimpleLayer\Sl1e\Contracts\HttpClientInterface;
use SimpleLayer\Sl1e\Contracts\HttpResponse;
use SimpleLayer\Sl1e\Exception\Sl1eTransportException;

final class CurlHttpClient implements HttpClientInterface
{
    public function postJson(string $url, array $payload, bool $verifyTls = true): HttpResponse
    {
        if (! function_exists('curl_init')) {
            throw new Sl1eTransportException('ext-curl is required for CurlHttpClient.');
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $verifyTls,
            CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
        ]);

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $message = curl_error($handle) ?: 'Unknown cURL error.';
            curl_close($handle);

            throw new Sl1eTransportException($message);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $decoded = json_decode((string) $responseBody, true);

        return new HttpResponse(
            status: $status,
            json: is_array($decoded) ? $decoded : [],
            body: (string) $responseBody,
        );
    }
}
