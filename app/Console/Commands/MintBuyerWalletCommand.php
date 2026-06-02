<?php

namespace App\Console\Commands;

use App\Exceptions\DuplicateMutationException;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\BuyerWalletService;
use App\Services\Mutation\MutationContext;
use App\Services\Mutation\MutationDedupGuard;
use App\Services\Mutation\MutationIdentityResolver;
use App\Services\VaultTransitService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MintBuyerWalletCommand extends Command
{
    protected $signature = 'wallet:mint
                            {user : User id, email, handle, or @handle}
                            {amount : RUBT amount in RUB units, e.g. 10000}
                            {--asset=RUBT : Wallet asset to mint}
                            {--reason=Dev buyer wallet mint : Audit reason}
                            {--idempotency-key= : Unique key that prevents duplicate minting}
                            {--operator= : Operator identity for audited wallet mutation}
                            {--mutation-id= : Precomputed mutation id for replay-safe execution}
                            {--confirm-production : Explicitly allow production wallet mint}';

    protected $description = 'Mint test funds into a buyer wallet with an audited idempotent ledger entry.';

    public function handle(BuyerWalletService $wallets): int
    {
        $asset = strtoupper((string) $this->option('asset'));
        if ($asset !== BuyerWalletService::ASSET_RUBT) {
            $this->error('Only RUBT minting is supported by this command.');

            return self::FAILURE;
        }

        $matches = $this->resolveUser((string) $this->argument('user'));
        if ($matches->count() === 0) {
            $this->error('No user matched the provided identifier. Nothing minted.');

            return self::FAILURE;
        }

        if ($matches->count() > 1) {
            $this->error('Multiple users matched the provided identifier. Nothing minted.');
            $matches->each(fn (User $user) => $this->line($this->formatUser($user)));

            return self::FAILURE;
        }

        /** @var User $user */
        $user = $matches->first();
        $amountMinor = $wallets->rubToMinor((string) $this->argument('amount'));
        $idempotencyKey = (string) ($this->option('idempotency-key') ?: sprintf(
            'dev-mint:user-%d:rubt:%d:%s',
            $user->id,
            $amountMinor,
            now()->toDateString(),
        ));
        $reason = (string) $this->option('reason');
        $alreadyExisted = WalletLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->exists();

        $mode = (string) config('mutation.cli_guard_mode', 'shadow');
        if (app()->environment('production') && config('mutation.production_cli_requires_confirmation', true) && ! $this->option('confirm-production')) {
            $this->error('Production wallet mint requires --confirm-production.');

            return self::FAILURE;
        }

        if (in_array(strtolower($mode), ['enforce', 'hard', 'hard_enforce'], true) && blank($this->option('operator'))) {
            $this->error('Wallet mint requires --operator when mutation CLI guard is enforced.');

            return self::FAILURE;
        }

        $identity = app(MutationIdentityResolver::class)->fromCli(
            command: $this,
            action: 'wallet.mint',
            entityType: 'wallet',
            entityId: $user->id,
            idempotencyKey: $idempotencyKey,
            context: [
                'asset' => $asset,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
                'environment' => app()->environment(),
            ],
            mutationPath: 'wallet.mint.cli',
        );

        try {
            $decision = app(MutationDedupGuard::class)->check(
                identity: $identity,
                mutationPath: 'wallet.mint.cli',
                mode: $mode,
                guardKey: 'cli:wallet.mint:'.$identity['mutation_id'],
                metadata: ['command' => $this->getName()],
            );

            $entry = MutationContext::bind($identity, fn () => $wallets->mintRUBT(
                user: $user,
                amountMinor: $amountMinor,
                reason: $reason,
                idempotencyKey: $idempotencyKey,
            ));

            app(MutationDedupGuard::class)->complete($decision['guard_key']);
        } catch (DuplicateMutationException $e) {
            $this->error('Duplicate wallet mint rejected: '.$e->mutationId);

            return self::FAILURE;
        }

        $balance = $wallets->balance($user, $asset);

        if ($alreadyExisted) {
            $this->warn('Existing idempotency key found; no duplicate mint applied.');
        } else {
            $this->info('Wallet mint applied.');
        }

        $this->line('User: '.$this->formatUser($user));
        $this->line('Asset: '.$asset);
        $this->line('Amount minor: '.$amountMinor);
        $this->line('Amount RUBT: '.$wallets->minorToDecimalString($amountMinor));
        $this->line('Available balance minor: '.$balance['available_minor']);
        $this->line('Available balance RUBT: '.$wallets->minorToDecimalString($balance['available_minor']));
        $this->line('Wallet ledger entry id: '.$entry->id);
        $this->line('Entry type: '.$entry->entry_type);
        $this->line('Idempotency key: '.$entry->idempotency_key);
        $this->line('Sovereign ledger id: '.($entry->payload['sovereign_ledger_id'] ?? 'n/a'));
        $this->line('Tx hash: '.($entry->tx_hash ?? 'n/a'));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveUser(string $identifier): Collection
    {
        $identifier = trim($identifier);
        $handle = ltrim($identifier, '@');
        $needles = collect([$identifier, $handle])
            ->filter()
            ->map(fn (string $value) => strtolower(trim($value)))
            ->unique()
            ->values();

        $columns = Schema::getColumnListing('users');
        $bidxValues = $needles
            ->map(fn (string $value) => app(VaultTransitService::class)->computeBlindIndex($value))
            ->filter()
            ->values()
            ->all();

        $direct = User::query()
            ->where(function ($query) use ($identifier, $columns, $needles, $bidxValues) {
                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }

                foreach (['first_name', 'last_name', 'middle_name', 'phone', 'entity_l1_address'] as $column) {
                    if (in_array($column, $columns, true)) {
                        $needles->each(fn (string $needle) => $query->orWhere($column, $needle));
                    }

                    $bidxColumn = $column.'_bidx';
                    if (in_array($bidxColumn, $columns, true)) {
                        $query->orWhereIn($bidxColumn, $bidxValues);
                    }
                }
            })
            ->get();

        $scan = User::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user) => $this->userMatchesNeedles($user, $needles));

        return $direct
            ->merge($scan)
            ->unique('id')
            ->values();
    }

    /**
     * @param Collection<int, string> $needles
     */
    private function userMatchesNeedles(User $user, Collection $needles): bool
    {
        $values = [
            $user->first_name,
            $user->last_name,
            $user->middle_name,
            $user->sovereignIdentityAddress(),
            $user->phone,
            $user->meta['nickname'] ?? null,
            $user->meta['handle'] ?? null,
            $user->meta['username'] ?? null,
        ];

        foreach ($values as $value) {
            $value = strtolower(trim((string) $value));
            if ($value !== '' && $needles->contains($value)) {
                return true;
            }
        }

        return false;
    }

    private function formatUser(User $user): string
    {
        return sprintf(
            '#%d <%s> %s %s',
            $user->id,
            $user->sovereignIdentityAddress(),
            $user->first_name,
            $user->last_name,
        );
    }
}
