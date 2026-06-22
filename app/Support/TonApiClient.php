<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TonApiClient
{
    public function getAccount(string $apiBaseUrl, string $address): ?array
    {
        $response = Http::timeout(12)
            ->acceptJson()
            ->get(rtrim($apiBaseUrl, '/').'/accounts/'.rawurlencode($address));

        if (! $response->successful()) {
            throw new RuntimeException('TON API request failed.');
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    public function getBalanceTon(string $apiBaseUrl, string $address): ?string
    {
        $account = $this->getAccount($apiBaseUrl, $address);
        if (! is_array($account)) {
            return null;
        }

        $nanotons = (string) ($account['balance'] ?? '0');

        return rtrim(rtrim(bcdiv($nanotons, '1000000000', 9), '0'), '.') ?: '0';
    }
}
