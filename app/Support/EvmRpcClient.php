<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EvmRpcClient
{
    /**
     * @return array<string, mixed>|null
     */
    public function getTransactionReceipt(string $rpcUrl, string $txHash): ?array
    {
        $payload = $this->rpcCall($rpcUrl, 'eth_getTransactionReceipt', [$txHash]);

        return is_array($payload) ? $payload : null;
    }

    public function getChainId(string $rpcUrl): ?int
    {
        $payload = $this->rpcCall($rpcUrl, 'eth_chainId', []);
        if (! is_string($payload) || ! str_starts_with($payload, '0x')) {
            return null;
        }

        return (int) hexdec($payload);
    }

    public function ethCall(string $rpcUrl, string $to, string $data, string $block = 'latest'): ?string
    {
        $payload = $this->rpcCall($rpcUrl, 'eth_call', [[
            'to' => $to,
            'data' => $data,
        ], $block]);

        return is_string($payload) ? $payload : null;
    }

    /**
     * @return mixed
     */
    private function rpcCall(string $rpcUrl, string $method, array $params)
    {
        $response = Http::timeout(8)
            ->acceptJson()
            ->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('EVM RPC request failed.');
        }

        $body = $response->json();
        if (! is_array($body) || array_key_exists('error', $body)) {
            throw new RuntimeException('EVM RPC returned an error response.');
        }

        return $body['result'] ?? null;
    }
}
