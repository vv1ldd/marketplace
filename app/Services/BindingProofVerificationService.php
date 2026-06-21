<?php

namespace App\Services;

use App\Models\BindingProof;
use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Support\EvmErc20TransferProofVerifier;
use App\Support\SettlementAdapterConfig;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BindingProofVerificationService
{
    public function __construct(
        private readonly WalletBindingService $bindings,
        private readonly EvmErc20TransferProofVerifier $erc20TransferProofs,
        private readonly VerificationEventRecorder $verificationEvents,
        private readonly SettlementAuditEventRecorder $settlementAuditEvents,
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public function verifyUsdcTransfer(VaultIdentity $vault, array $input): BindingProof
    {
        if (! (bool) config('blockchain_networks.crypto_rails_enabled', false)) {
            throw ValidationException::withMessages([
                'binding_key' => 'On-chain transfer proofs are disabled. Simple commerce mode is active.',
            ]);
        }

        $bindingKey = trim((string) ($input['binding_key'] ?? ''));
        if (! SettlementAdapterConfig::allowsWrite($bindingKey)) {
            throw ValidationException::withMessages([
                'binding_key' => 'Settlement write actions are disabled for this adapter. Read-only observation mode is active.',
            ]);
        }
        $transactionHash = Str::lower(trim((string) ($input['transaction_hash'] ?? '')));
        $recipient = Str::lower(trim((string) ($input['recipient'] ?? '')));
        $minimumAmount = trim((string) ($input['minimum_amount'] ?? ''));
        $sender = array_key_exists('sender', $input)
            ? Str::lower(trim((string) $input['sender']))
            : null;

        $tokenConfig = config('verification_proofs.usdc_transfer.'.$bindingKey);
        if (! is_array($tokenConfig)) {
            throw ValidationException::withMessages([
                'binding_key' => 'USDC transfer proof is not configured for this binding key.',
            ]);
        }

        $tokenContract = Str::lower(trim((string) ($tokenConfig['token_contract'] ?? '')));
        $chainId = (int) ($tokenConfig['chain_id'] ?? 0);
        $decimals = (int) ($tokenConfig['decimals'] ?? 6);
        $minimumAmountRaw = $this->toTokenBaseUnits($minimumAmount, $decimals);
        $proofReference = BindingProof::referenceFor(BindingProof::TYPE_USDC_TRANSFER, $transactionHash);

        if (BindingProof::query()
            ->where('vault_id', $vault->id)
            ->where('proof_reference', $proofReference)
            ->exists()) {
            throw ValidationException::withMessages([
                'transaction_hash' => 'This transaction proof was already recorded for this vault.',
            ]);
        }

        $binding = $this->bindings->findActiveWalletBinding($vault, $bindingKey);
        if ($sender === null && $binding instanceof IdentityBinding && $binding->isVerified()) {
            $sender = $binding->binding_value_normalized;
        }

        $result = $this->erc20TransferProofs->verify([
            'binding_key' => $bindingKey,
            'transaction_hash' => $transactionHash,
            'token_contract' => $tokenContract,
            'chain_id' => $chainId,
            'expected_recipient' => $recipient,
            'minimum_amount' => $minimumAmountRaw,
            'expected_sender' => $sender !== '' ? $sender : null,
        ]);

        if ($result['valid'] !== true) {
            $this->verificationEvents->recordProofVerificationFailed(
                vaultId: (string) $vault->id,
                proofType: BindingProof::TYPE_USDC_TRANSFER,
                bindingKey: $bindingKey,
                code: (string) ($result['error_code'] ?? 'proof_invalid'),
                message: (string) ($result['error'] ?? 'Transfer proof could not be verified.'),
                context: [
                    'transaction_hash' => $transactionHash,
                    'recipient' => $recipient,
                    'minimum_amount' => $minimumAmount,
                    'sender' => $sender,
                ],
            );

            throw ValidationException::withMessages([
                'proof' => (string) ($result['error'] ?? 'Transfer proof could not be verified.'),
            ]);
        }

        $proofData = (array) ($result['proof'] ?? []);
        $verifiedAt = now();
        $proofPayload = [
            'chain_id' => (int) ($proofData['chain_id'] ?? $chainId),
            'token_contract' => (string) ($proofData['token_contract'] ?? $tokenContract),
            'transaction_hash' => (string) ($proofData['transaction_hash'] ?? $transactionHash),
            'sender' => (string) ($proofData['sender'] ?? ''),
            'recipient' => (string) ($proofData['recipient'] ?? $recipient),
            'amount' => (string) ($proofData['amount'] ?? '0'),
            'block_number' => (int) ($proofData['block_number'] ?? 0),
            'asset' => (string) ($tokenConfig['asset'] ?? 'USDC'),
        ];

        $proof = BindingProof::query()->create([
            'vault_id' => $vault->id,
            'identity_binding_id' => $binding?->id,
            'proof_type' => BindingProof::TYPE_USDC_TRANSFER,
            'binding_key' => $bindingKey,
            'proof_reference' => $proofReference,
            'verification_state' => BindingProof::STATE_VERIFIED,
            'proof_payload' => $proofPayload,
            'verified_at' => $verifiedAt,
            'metadata' => [
                'minimum_amount' => $minimumAmount,
                'minimum_amount_raw' => $minimumAmountRaw,
            ],
        ]);

        $this->verificationEvents->recordProofVerified($proof);

        $vaultIdentity = $vault->refresh();
        $this->settlementAuditEvents->recordSettlementObserved(
            identityId: (string) $vaultIdentity->anchor_address,
            vaultId: (string) $vault->id,
            adapterKey: $bindingKey,
            asset: (string) ($proofPayload['asset'] ?? 'USDC'),
            amount: (string) ($proofPayload['amount'] ?? '0'),
            blockNumber: (int) ($proofPayload['block_number'] ?? 0),
        );

        return $proof->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatProof(BindingProof $proof): array
    {
        $payload = (array) ($proof->proof_payload ?? []);

        return [
            'id' => $proof->id,
            'vault_id' => $proof->vault_id,
            'identity_binding_id' => $proof->identity_binding_id,
            'proof_type' => $proof->proof_type,
            'binding_key' => $proof->binding_key,
            'proof_reference' => $proof->proof_reference,
            'verification_state' => $proof->verification_state,
            'proof_payload' => $payload,
            'verified_at' => $proof->verified_at?->toJSON(),
            'metadata' => $proof->metadata ?? [],
            'chain_id' => $payload['chain_id'] ?? null,
            'token_contract' => $payload['token_contract'] ?? null,
            'transaction_hash' => $payload['transaction_hash'] ?? null,
            'sender' => $payload['sender'] ?? null,
            'recipient' => $payload['recipient'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'block_number' => $payload['block_number'] ?? null,
        ];
    }

    private function toTokenBaseUnits(string $amount, int $decimals): string
    {
        if (! preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw ValidationException::withMessages([
                'minimum_amount' => 'Minimum amount must be a decimal number.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);

        return ltrim($whole.$fraction, '0') ?: '0';
    }
}
