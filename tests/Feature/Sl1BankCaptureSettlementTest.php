<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\BuyerWalletService;
use App\Services\Sl1BankCaptureSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Sl1BankCaptureSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_capture_mints_sl1_reward_once_and_marks_order_captured(): void
    {
        $user = User::factory()->create([
            'meta' => [
                'l1_address' => 'sl1e_'.str_repeat('a', 39),
            ],
        ]);
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => 'MS-SBP-001',
            'status' => 'NEW',
            'sub_status' => 'DIRECT_STOREFRONT',
            'progress_id' => 1,
            'user_id' => $user->id,
            'total_amount' => '150.00',
            'currency' => 'RUB',
            'info' => [
                'payment_status' => 'pending',
                'payment_method' => 'sbp_pending',
            ],
            'client_info' => [],
        ]);

        $capture = [
            'status' => 'captured',
            'method' => 'sbp_oney_capture',
            'provider' => 'oney',
            'reference' => 'sbp-capture-001',
            'amount_minor' => 15000,
            'currency' => 'RUB',
            'captured_at' => '2026-05-28T01:14:00Z',
            'signed_by' => 'bank',
        ];

        $settlement = app(Sl1BankCaptureSettlementService::class);
        $first = $settlement->captureWithReward($order, $capture, $user);
        $second = $settlement->captureWithReward($order->refresh(), $capture, $user);

        $this->assertSame($first['reward']->id, $second['reward']->id);
        $this->assertSame(15000, $first['reward_minor']);
        $this->assertSame('captured', data_get($first['order']->info, 'payment_status'));
        $this->assertSame('bank', data_get($first['order']->info, 'bank_capture.signed_by'));
        $this->assertSame($first['reward']->id, data_get($first['order']->info, 'simple_l1.reward.wallet_ledger_entry_id'));

        $balance = app(BuyerWalletService::class)->balance($user, BuyerWalletService::ASSET_SL1);
        $this->assertSame(15000, $balance['available_minor']);
        $this->assertSame(0, $balance['reserved_minor']);
        $this->assertSame(1, WalletLedgerEntry::where('entry_type', 'CASHBACK')->count());
        $this->assertSame(1, SovereignLedger::where('event_type', 'BANK_CAPTURE_SL1_REWARD_MINT')->count());
    }

    public function test_sl1_multipay_reserve_commit_and_release_moves_reserved_balance(): void
    {
        $user = User::factory()->create([
            'meta' => [
                'l1_address' => 'sl1e_'.str_repeat('b', 39),
            ],
        ]);
        $wallets = app(BuyerWalletService::class);
        $settlement = app(Sl1BankCaptureSettlementService::class);

        $wallets->mintSL1(
            user: $user,
            amountMinor: 20000,
            reason: 'Test bonus funding',
            idempotencyKey: 'test-sl1-funding:'.$user->id,
        );

        $settlement->reserveSl1ForMultiPay($user, 7000, 'MS-MULTIPAY-001');
        $balance = $wallets->balance($user, BuyerWalletService::ASSET_SL1);
        $this->assertSame(13000, $balance['available_minor']);
        $this->assertSame(7000, $balance['reserved_minor']);

        $settlement->commitSl1MultiPay($user, 7000, 'MS-MULTIPAY-001');
        $balance = $wallets->balance($user, BuyerWalletService::ASSET_SL1);
        $this->assertSame(13000, $balance['available_minor']);
        $this->assertSame(0, $balance['reserved_minor']);

        $settlement->reserveSl1ForMultiPay($user, 3000, 'MS-MULTIPAY-002');
        $settlement->releaseSl1MultiPay($user, 3000, 'MS-MULTIPAY-002');
        $balance = $wallets->balance($user, BuyerWalletService::ASSET_SL1);
        $this->assertSame(13000, $balance['available_minor']);
        $this->assertSame(0, $balance['reserved_minor']);
    }
}
