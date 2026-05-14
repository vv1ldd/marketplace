<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\SovereignLedger;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LedgerBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:backfill {--chunk=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retroactively populates the Sovereign Ledger with historical orders.';

    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledgerService)
    {
        $this->info("Starting Sovereign Ledger Backfill...");

        $chunkSize = $this->option('chunk');
        $processed = 0;
        $skipped = 0;

        // Fetch all orders that have a valid shop and a total > 0
        Order::with('shop')->whereNotNull('shop_id')->chunk($chunkSize, function ($orders) use ($ledgerService, &$processed, &$skipped) {
            foreach ($orders as $order) {
                if (!$order->shop) {
                    continue;
                }

                // Use denormalized fields — filled by Order::resolveTotalFromInfo()
                $orderTotal = (float) ($order->total_amount ?? 0);
                $orderCurrency = $order->currency ?? 'RUB';

                // Check if already in ledger
                $exists = SovereignLedger::where('entity_id', $order->id)
                    ->where('entity_type', get_class($order))
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                DB::beginTransaction();
                try {
                    // 1. ORDER_RECEIVE
                    $receiveEntry = $ledgerService->record(
                        $order->shop,
                        'ORDER_RECEIVE',
                        $order,
                        [
                            'order_id' => $order->id,
                            'total_rub' => $orderTotal,
                            'currency' => 'RUB',
                            'is_backfill' => true,
                            'original_date' => $order->created_at->toDateTimeString(),
                        ],
                        $order->created_at
                    );

                    // 2. FINANCE_CAPTURE 
                    // Assuming if total_rub > 0 it means it was a valid order we captured money for
                    if ($orderTotal > 0) {
                        $captureEntry = $ledgerService->record(
                            $order->shop,
                            'FINANCE_CAPTURE',
                            $order,
                            [
                                'amount' => $orderTotal,
                                'currency' => 'RUB',
                                'order_id' => $order->id,
                                'is_backfill' => true,
                                'original_date' => $order->updated_at->toDateTimeString(),
                            ],
                            $order->updated_at
                        );
                    }

                    DB::commit();
                    $processed++;

                    if ($processed % 100 === 0) {
                        $this->info("Processed {$processed} orders...");
                    }

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Failed to process order ID {$order->id}: " . $e->getMessage());
                }
            }
        });

        $this->info("Backfill complete! Processed: {$processed}. Skipped (already in ledger): {$skipped}.");
        return self::SUCCESS;
    }
}
