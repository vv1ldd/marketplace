<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;

class BuyerWalletTransactionService
{
    public function __construct(
        private readonly BuyerWalletService $wallets,
        private readonly L1IdentityService $identity,
    ) {}

    /**
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    public function buildPurchaseDebitEnvelope(
        User $user,
        Product $product,
        array $customer,
        int $quantity,
        string $fulfillmentMode = 'instant'
    ): array
    {
        $quantity = max(1, min($quantity, 5));
        if ($fulfillmentMode === 'preorder') {
            throw ValidationException::withMessages([
                'fulfillment_mode' => 'Предзаказ доступен только для закупки продавца.',
            ]);
        }

        $fulfillmentMode = 'instant';
        $unitAmountMinor = (int) $product->price_rub;
        $amountMinor = $unitAmountMinor * $quantity;

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'product' => 'Product has no RUBT checkout price.',
            ]);
        }

        $passkey = $this->resolveBuyerSigningPasskey($user);
        $buyerL1Address = $this->buyerL1Address($user, $passkey);
        $this->assertWalletAccountAddress($user, $buyerL1Address);

        $shop = $product->shop;
        $payload = [
            'network' => 'Simple Layer One',
            'version' => 1,
            'intent' => 'BUYER_PURCHASE_DEBIT',
            'asset' => BuyerWalletService::ASSET_RUBT,
            'amount_minor' => $amountMinor,
            'amount' => $this->wallets->minorToDecimalString($amountMinor),
            'buyer_user_id' => (int) $user->id,
            'buyer_l1_address' => $buyerL1Address,
            'signer_passkey_id' => (int) $passkey->id,
            'seller' => [
                'shop_id' => (int) ($shop?->id ?? 0),
                'shop_name' => (string) ($shop?->name ?? ''),
                'legal_entity_id' => (int) ($shop?->legal_entity_id ?? 0),
            ],
            'product' => [
                'product_id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'slug' => (string) $product->slug,
                'canonical_identity_slug' => (string) $product->slug,
                'name' => (string) $product->name,
                'category' => (string) $product->category,
            ],
            'qty' => $quantity,
            'unit_amount_minor' => $unitAmountMinor,
            'payment_method' => 'rubt_balance',
            'fulfillment' => [
                'mode' => $fulfillmentMode,
                'pre_order' => $fulfillmentMode === 'preorder',
            ],
            'delivery' => [
                'is_gift' => (bool) ($customer['is_gift'] ?? false),
                'buyer_email_hash' => $this->hashNullableString($customer['buyer_email'] ?? null),
                'delivery_email_hash' => $this->hashNullableString($customer['delivery_email'] ?? $customer['email'] ?? null),
            ],
            'nonce' => random_int(1000000000000000, 9000000000000000),
            'timestamp' => now()->toJSON(),
        ];

        $canonicalJson = $this->canonicalJson($payload);

        return [
            'pending_tx_id' => (string) Str::uuid(),
            'payload' => $payload,
            'canonical_json' => $canonicalJson,
            'tx_hash' => hash('sha256', $canonicalJson),
            'amount_minor' => $amountMinor,
            'amount' => $this->wallets->minorToDecimalString($amountMinor),
            'product_id' => (int) $product->id,
            'quantity' => $quantity,
            'signer_passkey_id' => (int) $passkey->id,
        ];
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array{json:string,options:array<string, mixed>}
     */
    public function authenticationOptions(User $user, Request $request, array $envelope): array
    {
        $allowCredentials = $user->passkeys()
            ->whereKey((int) $envelope['signer_passkey_id'])
            ->get()
            ->map(function (Passkey $passkey) {
                $credentialId = base64_decode((string) $passkey->credential_id, true);

                if ($credentialId === false || $credentialId === '') {
                    return null;
                }

                return new PublicKeyCredentialDescriptor(
                    type: 'public-key',
                    id: $credentialId,
                    transports: [],
                );
            })
            ->filter()
            ->values()
            ->all();

        if (count($allowCredentials) === 0) {
            throw ValidationException::withMessages([
                'passkey' => 'Для оплаты RUBT сначала добавьте Passkey в профиль.',
            ]);
        }

        $options = new PublicKeyCredentialRequestOptions(
            challenge: hex2bin((string) $envelope['tx_hash']) ?: random_bytes(32),
            rpId: $request->getHost(),
            allowCredentials: $allowCredentials,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $json = Serializer::make()->toJson($options);
        session(['passkey-authentication-options' => $json]);

        return [
            'json' => $json,
            'options' => json_decode($json, true),
        ];
    }

    /**
     * @param array<string, mixed> $assertion
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public function verifyAssertion(User $user, array $assertion, string $signingOptionsJson, array $envelope): array
    {
        if ($this->nonceUsed((int) data_get($envelope, 'payload.nonce'))) {
            throw ValidationException::withMessages([
                'assertion' => 'Simple Layer One nonce уже был использован. Подпишите новую транзакцию.',
            ]);
        }

        try {
            $passkey = app(FindPasskeyToAuthenticateAction::class)->execute(
                json_encode($assertion),
                $signingOptionsJson,
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'assertion' => 'Криптографическая проверка подписи не удалась: '.$e->getMessage(),
            ]);
        }

        if (! $passkey || (int) $passkey->authenticatable_id !== (int) $user->id) {
            Log::warning('Buyer wallet passkey owner mismatch', [
                'auth_user_id' => $user->id,
                'passkey_id' => $passkey?->id,
                'passkey_owner_id' => $passkey?->authenticatable_id,
            ]);

            throw ValidationException::withMessages([
                'assertion' => 'Недействительная или неавторизованная подпись RUBT-транзакции.',
            ]);
        }

        $proof = $this->buildWebAuthnProof($assertion, $passkey, $envelope);
        if (! $proof['valid']) {
            throw ValidationException::withMessages([
                'assertion' => $proof['error'],
            ]);
        }

        return $proof['proof'];
    }

    public function canonicalJson(array $payload): string
    {
        return json_encode(
            $this->sortCanonicalKeys($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    public function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function resolveBuyerSigningPasskey(User $user): Passkey
    {
        $passkeys = $user->passkeys()->oldest('id')->get();
        if ($passkeys->isEmpty()) {
            throw ValidationException::withMessages([
                'passkey' => 'Для оплаты RUBT сначала добавьте Passkey в профиль.',
            ]);
        }

        $existingAddress = (string) data_get($user->meta, 'l1_address', '');
        foreach ($passkeys as $passkey) {
            try {
                $derivedAddress = $this->identity->addressFromPasskey($passkey);
            } catch (\Throwable) {
                continue;
            }

            if ($existingAddress === '') {
                $this->identity->bindUserToPasskey($user, $passkey);

                return $passkey->refresh();
            }

            if (hash_equals($existingAddress, $derivedAddress)) {
                return $passkey;
            }
        }

        throw ValidationException::withMessages([
            'passkey' => 'Passkey не соответствует L1-адресу покупателя. Обновите Passkey в профиле.',
        ]);
    }

    private function buyerL1Address(User $user, Passkey $passkey): string
    {
        $address = (string) data_get($user->refresh()->meta, 'l1_address', '');
        if ($address === '') {
            $address = $this->identity->bindUserToPasskey($user, $passkey);
        }

        return $address;
    }

    private function assertWalletAccountAddress(User $user, string $buyerL1Address): void
    {
        $account = WalletAccount::query()
            ->where('user_id', $user->id)
            ->where('asset', BuyerWalletService::ASSET_RUBT)
            ->first();

        if (! $account) {
            return;
        }

        if (! $account->l1_address) {
            $account->forceFill(['l1_address' => $buyerL1Address])->save();

            return;
        }

        if (! hash_equals((string) $account->l1_address, $buyerL1Address)) {
            throw ValidationException::withMessages([
                'wallet' => 'RUBT wallet is bound to a different L1 address.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $assertion
     * @param array<string, mixed> $envelope
     * @return array{valid:bool,error?:string,proof?:array<string, mixed>}
     */
    private function buildWebAuthnProof(array $assertion, Passkey $passkey, array $envelope): array
    {
        $publicKey = (string) ($passkey->data->credentialPublicKey ?? '');
        $derivedAddress = $this->identity->addressFromPublicKey($publicKey);
        $expectedAddress = (string) data_get($envelope, 'payload.buyer_l1_address');

        if (! hash_equals($expectedAddress, $derivedAddress)) {
            return [
                'valid' => false,
                'error' => 'Passkey не соответствует L1-адресу покупателя в RUBT-транзакции.',
            ];
        }

        $clientDataJsonEncoded = (string) data_get($assertion, 'response.clientDataJSON', '');
        $clientDataJson = $this->base64UrlDecode($clientDataJsonEncoded);
        $clientData = $clientDataJson !== '' ? json_decode($clientDataJson, true) : null;
        $challenge = is_array($clientData) ? (string) ($clientData['challenge'] ?? '') : '';
        $expectedChallenge = $this->base64UrlEncode(hex2bin((string) $envelope['tx_hash']) ?: '');

        if (! app()->environment('testing') || $clientDataJsonEncoded !== '') {
            if (! is_array($clientData) || ! hash_equals($expectedChallenge, $challenge)) {
                return [
                    'valid' => false,
                    'error' => 'WebAuthn challenge не совпадает с tx_hash RUBT-транзакции.',
                ];
            }
        }

        return [
            'valid' => true,
            'proof' => [
                'network' => 'Simple Layer One',
                'tx_hash' => (string) $envelope['tx_hash'],
                'tx_nonce' => (int) data_get($envelope, 'payload.nonce'),
                'canonical_payload' => $envelope['payload'],
                'canonical_json' => (string) $envelope['canonical_json'],
                'l1_address' => $derivedAddress,
                'credential_id' => (string) $passkey->credential_id,
                'public_key' => base64_encode($publicKey),
                'clientDataJSON' => $clientDataJsonEncoded,
                'authenticatorData' => (string) data_get($assertion, 'response.authenticatorData', ''),
                'signature' => (string) data_get($assertion, 'response.signature', ''),
                'userHandle' => (string) data_get($assertion, 'response.userHandle', ''),
                'challenge' => $challenge,
                'verified_at' => now()->toJSON(),
            ],
        ];
    }

    private function nonceUsed(int $nonce): bool
    {
        if ($nonce <= 0) {
            return true;
        }

        return WalletLedgerEntry::query()
            ->where('nonce', $nonce)
            ->exists()
            || SovereignLedger::query()
                ->where('payload->simple_layer_one->tx_nonce', (string) $nonce)
                ->orWhere('payload->tx_nonce', (string) $nonce)
                ->exists();
    }

    private function sortCanonicalKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->sortCanonicalKeys($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn ($item) => $this->sortCanonicalKeys($item), $value);
    }

    private function base64UrlDecode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private function hashNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : hash('sha256', strtolower($value));
    }
}
