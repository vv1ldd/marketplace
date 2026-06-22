<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SolanaRpcClient
{
    public function getHealth(string $rpcUrl): ?string
    {
        $result = $this->rpcCall($rpcUrl, 'getHealth', []);

        return is_string($result) ? $result : null;
    }

    public function getGenesisHash(string $rpcUrl): ?string
    {
        $result = $this->rpcCall($rpcUrl, 'getGenesisHash', []);

        return is_string($result) ? $result : null;
    }

    public function getBalanceSol(string $rpcUrl, string $address): ?string
    {
        $result = $this->rpcCall($rpcUrl, 'getBalance', [
            $address,
            ['commitment' => 'confirmed'],
        ]);

        if (! is_array($result) || ! array_key_exists('value', $result)) {
            return null;
        }

        $lamports = (string) ($result['value'] ?? '0');

        return rtrim(rtrim(bcdiv($lamports, '1000000000', 9), '0'), '.') ?: '0';
    }

    public function getSplTokenBalance(string $rpcUrl, string $owner, string $mint, int $decimals = 6): ?string
    {
        $result = $this->rpcCall($rpcUrl, 'getTokenAccountsByOwner', [
            $owner,
            ['mint' => $mint],
            ['encoding' => 'jsonParsed'],
        ]);

        if (! is_array($result)) {
            return null;
        }

        $total = '0';

        foreach ($result['value'] ?? [] as $account) {
            if (! is_array($account)) {
                continue;
            }

            $amount = data_get($account, 'account.data.parsed.info.tokenAmount.uiAmountString');
            if (! is_string($amount) && ! is_numeric($amount)) {
                continue;
            }

            $total = bcadd($total, (string) $amount, $decimals);
        }

        return $total;
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
            throw new RuntimeException('Solana RPC request failed.');
        }

        $body = $response->json();
        if (! is_array($body) || array_key_exists('error', $body)) {
            throw new RuntimeException('Solana RPC returned an error.');
        }

        return $body['result'] ?? null;
    }
}
