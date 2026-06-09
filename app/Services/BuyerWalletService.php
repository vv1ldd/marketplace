<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\Mutation\MutationContext;
use App\Services\Mutation\MutationIdentityResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BuyerWalletService
{
    public const ASSET_RUB = 'RUB';
    public const ASSET_RUBT = self::ASSET_RUB;
    private const LEGACY_ASSET_RUBT = 'RUBT';
    public const ASSET_SL1 = 'SL';

    /**
     * @return array{available_minor:int,reserved_minor:int,total_minor:int}
     */
    public function balance(User $user, string $asset = self::ASSET_RUBT): array
    {
        $asset = $this->normalizeAsset($asset);
        $query = WalletAccount::query()
            ->where('user_id', $user->id)
            ->where('asset', $asset);

        if ($asset === self::ASSET_RUB) {
            $query->orWhere(function ($legacyQuery) use ($user): void {
                $legacyQuery->where('user_id', $user->id)
                    ->where('asset', self::LEGACY_ASSET_RUBT);
            });
        }

        $account = $query->get();

        $available = (int) $account->sum('available_minor');
        $reserved = (int) $account->sum('reserved_minor');

        return [
            'available_minor' => $available,
            'reserved_minor' => $reserved,
            'total_minor' => $available + $reserved,
        ];
    }

    public function credit(
        User $user,
        string $asset,
        int $amountMinor,
        string $entryType,
        string $idempotencyKey,
        array $payload = [],
        ?string $txHash = null,
        ?int $nonce = null,
    ): WalletLedgerEntry {
        $asset = $this->normalizeAsset($asset);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive minor units.');
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Wallet credit requires an idempotency key.');
        }

        if (! MutationContext::isActive()) {
            $identity = app(MutationIdentityResolver::class)->resolve(
                actor: 'service:wallet',
                action: 'wallet.credit',
                entityType: 'wallet',
                entityId: $user->id,
                idempotencyKey: $idempotencyKey,
                context: [
                    'asset' => $asset,
                    'amount_minor' => $amountMinor,
                    'entry_type' => $entryType,
                ],
                mutationPath: $entryType === 'MINT' ? 'wallet.mint.cli' : 'wallet.credit',
            );

            return MutationContext::bind($identity, fn (): WalletLedgerEntry => $this->credit(
                user: $user,
                asset: $asset,
                amountMinor: $amountMinor,
                entryType: $entryType,
                idempotencyKey: $idempotencyKey,
                payload: $payload,
                txHash: $txHash,
                nonce: $nonce,
            ));
        }

        $existing = WalletLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $asset, $amountMinor, $entryType, $idempotencyKey, $payload, $txHash, $nonce) {
            $existing = WalletLedgerEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $account = WalletAccount::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'asset' => $asset,
                ],
                [
                    'l1_address' => $user->meta['l1_address'] ?? null,
                    'available_minor' => 0,
                    'reserved_minor' => 0,
                ],
            );

            $account = WalletAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $account->l1_address && isset($user->meta['l1_address'])) {
                $account->l1_address = $user->meta['l1_address'];
            }

            $account->available_minor = (int) $account->available_minor + $amountMinor;
            $account->save();

            $entry = WalletLedgerEntry::create([
                'user_id' => $user->id,
                'asset' => $asset,
                'direction' => 'credit',
                'entry_type' => $entryType,
                'amount_minor' => $amountMinor,
                'balance_after_minor' => (int) $account->available_minor,
                'idempotency_key' => $idempotencyKey,
                'tx_hash' => $txHash,
                'nonce' => $nonce,
                'payload' => $payload,
            ]);

            $ledger = app(LedgerService::class)->recordGlobal(
                'BUYER_WALLET_TOPUP',
                $entry,
                [
                    'wallet_ledger_entry_id' => $entry->id,
                    'user_id' => $user->id,
                    'asset' => $asset,
                    'amount_minor' => $amountMinor,
                    'balance_after_minor' => (int) $account->available_minor,
                    'idempotency_key' => $idempotencyKey,
                    'l1_address' => $account->l1_address,
                    'entry_type' => $entryType,
                ] + $payload,
            );

            $entry->tx_hash ??= $ledger->fingerprint;
            $entry->payload = array_merge($entry->payload ?? [], [
                'sovereign_ledger_id' => $ledger->id,
                'sovereign_ledger_fingerprint' => $ledger->fingerprint,
            ]);
            $entry->save();

            return $entry->refresh();
        });
    }

    public function mintRUBT(
        User $user,
        int $amountMinor,
        string $reason,
        string $idempotencyKey,
    ): WalletLedgerEntry {
        return $this->credit(
            user: $user,
            asset: self::ASSET_RUBT,
            amountMinor: $amountMinor,
            entryType: 'MINT',
            idempotencyKey: $idempotencyKey,
            payload: [
                'reason' => $reason,
                'source' => 'wallet:mint',
                'amount_rub' => $this->minorToDecimalString($amountMinor, 2),
                'token_currency' => self::ASSET_RUB,
                'backing_currency' => 'RUB',
                'backing_ratio' => 1,
            ],
        );
    }

    public function mintSL1(
        User $user,
        int $amountMinor,
        string $reason,
        string $idempotencyKey,
        array $payload = [],
    ): WalletLedgerEntry {
        return $this->credit(
            user: $user,
            asset: self::ASSET_SL1,
            amountMinor: $amountMinor,
            entryType: 'CASHBACK',
            idempotencyKey: $idempotencyKey,
            payload: [
                'reason' => $reason,
                'source' => 'sl1:bonus_mint',
                'amount_sl' => $this->minorToDecimalString($amountMinor, 4),
                'token_currency' => self::ASSET_SL1,
            ] + $payload,
        );
    }

    public function debitRUBT(
        User $user,
        int $amountMinor,
        string $entryType,
        string $idempotencyKey,
        array $payload = [],
        ?string $txHash = null,
        ?int $nonce = null,
    ): WalletLedgerEntry {
        return $this->debit(
            user: $user,
            asset: self::ASSET_RUBT,
            amountMinor: $amountMinor,
            entryType: $entryType,
            idempotencyKey: $idempotencyKey,
            payload: $payload,
            txHash: $txHash,
            nonce: $nonce,
        );
    }

    public function debit(
        User $user,
        string $asset,
        int $amountMinor,
        string $entryType,
        string $idempotencyKey,
        array $payload = [],
        ?string $txHash = null,
        ?int $nonce = null,
    ): WalletLedgerEntry {
        $asset = $this->normalizeAsset($asset);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive minor units.');
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Wallet debit requires an idempotency key.');
        }

        if (! MutationContext::isActive()) {
            $identity = app(MutationIdentityResolver::class)->resolve(
                actor: 'service:wallet',
                action: 'wallet.debit',
                entityType: 'wallet',
                entityId: $user->id,
                idempotencyKey: $idempotencyKey,
                context: [
                    'asset' => $asset,
                    'amount_minor' => $amountMinor,
                    'entry_type' => $entryType,
                ],
                mutationPath: 'wallet.debit',
            );

            return MutationContext::bind($identity, fn (): WalletLedgerEntry => $this->debit(
                user: $user,
                asset: $asset,
                amountMinor: $amountMinor,
                entryType: $entryType,
                idempotencyKey: $idempotencyKey,
                payload: $payload,
                txHash: $txHash,
                nonce: $nonce,
            ));
        }

        $existing = WalletLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $asset, $amountMinor, $entryType, $idempotencyKey, $payload, $txHash, $nonce) {
            $existing = WalletLedgerEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $account = WalletAccount::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'asset' => $asset,
                ],
                [
                    'l1_address' => $user->meta['l1_address'] ?? null,
                    'available_minor' => 0,
                    'reserved_minor' => 0,
                ],
            );

            $account = WalletAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $account->l1_address && isset($user->meta['l1_address'])) {
                $account->l1_address = $user->meta['l1_address'];
            }

            if ((int) $account->available_minor < $amountMinor) {
                throw ValidationException::withMessages([
                    'wallet' => 'Недостаточно RUB на балансе для оплаты заказа.',
                ]);
            }

            $account->available_minor = (int) $account->available_minor - $amountMinor;
            $account->save();

            $entry = WalletLedgerEntry::create([
                'user_id' => $user->id,
                'asset' => $asset,
                'direction' => 'debit',
                'entry_type' => $entryType,
                'amount_minor' => $amountMinor,
                'balance_after_minor' => (int) $account->available_minor,
                'idempotency_key' => $idempotencyKey,
                'tx_hash' => $txHash,
                'nonce' => $nonce,
                'payload' => $payload,
            ]);

            $ledger = app(LedgerService::class)->recordGlobal(
                'BUYER_WALLET_DEBIT',
                $entry,
                [
                    'wallet_ledger_entry_id' => $entry->id,
                    'user_id' => $user->id,
                    'asset' => $asset,
                    'amount_minor' => $amountMinor,
                    'balance_after_minor' => (int) $account->available_minor,
                    'idempotency_key' => $idempotencyKey,
                    'l1_address' => $account->l1_address,
                    'entry_type' => $entryType,
                    'tx_hash' => $txHash,
                    'nonce' => $nonce,
                ] + $payload,
            );

            $entry->tx_hash ??= $ledger->fingerprint;
            $entry->payload = array_merge($entry->payload ?? [], [
                'sovereign_ledger_id' => $ledger->id,
                'sovereign_ledger_fingerprint' => $ledger->fingerprint,
            ]);
            $entry->save();

            return $entry->refresh();
        });
    }

    public function reserve(
        User $user,
        string $asset,
        int $amountMinor,
        string $entryType,
        string $idempotencyKey,
        array $payload = [],
    ): WalletLedgerEntry {
        $asset = $this->normalizeAsset($asset);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Reserve amount must be positive minor units.');
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Wallet reserve requires an idempotency key.');
        }

        if (! MutationContext::isActive()) {
            $identity = app(MutationIdentityResolver::class)->resolve(
                actor: 'service:wallet',
                action: 'wallet.reserve',
                entityType: 'wallet',
                entityId: $user->id,
                idempotencyKey: $idempotencyKey,
                context: [
                    'asset' => $asset,
                    'amount_minor' => $amountMinor,
                    'entry_type' => $entryType,
                ],
                mutationPath: 'wallet.debit',
            );

            return MutationContext::bind($identity, fn (): WalletLedgerEntry => $this->reserve(
                user: $user,
                asset: $asset,
                amountMinor: $amountMinor,
                entryType: $entryType,
                idempotencyKey: $idempotencyKey,
                payload: $payload,
            ));
        }

        $existing = WalletLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $asset, $amountMinor, $entryType, $idempotencyKey, $payload) {
            $existing = WalletLedgerEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $account = $this->lockedAccount($user, $asset);

            if ((int) $account->available_minor < $amountMinor) {
                throw ValidationException::withMessages([
                    'wallet' => 'Недостаточно средств для резервирования.',
                ]);
            }

            $account->available_minor = (int) $account->available_minor - $amountMinor;
            $account->reserved_minor = (int) $account->reserved_minor + $amountMinor;
            $account->save();

            $entry = WalletLedgerEntry::create([
                'user_id' => $user->id,
                'asset' => $asset,
                'direction' => 'reserve',
                'entry_type' => $entryType,
                'amount_minor' => $amountMinor,
                'balance_after_minor' => (int) $account->available_minor,
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
            ]);

            $ledger = app(LedgerService::class)->recordGlobal(
                'BUYER_WALLET_RESERVE',
                $entry,
                [
                    'wallet_ledger_entry_id' => $entry->id,
                    'user_id' => $user->id,
                    'asset' => $asset,
                    'amount_minor' => $amountMinor,
                    'available_after_minor' => (int) $account->available_minor,
                    'reserved_after_minor' => (int) $account->reserved_minor,
                    'idempotency_key' => $idempotencyKey,
                    'l1_address' => $account->l1_address,
                    'entry_type' => $entryType,
                ] + $payload,
            );

            $entry->tx_hash ??= $ledger->fingerprint;
            $entry->payload = array_merge($entry->payload ?? [], [
                'sovereign_ledger_id' => $ledger->id,
                'sovereign_ledger_fingerprint' => $ledger->fingerprint,
            ]);
            $entry->save();

            return $entry->refresh();
        });
    }

    public function commitReserved(
        User $user,
        string $asset,
        int $amountMinor,
        string $entryType,
        string $idempotencyKey,
        array $payload = [],
    ): WalletLedgerEntry {
        return $this->settleReserved(
            user: $user,
            asset: $asset,
            amountMinor: $amountMinor,
            entryType: $entryType,
            idempotencyKey: $idempotencyKey,
            direction: 'debit',
            ledgerEventType: 'BUYER_WALLET_RESERVED_DEBIT',
            payload: $payload,
        );
    }

    public function releaseReserved(
        User $user,
        string $asset,
        int $amountMinor,
        string $entryType,
        string $idempotencyKey,
        array $payload = [],
    ): WalletLedgerEntry {
        return $this->settleReserved(
            user: $user,
            asset: $asset,
            amountMinor: $amountMinor,
            entryType: $entryType,
            idempotencyKey: $idempotencyKey,
            direction: 'release',
            ledgerEventType: 'BUYER_WALLET_RESERVATION_RELEASE',
            payload: $payload,
        );
    }

    public function rubToMinor(string $amount): int
    {
        $amount = str_replace(',', '.', trim($amount));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('RUB amount must have at most 2 decimal places.');
        }

        [$rubles, $kopecks] = array_pad(explode('.', $amount, 2), 2, '0');
        $kopecks = str_pad($kopecks, 2, '0');

        return ((int) $rubles * 100) + (int) $kopecks;
    }

    public function minorToDecimalString(int $amountMinor, int $scale = 2): string
    {
        $factor = 10 ** $scale;
        $whole = intdiv($amountMinor, $factor);
        $fraction = $amountMinor % $factor;

        return $whole.'.'.str_pad((string) $fraction, $scale, '0', STR_PAD_LEFT);
    }

    private function normalizeAsset(string $asset): string
    {
        $asset = strtoupper(trim($asset));
        $asset = $asset === 'SL1' ? self::ASSET_SL1 : $asset;
        $asset = $asset === self::LEGACY_ASSET_RUBT ? self::ASSET_RUB : $asset;

        if (! in_array($asset, [self::ASSET_RUB, self::ASSET_SL1], true)) {
            throw new InvalidArgumentException('Unsupported wallet asset: '.$asset);
        }

        return $asset;
    }

    private function lockedAccount(User $user, string $asset): WalletAccount
    {
        if ($asset === self::ASSET_RUB) {
            $account = WalletAccount::query()
                ->where('user_id', $user->id)
                ->whereIn('asset', [self::ASSET_RUB, self::LEGACY_ASSET_RUBT])
                ->orderByRaw('asset = ? desc', [self::ASSET_RUB])
                ->first();

            if ($account && $account->asset === self::LEGACY_ASSET_RUBT) {
                $rubAccountExists = WalletAccount::query()
                    ->where('user_id', $user->id)
                    ->where('asset', self::ASSET_RUB)
                    ->exists();

                if (! $rubAccountExists) {
                    $account->forceFill(['asset' => self::ASSET_RUB])->save();
                }
            }
        } else {
            $account = null;
        }

        $account ??= WalletAccount::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'asset' => $asset,
            ],
            [
                'l1_address' => $user->meta['l1_address'] ?? null,
                'available_minor' => 0,
                'reserved_minor' => 0,
            ],
        );

        $account = WalletAccount::query()
            ->whereKey($account->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $account->l1_address && isset($user->meta['l1_address'])) {
            $account->l1_address = $user->meta['l1_address'];
            $account->save();
        }

        return $account;
    }

    private function settleReserved(
        User $user,
        string $asset,
        int $amountMinor,
        string $entryType,
        string $idempotencyKey,
        string $direction,
        string $ledgerEventType,
        array $payload = [],
    ): WalletLedgerEntry {
        $asset = $this->normalizeAsset($asset);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Reserved settlement amount must be positive minor units.');
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Reserved settlement requires an idempotency key.');
        }

        $existing = WalletLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $asset, $amountMinor, $entryType, $idempotencyKey, $direction, $ledgerEventType, $payload) {
            $existing = WalletLedgerEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $account = $this->lockedAccount($user, $asset);

            if ((int) $account->reserved_minor < $amountMinor) {
                throw ValidationException::withMessages([
                    'wallet' => 'Недостаточно зарезервированных средств.',
                ]);
            }

            $account->reserved_minor = (int) $account->reserved_minor - $amountMinor;
            if ($direction === 'release') {
                $account->available_minor = (int) $account->available_minor + $amountMinor;
            }
            $account->save();

            $entry = WalletLedgerEntry::create([
                'user_id' => $user->id,
                'asset' => $asset,
                'direction' => $direction,
                'entry_type' => $entryType,
                'amount_minor' => $amountMinor,
                'balance_after_minor' => (int) $account->available_minor,
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
            ]);

            $ledger = app(LedgerService::class)->recordGlobal(
                $ledgerEventType,
                $entry,
                [
                    'wallet_ledger_entry_id' => $entry->id,
                    'user_id' => $user->id,
                    'asset' => $asset,
                    'amount_minor' => $amountMinor,
                    'available_after_minor' => (int) $account->available_minor,
                    'reserved_after_minor' => (int) $account->reserved_minor,
                    'idempotency_key' => $idempotencyKey,
                    'l1_address' => $account->l1_address,
                    'entry_type' => $entryType,
                    'direction' => $direction,
                ] + $payload,
            );

            $entry->tx_hash ??= $ledger->fingerprint;
            $entry->payload = array_merge($entry->payload ?? [], [
                'sovereign_ledger_id' => $ledger->id,
                'sovereign_ledger_fingerprint' => $ledger->fingerprint,
            ]);
            $entry->save();

            return $entry->refresh();
        });
    }
}
