<?php

namespace Tests\Feature;

use App\Support\EvmDepositProofVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvmDepositProofVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_rpc_verification_accepts_successful_receipt(): void
    {
        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        Http::fake([
            'https://polygon-rpc.test' => Http::sequence()
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'status' => '0x1',
                        'to' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                        'blockNumber' => '0x10',
                    ],
                ])
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x89',
                ]),
        ]);

        $depositAddress = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';
        $txHash = '0x'.str_repeat('b', 64);

        $result = app(EvmDepositProofVerifier::class)->verify('polygon', [
            'tx_hash' => $txHash,
            'asset' => 'USDT',
            'deposit_address' => $depositAddress,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('rpc_receipt', $result['verification']);
        $this->assertSame($txHash, $result['proof']['tx_hash']);
        $this->assertSame($depositAddress, $result['proof']['to_address']);
    }

    public function test_rpc_verification_rejects_failed_receipt(): void
    {
        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        Http::fake([
            'https://polygon-rpc.test' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'status' => '0x0',
                    'to' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                ],
            ]),
        ]);

        $result = app(EvmDepositProofVerifier::class)->verify('polygon', [
            'tx_hash' => '0x'.str_repeat('c', 64),
            'asset' => 'USDC',
            'deposit_address' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertSame('Transaction failed on-chain.', $result['error']);
    }

    public function test_rpc_verification_rejects_recipient_mismatch(): void
    {
        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        Http::fake([
            'https://polygon-rpc.test' => Http::sequence()
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'status' => '0x1',
                        'to' => '0x1111111111111111111111111111111111111111',
                    ],
                ])
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x89',
                ]),
        ]);

        $result = app(EvmDepositProofVerifier::class)->verify('polygon', [
            'tx_hash' => '0x'.str_repeat('d', 64),
            'asset' => 'USDT',
            'deposit_address' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertSame(
            'Transaction recipient does not match the issued deposit address.',
            $result['error'],
        );
    }
}
