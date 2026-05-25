<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BuyerWalletService
{
    public const ASSET_RUBT = 'RUBT';
    public const ASSET_SL1 = 'SL1';

    /**
     * @return array{available_minor:int,reserved_minor:int,total_minor:int}
     */
    public function balance(User $user, string $asset = self::ASSET_RUBT): array
    {
        $asset = $this->normalizeAsset($asset);
        $account = WalletAccount::query()
            ->where('user_id', $user->id)
            ->where('asset', $asset)
            ->first();

        $available = (int) ($account?->available_minor ?? 0);
        $reserved = (int) ($account?->reserved_minor ?? 0);

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
                'token_currency' => self::ASSET_RUBT,
                'backing_currency' => 'RUB',
                'backing_ratio' => 1,
            ],
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
                    'wallet' => 'Недостаточно RUBT на балансе для оплаты заказа.',
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

    public function rubToMinor(string $amount): int
    {
        $amount = str_replace(',', '.', trim($amount));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('RUBT amount must have at most 2 decimal places.');
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

        if (! in_array($asset, [self::ASSET_RUBT, self::ASSET_SL1], true)) {
            throw new InvalidArgumentException('Unsupported wallet asset: '.$asset);
        }

        return $asset;
    }
}
