<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use SimpleLayer\Sl1e\Contracts\HttpClientInterface;
use SimpleLayer\Sl1e\Contracts\HttpResponse;

final class LaravelSl1eHttpClient implements HttpClientInterface
{
    public function postJson(string $url, array $payload, bool $verifyTls = true): HttpResponse
    {
        $client = Http::acceptJson()->timeout(10);

        if (! $verifyTls) {
            $client = $client->withoutVerifying();
        }

        $response = $client->post($url, $payload);

        return new HttpResponse(
            status: $response->status(),
            json: $response->json() ?? [],
            body: $response->body(),
        );
    }
}
