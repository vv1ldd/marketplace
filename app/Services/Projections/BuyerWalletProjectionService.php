<?php

namespace App\Services\Projections;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;

class BuyerWalletProjectionService
{
    /**
     * @return array{accounts: array<int, array<string, mixed>>, anomalies: array<int, array<string, mixed>>, source_revision: string}
     */
    public function expectedAccounts(?int $userId = null): array
    {
        $accounts = [];
        $anomalies = [];

        WalletAccount::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->each(function (WalletAccount $account) use (&$accounts): void {
                $key = $this->key((int) $account->user_id, (string) $account->asset);
                $accounts[$key] ??= $this->emptyAccount((int) $account->user_id, (string) $account->asset, $account->l1_address);
            });

        WalletLedgerEntry::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->each(function (WalletLedgerEntry $entry) use (&$accounts, &$anomalies): void {
                $userId = (int) $entry->user_id;
                $asset = (string) $entry->asset;
                $key = $this->key($userId, $asset);
                $accounts[$key] ??= $this->emptyAccount($userId, $asset);

                $amount = (int) $entry->amount_minor;
                match ((string) $entry->direction) {
                    'credit' => $accounts[$key]['available_minor'] += $amount,
                    'reserve' => $this->applyReserve($accounts[$key], $amount),
                    'release' => $this->applyRelease($accounts[$key], $amount),
                    'debit' => $this->applyDebit($accounts[$key], $entry, $amount),
                    default => $anomalies[] = [
                        'wallet_ledger_entry_id' => $entry->id,
                        'reason' => 'unknown_direction',
                        'direction' => $entry->direction,
                    ],
                };

                if ($accounts[$key]['available_minor'] < 0 || $accounts[$key]['reserved_minor'] < 0) {
                    $anomalies[] = [
                        'wallet_ledger_entry_id' => $entry->id,
                        'user_id' => $userId,
                        'asset' => $asset,
                        'reason' => 'negative_replayed_balance',
                        'available_minor' => $accounts[$key]['available_minor'],
                        'reserved_minor' => $accounts[$key]['reserved_minor'],
                    ];
                }
            });

        ksort($accounts);

        return [
            'accounts' => array_values($accounts),
            'anomalies' => $anomalies,
            'source_revision' => $this->sourceRevision($userId),
        ];
    }

    /**
     * @return array{status: string, accounts_processed: int, accounts_updated: int, anomalies: array<int, array<string, mixed>>, source_revision: string}
     */
    public function rebuild(?int $userId = null, bool $dryRun = false): array
    {
        $projection = $this->expectedAccounts($userId);

        if ($projection['anomalies'] !== []) {
            return [
                'status' => 'SOURCE_GAP',
                'accounts_processed' => count($projection['accounts']),
                'accounts_updated' => 0,
                'anomalies' => $projection['anomalies'],
                'source_revision' => $projection['source_revision'],
            ];
        }

        $updated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($projection, &$updated): void {
                foreach ($projection['accounts'] as $expected) {
                    WalletAccount::withoutEvents(function () use ($expected, &$updated): void {
                        $account = WalletAccount::query()->firstOrNew([
                            'user_id' => $expected['user_id'],
                            'asset' => $expected['asset'],
                        ]);

                        $account->l1_address = $account->l1_address
                            ?: $expected['l1_address']
                            ?: User::query()->find($expected['user_id'])?->sovereignIdentityAddress();
                        $account->available_minor = $expected['available_minor'];
                        $account->reserved_minor = $expected['reserved_minor'];
                        $account->save();
                    });

                    $updated++;
                }
            });
        }

        return [
            'status' => 'OK',
            'accounts_processed' => count($projection['accounts']),
            'accounts_updated' => $updated,
            'anomalies' => [],
            'source_revision' => $projection['source_revision'],
        ];
    }

    /**
     * @return array{status: string, accounts_checked: int, mismatches: int, anomalies: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>, source_revision: string}
     */
    public function verify(?int $userId = null): array
    {
        $projection = $this->expectedAccounts($userId);
        $expectedByKey = collect($projection['accounts'])->keyBy(fn (array $row): string => $this->key($row['user_id'], $row['asset']));
        $actualByKey = WalletAccount::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (WalletAccount $account): array => [
                $this->key((int) $account->user_id, (string) $account->asset) => [
                    'user_id' => (int) $account->user_id,
                    'asset' => (string) $account->asset,
                    'available_minor' => (int) $account->available_minor,
                    'reserved_minor' => (int) $account->reserved_minor,
                ],
            ]);

        $keys = $expectedByKey->keys()->merge($actualByKey->keys())->unique()->sort()->values();
        $rows = [];
        $mismatches = 0;

        foreach ($keys as $key) {
            $expected = $expectedByKey->get($key);
            $actual = $actualByKey->get($key);
            $expectedComparable = $expected ? $this->comparable($expected) : null;
            $actualComparable = $actual ? $this->comparable($actual) : null;
            $matches = $expectedComparable === $actualComparable;

            if (! $matches) {
                $mismatches++;
            }

            $rows[] = [
                'key' => $key,
                'matches' => $matches,
                'expected' => $expectedComparable,
                'actual' => $actualComparable,
            ];
        }

        return [
            'status' => $projection['anomalies'] !== [] ? 'SOURCE_GAP' : ($mismatches === 0 ? 'OK' : 'FAILED'),
            'accounts_checked' => count($rows),
            'mismatches' => $mismatches,
            'anomalies' => $projection['anomalies'],
            'rows' => $rows,
            'source_revision' => $projection['source_revision'],
        ];
    }

    private function applyReserve(array &$account, int $amount): void
    {
        $account['available_minor'] -= $amount;
        $account['reserved_minor'] += $amount;
    }

    private function applyRelease(array &$account, int $amount): void
    {
        $account['available_minor'] += $amount;
        $account['reserved_minor'] -= $amount;
    }

    private function applyDebit(array &$account, WalletLedgerEntry $entry, int $amount): void
    {
        if (str_contains(strtoupper((string) $entry->entry_type), 'COMMIT')) {
            $account['reserved_minor'] -= $amount;

            return;
        }

        $account['available_minor'] -= $amount;
    }

    private function emptyAccount(int $userId, string $asset, ?string $l1Address = null): array
    {
        return [
            'user_id' => $userId,
            'asset' => $asset,
            'l1_address' => $l1Address,
            'available_minor' => 0,
            'reserved_minor' => 0,
        ];
    }

    private function comparable(array $row): array
    {
        return [
            'user_id' => (int) $row['user_id'],
            'asset' => (string) $row['asset'],
            'available_minor' => (int) $row['available_minor'],
            'reserved_minor' => (int) $row['reserved_minor'],
        ];
    }

    private function key(int $userId, string $asset): string
    {
        return $userId.'|'.$asset;
    }

    private function sourceRevision(?int $userId): string
    {
        $query = WalletLedgerEntry::query()->when($userId, fn ($query) => $query->where('user_id', $userId));

        return sprintf(
            'wallet_ledger_entries:%d:%s',
            (clone $query)->count(),
            (clone $query)->max('id') ?? 'none',
        );
    }
}
