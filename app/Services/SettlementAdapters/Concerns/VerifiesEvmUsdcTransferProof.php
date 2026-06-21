<?php

namespace App\Services\SettlementAdapters\Concerns;

use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;
use App\Support\EvmErc20TransferProofVerifier;
use App\Support\SettlementAdapterConfig;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait VerifiesEvmUsdcTransferProof
{
    abstract protected function networkKey(): string;

    abstract protected function erc20TransferProofVerifier(): EvmErc20TransferProofVerifier;

    abstract protected function walletBindings(): \App\Services\WalletBindingService;

    abstract protected function verificationEvents(): \App\Services\VerificationEventRecorder;

    abstract protected function settlementAuditEvents(): \App\Services\SettlementAuditEventRecorder;

    /**
     * @param array<string, mixed> $input
     */
    public function verifyProof(VaultIdentity $vault, array $input): VaultSettlementProof
    {
        if (! (bool) config('blockchain_networks.crypto_rails_enabled', false)) {
            throw ValidationException::withMessages([
                'binding_key' => 'On-chain transfer proofs are disabled. Simple commerce mode is active.',
            ]);
        }

        $bindingKey = $this->networkKey();
        if (trim((string) ($input['binding_key'] ?? '')) !== '' && trim((string) $input['binding_key']) !== $bindingKey) {
            throw ValidationException::withMessages([
                'binding_key' => 'Settlement proof binding key does not match this adapter.',
            ]);
        }

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
        $proofKind = VaultSettlementProof::KIND_USDC_TRANSFER;
        $externalReference = VaultSettlementProof::externalReferenceFor($proofKind, $transactionHash);

        if (VaultSettlementProof::query()
            ->where('vault_id', $vault->id)
            ->where('external_reference', $externalReference)
            ->exists()) {
            throw ValidationException::withMessages([
                'transaction_hash' => 'This transaction proof was already recorded for this vault.',
            ]);
        }

        $binding = $this->walletBindings()->findActiveWalletBinding($vault, $bindingKey);
        if ($sender === null && $binding instanceof IdentityBinding && $binding->isVerified()) {
            $sender = $binding->binding_value_normalized;
        }

        $proof = VaultSettlementProof::query()->create([
            'vault_id' => $vault->id,
            'identity_binding_id' => $binding?->id,
            'rail' => $bindingKey,
            'external_reference' => $externalReference,
            'proof_kind' => $proofKind,
            'asset' => (string) ($tokenConfig['asset'] ?? 'USDC'),
            'amount' => '0',
            'recipient' => $recipient,
            'status' => VaultSettlementProof::STATUS_PENDING,
            'metadata' => [
                'minimum_amount' => $minimumAmount,
                'minimum_amount_raw' => $minimumAmountRaw,
                'expected_sender' => $sender,
            ],
        ]);

        $result = $this->erc20TransferProofVerifier()->verify([
            'binding_key' => $bindingKey,
            'transaction_hash' => $transactionHash,
            'token_contract' => $tokenContract,
            'chain_id' => $chainId,
            'expected_recipient' => $recipient,
            'minimum_amount' => $minimumAmountRaw,
            'expected_sender' => $sender !== '' ? $sender : null,
        ]);

        if ($result['valid'] !== true) {
            $proof->transitionTo(VaultSettlementProof::STATUS_FAILED);

            $this->verificationEvents()->recordSettlementProofVerificationFailed(
                vaultId: (string) $vault->id,
                proofKind: $proofKind,
                bindingKey: $bindingKey,
                code: (string) ($result['error_code'] ?? 'proof_invalid'),
                message: (string) ($result['error'] ?? 'Transfer proof could not be verified.'),
                context: [
                    'transaction_hash' => $transactionHash,
                    'recipient' => $recipient,
                    'minimum_amount' => $minimumAmount,
                    'sender' => $sender,
                    'vault_settlement_proof_id' => $proof->id,
                ],
            );

            throw ValidationException::withMessages([
                'proof' => (string) ($result['error'] ?? 'Transfer proof could not be verified.'),
            ]);
        }

        $proofData = (array) ($result['proof'] ?? []);
        $evidence = [
            'chain_id' => (int) ($proofData['chain_id'] ?? $chainId),
            'token_contract' => (string) ($proofData['token_contract'] ?? $tokenContract),
            'transaction_hash' => (string) ($proofData['transaction_hash'] ?? $transactionHash),
            'sender' => (string) ($proofData['sender'] ?? ''),
            'recipient' => (string) ($proofData['recipient'] ?? $recipient),
            'amount' => (string) ($proofData['amount'] ?? '0'),
            'block_number' => (int) ($proofData['block_number'] ?? 0),
            'asset' => (string) ($tokenConfig['asset'] ?? 'USDC'),
        ];

        $proof->forceFill([
            'amount' => (string) ($evidence['amount'] ?? '0'),
            'recipient' => (string) ($evidence['recipient'] ?? $recipient),
            'evidence' => $evidence,
        ])->save();

        $proof->transitionTo(VaultSettlementProof::STATUS_OBSERVED);
        $proof->transitionTo(VaultSettlementProof::STATUS_VERIFIED);

        $this->verificationEvents()->recordSettlementProofVerified($proof);

        $this->settlementAuditEvents()->recordSettlementObserved(
            identityId: (string) $vault->refresh()->anchor_address,
            vaultId: (string) $vault->id,
            adapterKey: $bindingKey,
            asset: (string) ($evidence['asset'] ?? 'USDC'),
            amount: (string) ($evidence['amount'] ?? '0'),
            blockNumber: (int) ($evidence['block_number'] ?? 0),
        );

        return $proof->refresh();
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
