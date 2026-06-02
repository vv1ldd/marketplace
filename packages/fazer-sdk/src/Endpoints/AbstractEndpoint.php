<?php

namespace FazerSdk\Endpoints;

use FazerSdk\Exceptions\FazerApiException;
use FazerSdk\FazerClient;
use Illuminate\Http\Client\Response;

abstract class AbstractEndpoint
{
    public function __construct(protected FazerClient $client) {}

    /**
     * @param  array<string, mixed>  $data
     */
    protected function request(string $method, string $path, array $data = []): Response
    {
        $response = match (strtoupper($method)) {
            'POST' => $this->client->client()->post($path, $data),
            'PUT' => $this->client->client()->put($path, $data),
            'DELETE' => $this->client->client()->delete($path, $data),
            default => $this->client->client()->get($path, $data),
        };

        if ($response->failed()) {
            throw new FazerApiException(
                "Fazer API Error [{$path}]: ".($response->json('message') ?? $response->body()),
                $response->status(),
            );
        }

        return $response;
    }
}
