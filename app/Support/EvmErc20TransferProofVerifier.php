<?php

namespace App\Support;

use App\Contracts\TransferProofVerifier;
use App\Models\BindingProof;
use App\Services\SettlementNetworkRegistry;
use Illuminate\Support\Str;

class EvmErc20TransferProofVerifier implements TransferProofVerifier
{
    private const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    public function __construct(
        private readonly SettlementNetworkRegistry $settlementNetworks,
        private readonly EvmRpcClient $rpcClient,
    ) {}

    /**
     * @param array{
     *     binding_key: string,
     *     transaction_hash: string,
     *     token_contract: string,
     *     chain_id: int,
     *     expected_recipient: string,
     *     minimum_amount: string,
     *     expected_sender?: string|null
     * } $criteria
     * @return array{
     *     valid: bool,
     *     error?: string,
     *     error_code?: string,
     *     proof?: array<string, mixed>
     * }
     */
    public function verify(array $criteria): array
    {
        $bindingKey = trim((string) $criteria['binding_key']);
        $transactionHash = Str::lower(trim((string) $criteria['transaction_hash']));
        $tokenContract = Str::lower(trim((string) $criteria['token_contract']));
        $expectedRecipient = Str::lower(trim((string) $criteria['expected_recipient']));
        $minimumAmount = trim((string) $criteria['minimum_amount']);
        $expectedSender = isset($criteria['expected_sender'])
            ? Str::lower(trim((string) $criteria['expected_sender']))
            : null;
        $expectedChainId = (int) $criteria['chain_id'];

        if (! preg_match('/^0x[a-f0-9]{64}$/', $transactionHash)) {
            return $this->failure('invalid_transaction_hash', 'Transfer proof requires a valid 0x transaction hash.');
        }

        if (! preg_match('/^0x[a-f0-9]{40}$/', $tokenContract)) {
            return $this->failure('invalid_token_contract', 'Transfer proof requires a valid token contract address.');
        }

        if (! preg_match('/^0x[a-f0-9]{40}$/', $expectedRecipient)) {
            return $this->failure('invalid_recipient', 'Transfer proof requires a valid expected recipient address.');
        }

        if ($minimumAmount === '' || ! is_numeric($minimumAmount) || bccomp($minimumAmount, '0', 0) < 0) {
            return $this->failure('invalid_minimum_amount', 'Transfer proof requires a non-negative minimum amount.');
        }

        if ($expectedSender !== null && $expectedSender !== '' && ! preg_match('/^0x[a-f0-9]{40}$/', $expectedSender)) {
            return $this->failure('invalid_sender', 'Transfer proof sender must be a valid 0x address when provided.');
        }

        $network = $this->settlementNetworks->network($bindingKey);

        if ($network->protocol !== 'evm') {
            return $this->failure('unsupported_protocol', 'ERC-20 transfer proof is not supported for this binding key.');
        }

        if ($network->chainId !== null && $network->chainId !== $expectedChainId) {
            return $this->failure('chain_id_mismatch', 'Configured chain id does not match the selected binding key.');
        }

        if (! $network->rpcEnabled || ! $network->rpcUrl) {
            return $this->failure('rpc_unavailable', 'On-chain transfer proof verification is unavailable for this binding key.');
        }

        try {
            $receipt = $this->rpcClient->getTransactionReceipt($network->rpcUrl, $transactionHash);
        } catch (\Throwable) {
            return $this->failure('rpc_error', 'Could not verify transaction on chain RPC.');
        }

        if ($receipt === null) {
            return $this->failure('transaction_not_found', 'Transaction receipt was not found yet. Wait for finalization and try again.');
        }

        if (Str::lower((string) ($receipt['status'] ?? '')) !== '0x1') {
            return $this->failure('transaction_failed', 'Transaction failed on-chain.');
        }

        $rpcChainId = $this->rpcClient->getChainId($network->rpcUrl);
        if ($rpcChainId !== null && $rpcChainId !== $expectedChainId) {
            return $this->failure('chain_id_mismatch', 'RPC chain id does not match the expected network.');
        }

        $transfer = $this->findMatchingTransfer(
            logs: (array) ($receipt['logs'] ?? []),
            tokenContract: $tokenContract,
            expectedRecipient: $expectedRecipient,
            expectedSender: $expectedSender !== '' ? $expectedSender : null,
            minimumAmount: $minimumAmount,
        );

        if ($transfer === null) {
            return $this->failure(
                'transfer_not_found',
                'No finalized ERC-20 transfer matched the expected token, recipient, sender, and amount.',
            );
        }

        $blockNumber = $this->hexToInt((string) ($receipt['blockNumber'] ?? '0x0'));

        return [
            'valid' => true,
            'proof' => [
                'proof_type' => BindingProof::TYPE_USDC_TRANSFER,
                'binding_key' => $bindingKey,
                'chain_id' => $expectedChainId,
                'token_contract' => $tokenContract,
                'transaction_hash' => $transactionHash,
                'sender' => $transfer['sender'],
                'recipient' => $transfer['recipient'],
                'amount' => $transfer['amount'],
                'block_number' => $blockNumber,
                'verified_at' => now()->toJSON(),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array{sender: string, recipient: string, amount: string}|null
     */
    private function findMatchingTransfer(
        array $logs,
        string $tokenContract,
        string $expectedRecipient,
        ?string $expectedSender,
        string $minimumAmount,
    ): ?array {
        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }

            if (Str::lower((string) ($log['address'] ?? '')) !== $tokenContract) {
                continue;
            }

            $topics = (array) ($log['topics'] ?? []);
            if (count($topics) < 3) {
                continue;
            }

            if (Str::lower((string) $topics[0]) !== self::TRANSFER_TOPIC) {
                continue;
            }

            $sender = $this->topicToAddress((string) $topics[1]);
            $recipient = $this->topicToAddress((string) $topics[2]);
            $amount = $this->hexToDecimal((string) ($log['data'] ?? '0x0'));

            if ($recipient !== $expectedRecipient) {
                continue;
            }

            if ($expectedSender !== null && $sender !== $expectedSender) {
                continue;
            }

            if (bccomp($amount, $minimumAmount, 0) < 0) {
                continue;
            }

            return [
                'sender' => $sender,
                'recipient' => $recipient,
                'amount' => $amount,
            ];
        }

        return null;
    }

    /**
     * @return array{valid: false, error: string, error_code: string}
     */
    private function failure(string $code, string $message): array
    {
        return [
            'valid' => false,
            'error_code' => $code,
            'error' => $message,
        ];
    }

    private function topicToAddress(string $topic): string
    {
        $topic = Str::lower(trim($topic));

        return '0x'.substr($topic, -40);
    }

    private function hexToDecimal(string $hex): string
    {
        $hex = Str::lower(ltrim(trim($hex), '0x'));
        if ($hex === '') {
            return '0';
        }

        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }

        return (string) hexdec($hex);
    }

    private function hexToInt(string $hex): int
    {
        $hex = Str::lower(ltrim(trim($hex), '0x'));
        if ($hex === '') {
            return 0;
        }

        return (int) hexdec($hex);
    }
}
