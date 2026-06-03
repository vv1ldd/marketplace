<?php

namespace App\Services\Projections;

use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;

class MarketplaceOrdersProjectionService
{
    private const MONEY_FIELDS = [
        'total_amount' => 2,
        'total_amount_base' => 2,
        'exchange_rate' => 4,
        'cost_amount' => 2,
        'cost_amount_base' => 2,
        'margin_base' => 2,
    ];

    /**
     * @return array{status: string, orders_processed: int, orders_updated: int, source_revision: string}
     */
    public function rebuild(?int $orderId = null, bool $dryRun = false): array
    {
        $processed = 0;
        $updated = 0;

        $this->query($orderId)->chunkById(100, function ($orders) use ($dryRun, &$processed, &$updated): void {
            foreach ($orders as $order) {
                $processed++;
                $expected = $this->expected($order);
                $changes = $this->changes($order, $expected);

                if ($changes === []) {
                    continue;
                }

                if (! $dryRun) {
                    Order::withoutEvents(fn () => $order->forceFill($changes)->save());
                }

                $updated++;
            }
        });

        return [
            'status' => 'OK',
            'orders_processed' => $processed,
            'orders_updated' => $updated,
            'source_revision' => $this->sourceRevision($orderId),
        ];
    }

    /**
     * @return array{status: string, orders_checked: int, mismatches: int, rows: array<int, array<string, mixed>>, source_revision: string}
     */
    public function verify(?int $orderId = null): array
    {
        $rows = [];
        $mismatches = 0;

        $this->query($orderId)->chunkById(100, function ($orders) use (&$rows, &$mismatches): void {
            foreach ($orders as $order) {
                $expected = $this->expected($order);
                $actual = $this->actual($order);
                $matches = $expected === $actual;

                if (! $matches) {
                    $mismatches++;
                }

                $rows[] = [
                    'order_pk' => $order->id,
                    'order_id' => $order->order_id,
                    'matches' => $matches,
                    'expected' => $expected,
                    'actual' => $actual,
                ];
            }
        });

        return [
            'status' => $mismatches === 0 ? 'OK' : 'FAILED',
            'orders_checked' => count($rows),
            'mismatches' => $mismatches,
            'rows' => $rows,
            'source_revision' => $this->sourceRevision($orderId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expected(Order $order): array
    {
        $projection = clone $order;
        $projection->setRelation('items', $order->items);
        $projection->resolveFinancials();

        $expected = [
            'currency' => (string) ($projection->currency ?: 'RUB'),
            'cost_currency' => (string) ($projection->cost_currency ?: 'RUB'),
            'progress_id' => $this->expectedProgressId($order),
        ];

        foreach (self::MONEY_FIELDS as $field => $scale) {
            $expected[$field] = $this->decimal($projection->{$field}, $scale);
        }

        ksort($expected);

        return $expected;
    }

    /**
     * @return array<string, mixed>
     */
    private function actual(Order $order): array
    {
        $actual = [
            'currency' => (string) ($order->currency ?: 'RUB'),
            'cost_currency' => (string) ($order->cost_currency ?: 'RUB'),
            'progress_id' => (int) ($order->progress_id ?? 0),
        ];

        foreach (self::MONEY_FIELDS as $field => $scale) {
            $actual[$field] = $this->decimal($order->{$field}, $scale);
        }

        ksort($actual);

        return $actual;
    }

    /**
     * @param array<string, mixed> $expected
     * @return array<string, mixed>
     */
    private function changes(Order $order, array $expected): array
    {
        $changes = [];
        $actual = $this->actual($order);

        foreach ($expected as $field => $value) {
            if ($actual[$field] !== $value) {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }

    private function expectedProgressId(Order $order): int
    {
        if ($order->items->isEmpty()) {
            return (int) ($order->progress_id ?? 0);
        }

        $allPurchased = $order->items->every(fn ($item): bool => (string) $item->purchase_status === 'success');
        $allActivated = $order->items->every(fn ($item): bool => (bool) $item->is_activated);

        return $allPurchased && $allActivated ? 4 : (int) ($order->progress_id ?? 0);
    }

    private function query(?int $orderId)
    {
        return Order::query()
            ->with('items')
            ->when($orderId, fn ($query) => $query->whereKey($orderId))
            ->orderBy('id');
    }

    private function sourceRevision(?int $orderId): string
    {
        $orders = Order::query()->when($orderId, fn ($query) => $query->whereKey($orderId));
        $items = DB::table('order_items')
            ->when($orderId, fn ($query) => $query->whereIn('order_id', Order::query()->whereKey($orderId)->select('id')));

        return sprintf(
            'orders:%d:%s;order_items:%d:%s',
            (clone $orders)->count(),
            (clone $orders)->max('id') ?? 'none',
            (clone $items)->count(),
            (clone $items)->max('id') ?? 'none',
        );
    }

    private function decimal(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
