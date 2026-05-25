<?php

namespace App\Services\ZeroLayer;

use Illuminate\Support\Facades\Http;

class IndexNowClient
{
    public function submit(array $credentials, array $settings, array $urls): array
    {
        $key = $credentials['key'] ?? $settings['key'] ?? config('services.indexnow.key');
        $host = $settings['host'] ?? config('services.indexnow.host') ?? parse_url((string) config('app.url'), PHP_URL_HOST);
        $endpoint = $settings['endpoint'] ?? config('services.indexnow.endpoint', 'https://api.indexnow.org/indexnow');
        $keyLocation = $settings['key_location'] ?? ($host && $key ? 'https://'.$host.'/'.$key.'.txt' : null);

        if (! $key || ! $host) {
            throw new \InvalidArgumentException('IndexNow requires host and key.');
        }

        $urlList = array_values(array_unique(array_filter($urls)));

        $response = Http::acceptJson()
            ->post($endpoint, [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $keyLocation,
                'urlList' => $urlList,
            ])
            ->throw();

        return collect($urlList)->map(fn (string $url): array => [
            'source' => 'indexnow',
            'signal_type' => 'index_push',
            'signal_date' => now()->toDateString(),
            'page_url' => $url,
            'external_id' => $host,
            'payload' => [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ],
        ])->values()->all();
    }
}
