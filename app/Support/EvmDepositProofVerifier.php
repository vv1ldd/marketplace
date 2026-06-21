<?php

namespace App\Support;

use App\Services\SettlementNetworkResolver;
use Illuminate\Support\Str;

class EvmDepositProofVerifier
{
    public function __construct(
        private readonly SettlementNetworkResolver $networks,
        private readonly EvmRpcClient $rpcClient,
    ) {}

    /**
     * @param array<string, mixed> $proofPayload
     * @return array{valid: bool, error?: string, proof?: array<string, mixed>, verification?: string}
     */
    public function verify(string $networkKey, array $proofPayload): array
    {
        $network = $this->networks->resolve($networkKey);
        $txHash = strtolower(trim((string) ($proofPayload['tx_hash'] ?? $proofPayload['transaction_hash'] ?? '')));
        $asset = strtoupper(trim((string) ($proofPayload['asset'] ?? '')));
        $depositAddress = Str::lower(trim((string) ($proofPayload['to_address'] ?? $proofPayload['deposit_address'] ?? '')));

        if ($txHash === '' || ! preg_match('/^0x[a-f0-9]{64}$/', $txHash)) {
            return [
                'valid' => false,
                'error' => 'EVM deposit proof requires a valid 0x transaction hash.',
            ];
        }

        if ($asset === '' || ! in_array($asset, $network->assets, true)) {
            return [
                'valid' => false,
                'error' => 'EVM deposit proof asset is missing or not supported on '.$network->label.'.',
            ];
        }

        $amount = $proofPayload['amount'] ?? $proofPayload['token_amount'] ?? null;
        if ($amount !== null && ! is_numeric($amount)) {
            return [
                'valid' => false,
                'error' => 'EVM deposit proof amount must be numeric when provided.',
            ];
        }

        $proof = [
            'settlement_network' => $network->key,
            'network' => 'evm',
            'chain_id' => $network->chainId,
            'tx_hash' => $txHash,
            'asset' => $asset,
            'amount' => $amount !== null ? (string) $amount : null,
            'to_address' => $depositAddress !== '' ? $depositAddress : null,
            'verified_at' => now()->toJSON(),
        ];

        if (! $network->rpcEnabled || ! $network->rpcUrl) {
            return [
                'valid' => true,
                'verification' => 'structural',
                'proof' => $proof,
            ];
        }

        try {
            $receipt = $this->rpcClient->getTransactionReceipt($network->rpcUrl, $txHash);
        } catch (\Throwable $exception) {
            return [
                'valid' => false,
                'error' => 'Could not verify transaction on '.$network->label.' RPC.',
            ];
        }

        if ($receipt === null) {
            return [
                'valid' => false,
                'error' => 'Transaction receipt was not found yet. Wait for confirmations and try again.',
            ];
        }

        if (strtolower((string) ($receipt['status'] ?? '')) !== '0x1') {
            return [
                'valid' => false,
                'error' => 'Transaction failed on-chain.',
            ];
        }

        if ($network->chainId !== null) {
            $chainId = $this->rpcClient->getChainId($network->rpcUrl);
            if ($chainId !== null && $chainId !== $network->chainId) {
                return [
                    'valid' => false,
                    'error' => 'RPC chain id does not match '.$network->label.'.',
                ];
            }
        }

        $receiptTo = Str::lower((string) ($receipt['to'] ?? ''));
        if ($depositAddress !== '' && $receiptTo !== '' && $receiptTo !== $depositAddress) {
            return [
                'valid' => false,
                'error' => 'Transaction recipient does not match the issued deposit address.',
            ];
        }

        $proof['receipt_to'] = $receiptTo !== '' ? $receiptTo : null;
        $proof['block_number'] = $receipt['blockNumber'] ?? null;
        $proof['confirmation_count'] = max(1, $network->requiredConfirmations);

        return [
            'valid' => true,
            'verification' => 'rpc_receipt',
            'proof' => $proof,
        ];
    }
}
