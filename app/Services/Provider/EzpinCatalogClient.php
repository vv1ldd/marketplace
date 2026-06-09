<?php

namespace App\Services\Provider;

use App\Models\Provider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EzpinCatalogClient
{
    private const DEFAULT_BASE_URL = 'https://api.ezpaypin.com/vendors/v2';

    private string $baseUrl;

    public function __construct(private readonly Provider $provider)
    {
        $providerBaseUrl = (string) data_get($provider->credentials, 'base_url', '');
        $providerBaseUrl = str_contains($providerBaseUrl, 'ezpaypin.com') ? $providerBaseUrl : '';
        $this->baseUrl = rtrim((string) (
            $providerBaseUrl
            ?: config('services.ezpin.base_url')
            ?: self::DEFAULT_BASE_URL
        ), '/');
    }

    /**
     * @return array{catalog: array<int, array<string, mixed>>, retailer: array<int, array<string, mixed>>}
     */
    public function pullCatalogs(): array
    {
        $token = $this->token();
        $client = Http::acceptJson()
            ->withToken($token)
            ->baseUrl($this->baseUrl)
            ->connectTimeout(10)
            ->timeout(45);

        return [
            'catalog' => $this->paginate($client, '/catalogs/'),
            'retailer' => $this->paginate($client, '/retailer_products/'),
        ];
    }

    private function token(): string
    {
        $clientId = (string) (data_get($this->provider->credentials, 'client_id') ?: config('services.ezpin.client_id'));
        $secretKey = (string) (
            data_get($this->provider->credentials, 'secret_key')
            ?: data_get($this->provider->credentials, 'client_secret')
            ?: config('services.ezpin.secret_key')
        );

        if ($clientId === '' || $secretKey === '') {
            throw new \RuntimeException('EZPin client_id and secret_key are required for direct catalog pull.');
        }

        $response = Http::acceptJson()
            ->baseUrl($this->baseUrl)
            ->connectTimeout(10)
            ->timeout(30)
            ->post('/auth/token/', [
                'client_id' => $clientId,
                'secret_key' => $secretKey,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('EZPin token request failed: '.$response->body());
        }

        $token = (string) $response->json('access');
        if ($token === '') {
            throw new \RuntimeException('EZPin token response did not include an access token.');
        }

        return $token;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paginate(PendingRequest $client, string $path): array
    {
        $items = [];
        $limit = 100;
        $offset = 0;

        do {
            $response = $client->get($path, [
                'limit' => $limit,
                'offset' => $offset,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException("EZPin catalog request failed for {$path}: ".$response->body());
            }

            $payload = $response->json();
            $results = is_array(data_get($payload, 'results')) ? data_get($payload, 'results') : [];
            $items = array_merge($items, $results);

            $count = (int) data_get($payload, 'count', count($items));
            $offset += $limit;
            $hasNext = filled(data_get($payload, 'next')) || count($items) < $count;
        } while ($hasNext && $results !== []);

        return $items;
    }
}
