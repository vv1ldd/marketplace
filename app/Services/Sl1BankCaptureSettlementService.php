<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Sl1BankCaptureSettlementService
{
    public const DEFAULT_REWARD_BPS = 100;

    public function __construct(
        private readonly BuyerWalletService $wallets,
    ) {}

    /**
     * @param array<string, mixed> $capture
     * @return array{order:Order,reward:WalletLedgerEntry,reward_minor:int,capture_hash:string}
     */
    public function captureWithReward(Order $order, array $capture, ?User $user = null): array
    {
        $this->assertCaptured($capture);

        $captureHash = $this->captureHash($capture);
        $captureAmountMinor = (int) $capture['amount_minor'];
        $expectedAmountMinor = $this->rubToMinor((string) $order->total_amount);

        if ($captureAmountMinor !== $expectedAmountMinor) {
            throw ValidationException::withMessages([
                'capture' => 'Bank capture amount does not match the order total.',
            ]);
        }

        $user ??= User::query()->find($order->user_id);
        if (! $user) {
            throw ValidationException::withMessages([
                'capture' => 'SL1 reward mint requires an identified buyer.',
            ]);
        }

        return DB::transaction(function () use ($order, $capture, $user, $captureHash, $captureAmountMinor) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rewardMinor = $this->quoteRewardMinor(
                capturedRubMinor: $captureAmountMinor,
                rewardBps: (int) ($capture['reward_bps'] ?? self::DEFAULT_REWARD_BPS),
            );
            $existingRewardId = data_get($lockedOrder->info, 'simple_l1.reward.wallet_ledger_entry_id');
            if (
                data_get($lockedOrder->info, 'bank_capture.capture_hash') === $captureHash
                && $existingRewardId
            ) {
                $existingReward = WalletLedgerEntry::query()->find($existingRewardId);
                if ($existingReward) {
                    return [
                        'order' => $lockedOrder->refresh(),
                        'reward' => $existingReward,
                        'reward_minor' => $rewardMinor,
                        'capture_hash' => $captureHash,
                    ];
                }
            }

            $reward = $this->wallets->mintSL1(
                user: $user,
                amountMinor: $rewardMinor,
                reason: 'Bank capture reward for Meanly order '.$lockedOrder->order_id,
                idempotencyKey: 'sl1-bank-capture-reward:'.$captureHash,
                payload: [
                    'source' => 'bank_capture_reward',
                    'order_id' => $lockedOrder->id,
                    'order_reference' => $lockedOrder->order_id,
                    'payment_method' => (string) ($capture['method'] ?? 'sbp_bank_capture'),
                    'capture_hash' => $captureHash,
                    'capture_provider' => (string) ($capture['provider'] ?? 'bank'),
                    'capture_reference' => (string) ($capture['reference'] ?? ''),
                    'captured_rub_minor' => $captureAmountMinor,
                    'reward_bps' => (int) ($capture['reward_bps'] ?? self::DEFAULT_REWARD_BPS),
                ],
            );

            $info = $lockedOrder->info ?? [];
            data_set($info, 'payment_status', 'captured');
            data_set($info, 'payment_method', (string) ($capture['method'] ?? 'sbp_bank_capture'));
            data_set($info, 'bank_capture', [
                'provider' => (string) ($capture['provider'] ?? 'bank'),
                'reference' => (string) ($capture['reference'] ?? ''),
                'amount_minor' => $captureAmountMinor,
                'currency' => (string) ($capture['currency'] ?? 'RUB'),
                'captured_at' => (string) ($capture['captured_at'] ?? now()->toJSON()),
                'capture_hash' => $captureHash,
                'signed_by' => (string) ($capture['signed_by'] ?? 'bank'),
            ]);
            data_set($info, 'simple_l1.reward', [
                'asset' => BuyerWalletService::ASSET_SL1,
                'amount_minor' => $rewardMinor,
                'amount' => $this->wallets->minorToDecimalString($rewardMinor, 4),
                'wallet_ledger_entry_id' => $reward->id,
                'capture_hash' => $captureHash,
            ]);

            $lockedOrder->forceFill([
                'status' => in_array((string) $lockedOrder->status, ['NEW', 'PENDING'], true) ? 'PROCESSING' : $lockedOrder->status,
                'progress_id' => max((int) ($lockedOrder->progress_id ?? 1), 2),
                'info' => $info,
            ])->save();

            app(LedgerService::class)->recordGlobal(
                'BANK_CAPTURE_SL1_REWARD_MINT',
                $lockedOrder,
                [
                    'order_id' => $lockedOrder->id,
                    'order_reference' => $lockedOrder->order_id,
                    'user_id' => $user->id,
                    'capture_hash' => $captureHash,
                    'payment_method' => (string) ($capture['method'] ?? 'sbp_bank_capture'),
                    'captured_rub_minor' => $captureAmountMinor,
                    'reward_asset' => BuyerWalletService::ASSET_SL1,
                    'reward_minor' => $rewardMinor,
                    'reward_ledger_entry_id' => $reward->id,
                ],
            );

            return [
                'order' => $lockedOrder->refresh(),
                'reward' => $reward->refresh(),
                'reward_minor' => $rewardMinor,
                'capture_hash' => $captureHash,
            ];
        });
    }

    public function quoteRewardMinor(int $capturedRubMinor, int $rewardBps = self::DEFAULT_REWARD_BPS): int
    {
        if ($capturedRubMinor <= 0 || $rewardBps <= 0) {
            return 0;
        }

        return intdiv($capturedRubMinor * $rewardBps, 100);
    }

    public function reserveSl1ForMultiPay(User $user, int $amountMinor, string $orderReference): WalletLedgerEntry
    {
        return $this->wallets->reserve(
            user: $user,
            asset: BuyerWalletService::ASSET_SL1,
            amountMinor: $amountMinor,
            entryType: 'MULTIPAY_SL1_RESERVE',
            idempotencyKey: 'multipay-sl1-reserve:'.$user->id.':'.$orderReference,
            payload: [
                'source' => 'multipay',
                'order_reference' => $orderReference,
            ],
        );
    }

    public function commitSl1MultiPay(User $user, int $amountMinor, string $orderReference): WalletLedgerEntry
    {
        return $this->wallets->commitReserved(
            user: $user,
            asset: BuyerWalletService::ASSET_SL1,
            amountMinor: $amountMinor,
            entryType: 'MULTIPAY_SL1_COMMIT',
            idempotencyKey: 'multipay-sl1-commit:'.$user->id.':'.$orderReference,
            payload: [
                'source' => 'multipay',
                'order_reference' => $orderReference,
            ],
        );
    }

    public function releaseSl1MultiPay(User $user, int $amountMinor, string $orderReference): WalletLedgerEntry
    {
        return $this->wallets->releaseReserved(
            user: $user,
            asset: BuyerWalletService::ASSET_SL1,
            amountMinor: $amountMinor,
            entryType: 'MULTIPAY_SL1_RELEASE',
            idempotencyKey: 'multipay-sl1-release:'.$user->id.':'.$orderReference,
            payload: [
                'source' => 'multipay',
                'order_reference' => $orderReference,
            ],
        );
    }

    /**
     * @param array<string, mixed> $capture
     */
    private function assertCaptured(array $capture): void
    {
        if (($capture['status'] ?? null) !== 'captured') {
            throw ValidationException::withMessages([
                'capture' => 'SL1 reward mint requires a captured bank payment.',
            ]);
        }

        if ((int) ($capture['amount_minor'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'capture' => 'Bank capture amount is required.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $capture
     */
    private function captureHash(array $capture): string
    {
        return hash('sha256', json_encode([
            'provider' => (string) ($capture['provider'] ?? 'bank'),
            'reference' => (string) ($capture['reference'] ?? ''),
            'amount_minor' => (int) ($capture['amount_minor'] ?? 0),
            'currency' => (string) ($capture['currency'] ?? 'RUB'),
            'captured_at' => (string) ($capture['captured_at'] ?? ''),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function rubToMinor(string $amount): int
    {
        $amount = str_replace(',', '.', trim($amount));
        [$rubles, $kopecks] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $rubles * 100) + (int) str_pad(substr($kopecks, 0, 2), 2, '0');
    }
}
