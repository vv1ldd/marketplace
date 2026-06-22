<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitcoinRpcClient
{
    /**
     * @return array<string, mixed>|null
     */
    public function getBlockchainInfo(string $rpcUrl): ?array
    {
        $payload = $this->rpcCall($rpcUrl, 'getblockchaininfo', []);

        return is_array($payload) ? $payload : null;
    }

    public function getAddressBalanceBtc(string $rpcUrl, string $address): ?string
    {
        $payload = $this->rpcCall($rpcUrl, 'scantxoutset', ['start', ['addr('.$address.')']]);
        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            return null;
        }

        $amount = (string) ($payload['total_amount'] ?? '0');

        return $amount === '' ? '0' : $amount;
    }

    public function getAddressBalanceFromEsplora(string $apiBaseUrl, string $address): ?string
    {
        $response = Http::timeout(12)
            ->acceptJson()
            ->get(rtrim($apiBaseUrl, '/').'/address/'.rawurlencode($address));

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        $funded = (string) ($body['chain_stats']['funded_txo_sum'] ?? '0');
        $spent = (string) ($body['chain_stats']['spent_txo_sum'] ?? '0');
        $sats = bcsub($funded, $spent, 0);
        if (bccomp($sats, '0', 0) < 0) {
            $sats = '0';
        }

        $amountBtc = bcdiv($sats, '100000000', 8);

        return rtrim(rtrim($amountBtc, '0'), '.') ?: '0';
    }

    /**
     * @return mixed
     */
    private function rpcCall(string $rpcUrl, string $method, array $params)
    {
        $response = Http::timeout(12)
            ->acceptJson()
            ->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Bitcoin RPC request failed.');
        }

        $body = $response->json();
        if (! is_array($body) || array_key_exists('error', $body)) {
            throw new RuntimeException('Bitcoin RPC returned an error.');
        }

        return $body['result'] ?? null;
    }
}
