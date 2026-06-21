<?php

namespace App\Services;

use App\Contracts\BindingChallengeFormatter;
use App\Models\BindingChallenge;
use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Support\BitcoinMessageSignVerifier;
use App\Support\EvmPersonalSignVerifier;
use App\Support\SolanaMessageSignVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletBindingChallengeService
{
    public function __construct(
        private readonly SettlementNetworkRegistry $settlementNetworks,
        private readonly WalletBindingService $bindings,
        private readonly EvmPersonalSignVerifier $evmSignatures,
        private readonly BitcoinMessageSignVerifier $bitcoinSignatures,
        private readonly SolanaMessageSignVerifier $solanaSignatures,
        private readonly BindingChallengeFormatter $challengeFormatter,
        private readonly BindingEventRecorder $bindingEvents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function issueChallenge(
        VaultIdentity $vault,
        string $bindingType,
        string $bindingKey,
        string $bindingValue,
        string $verificationMethod = BindingChallenge::METHOD_SIGNATURE,
    ): array {
        $bindingType = trim($bindingType);
        $bindingKey = trim($bindingKey);
        $bindingValue = trim($bindingValue);

        if ($bindingType !== IdentityBinding::TYPE_WALLET) {
            throw ValidationException::withMessages([
                'binding_type' => 'Only wallet binding challenges are supported right now.',
            ]);
        }

        $network = $this->bindings->resolveWalletNetwork($bindingKey);
        $canonical = $this->bindings->canonicalizeWalletBindingValue($network->protocol, $bindingValue);
        $this->bindings->assertWalletAddressFormat($network->protocol, $canonical['original']);

        if ($this->bindings->findActiveWalletBinding($vault, $bindingKey)) {
            throw ValidationException::withMessages([
                'binding_key' => 'An active wallet binding already exists for this transport layer.',
            ]);
        }

        $this->bindings->assertActiveBindingValueAvailable(
            bindingType: $bindingType,
            bindingKey: $bindingKey,
            bindingValueNormalized: $canonical['normalized'],
            vaultId: $vault->id,
        );

        $nonce = Str::lower(Str::random(32));
        $expiresAt = now()->addMinutes(10);
        $message = $this->challengeFormatter->format(
            vault: $vault,
            bindingType: $bindingType,
            bindingKey: $bindingKey,
            bindingValueNormalized: $canonical['normalized'],
            nonce: $nonce,
            expiresAt: $expiresAt,
        );

        $challenge = BindingChallenge::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => $bindingType,
            'binding_key' => $bindingKey,
            'binding_value_original' => $canonical['original'],
            'binding_value_normalized' => $canonical['normalized'],
            'nonce' => $nonce,
            'message' => $message,
            'verification_method' => $verificationMethod,
            'expires_at' => $expiresAt,
            'metadata' => [
                'network_label' => $network->label,
                'protocol' => $network->protocol,
            ],
        ]);

        return $this->formatChallenge($challenge);
    }

    public function verifyChallenge(VaultIdentity $vault, string $nonce, string $signature, ?string $signedMessage = null): IdentityBinding
    {
        $nonce = Str::lower(trim($nonce));
        $signature = trim($signature);

        $challenge = BindingChallenge::query()
            ->where('vault_id', $vault->id)
            ->where('nonce', $nonce)
            ->first();

        if (! $challenge instanceof BindingChallenge) {
            throw ValidationException::withMessages([
                'nonce' => 'Binding challenge was not found for this vault identity.',
            ]);
        }

        if ($challenge->isConsumed()) {
            $this->failVerification($challenge, 'challenge_consumed', 'Binding challenge was already consumed.', 'nonce');
        }

        if ($challenge->isExpired()) {
            $this->failVerification($challenge, 'challenge_expired', 'Binding challenge expired. Request a new challenge.', 'nonce');
        }

        $network = $this->bindings->resolveWalletNetwork($challenge->binding_key);

        if ($network->protocol === 'evm') {
            $recoveredAddress = $this->evmSignatures->recoverAddress($challenge->message, $signature);

            if ($recoveredAddress === null || $recoveredAddress !== $challenge->binding_value_normalized) {
                $this->failVerification(
                    $challenge,
                    'signature_mismatch',
                    'Signature does not prove ownership of the requested wallet address.',
                    'signature',
                );
            }
        } elseif ($network->protocol === 'utxo') {
            if ($this->looksLikeBip322Signature($signature) && ! $this->bitcoinSignatures->bip322RuntimeAvailable()) {
                $this->failVerification(
                    $challenge,
                    'bip322_unavailable',
                    'Bitcoin ownership verification is unavailable on this server right now. Try again after deploy, or connect with Unisat/Xverse.',
                    'signature',
                );
            }

            $verified = $this->bitcoinSignatures->verifyMessage(
                $challenge->message,
                $signature,
                (string) $challenge->binding_value_original,
            ) || $this->bitcoinSignatures->verifyMessage(
                $challenge->message,
                $signature,
                (string) $challenge->binding_value_normalized,
            );

            if (! $verified) {
                $this->failVerification(
                    $challenge,
                    'signature_mismatch',
                    'Signature does not prove ownership of the requested wallet address.',
                    'signature',
                );
            }
        } elseif ($network->protocol === 'solana') {
            if (! $this->solanaSignatures->runtimeAvailable()) {
                $this->failVerification(
                    $challenge,
                    'solana_unavailable',
                    'Solana ownership verification is unavailable on this server right now.',
                    'signature',
                );
            }

            $verified = $this->solanaSignatures->verifyMessage(
                $challenge->message,
                $signature,
                (string) $challenge->binding_value_original,
                $signedMessage,
            ) || $this->solanaSignatures->verifyMessage(
                $challenge->message,
                $signature,
                (string) $challenge->binding_value_normalized,
                $signedMessage,
            );

            if (! $verified) {
                $this->failVerification(
                    $challenge,
                    'signature_mismatch',
                    'Signature does not prove ownership of the requested wallet address.',
                    'signature',
                );
            }
        } else {
            $this->failVerification(
                $challenge,
                'unsupported_protocol',
                'Signature verification is not implemented for this transport layer yet.',
                'signature',
            );
        }

        try {
            return DB::transaction(function () use ($vault, $challenge): IdentityBinding {
                $lockedChallenge = BindingChallenge::query()
                    ->whereKey($challenge->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedChallenge->isConsumed()) {
                    $this->failVerification($lockedChallenge, 'challenge_consumed', 'Binding challenge was already consumed.', 'nonce');
                }

                if ($lockedChallenge->isExpired()) {
                    $this->failVerification($lockedChallenge, 'challenge_expired', 'Binding challenge expired. Request a new challenge.', 'nonce');
                }

                $binding = $this->bindings->createVerifiedWalletBinding(
                    vault: $vault,
                    networkKey: $lockedChallenge->binding_key,
                    address: $lockedChallenge->binding_value_original,
                    verificationMethod: IdentityBinding::METHOD_SIGNATURE,
                    metadata: [
                        'challenge_id' => $lockedChallenge->id,
                        'nonce' => $lockedChallenge->nonce,
                        'network_label' => data_get($lockedChallenge->metadata, 'network_label'),
                        'protocol' => data_get($lockedChallenge->metadata, 'protocol'),
                    ],
                );

                $lockedChallenge->forceFill([
                    'consumed_at' => now(),
                    'last_verification_error' => null,
                ])->save();

                return $binding;
            });
        } catch (ValidationException $exception) {
            $challenge->refresh();
            if (! $challenge->isConsumed()) {
                $message = collect($exception->errors())->flatten()->first() ?: 'Wallet binding could not be created.';
                $this->recordVerificationFailure($challenge, 'binding_create_failed', $message);
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formatChallenge(BindingChallenge $challenge): array
    {
        return [
            'id' => $challenge->id,
            'vault_id' => $challenge->vault_id,
            'binding_type' => $challenge->binding_type,
            'binding_key' => $challenge->binding_key,
            'binding_value' => $challenge->binding_value_normalized,
            'binding_value_original' => $challenge->binding_value_original,
            'binding_value_normalized' => $challenge->binding_value_normalized,
            'nonce' => $challenge->nonce,
            'message' => $challenge->message,
            'verification_method' => $challenge->verification_method,
            'expires_at' => $challenge->expires_at?->toJSON(),
            'consumed_at' => $challenge->consumed_at?->toJSON(),
            'verification_attempt_count' => (int) $challenge->verification_attempt_count,
            'last_verification_error' => $challenge->last_verification_error,
        ];
    }

    /**
     * @return never
     */
    private function failVerification(BindingChallenge $challenge, string $code, string $message, string $field): void
    {
        $this->recordVerificationFailure($challenge, $code, $message);

        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    private function looksLikeBip322Signature(string $signature): bool
    {
        $signature = trim($signature);

        return str_starts_with($signature, 'smp')
            || str_starts_with($signature, 'ful')
            || str_starts_with($signature, 'pof');
    }

    private function recordVerificationFailure(BindingChallenge $challenge, string $code, string $message): void
    {
        $this->bindingEvents->recordWalletBindingFailed($challenge, $code, $message);

        $challenge->forceFill([
            'verification_attempt_count' => (int) $challenge->verification_attempt_count + 1,
            'last_verification_error' => [
                'code' => $code,
                'message' => $message,
                'at' => now()->toIso8601String(),
            ],
        ])->save();
    }
}
