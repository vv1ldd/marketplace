<?php

namespace App\Services;

use App\Models\MeanlyAnalyticsEvent;
use App\Models\MeanlyOperationalAlert;
use App\Models\Order\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class MeanlyOperationalAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function stuckFulfillmentRows(int $days = 2, int $limit = 250): Collection
    {
        return Order::query()
            ->with(['items', 'shop'])
            ->where('sales_channel', 'meanly_storefront')
            ->where('created_at', '>=', now()->subDays($days))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order): ?array => $this->stuckFulfillmentRow($order))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, MeanlyOperationalAlert>
     */
    public function evaluate(): Collection
    {
        $activeKeys = collect();
        $alerts = collect();
        $stuckRows = $this->stuckFulfillmentRows();

        $providerFailures = $stuckRows->where('reason_key', 'provider_failed')->values();
        if ($providerFailures->isNotEmpty()) {
            $alerts->push($this->upsertAlert(
                key: 'fulfillment.provider_failed',
                type: 'fulfillment',
                severity: 'critical',
                surface: 'storefront',
                title: 'Provider выдача падает',
                description: 'Есть оплаченные заказы, где provider redemption завершился ошибкой.',
                occurrenceCount: $providerFailures->count(),
                threshold: 1,
                context: $this->sampleRows($providerFailures),
            ));
            $activeKeys->push('fulfillment.provider_failed');
        }

        $paidNotOpened = $stuckRows->where('reason_key', 'paid_not_opened')->values();
        if ($paidNotOpened->isNotEmpty()) {
            $alerts->push($this->upsertAlert(
                key: 'fulfillment.paid_not_opened',
                type: 'fulfillment',
                severity: 'warning',
                surface: 'storefront',
                title: 'Оплачено, но выдачу не открыли',
                description: 'Покупатели оплатили заказ, но не открыли страницу выдачи больше 15 минут.',
                occurrenceCount: $paidNotOpened->count(),
                threshold: 1,
                context: $this->sampleRows($paidNotOpened),
            ));
            $activeKeys->push('fulfillment.paid_not_opened');
        }

        $openedNotScratched = $stuckRows->where('reason_key', 'opened_not_scratched')->values();
        if ($openedNotScratched->isNotEmpty()) {
            $alerts->push($this->upsertAlert(
                key: 'fulfillment.opened_not_scratched',
                type: 'fulfillment',
                severity: 'warning',
                surface: 'storefront',
                title: 'Код открыт, но карту не стерли',
                description: 'Покупатели открыли выдачу, но не завершили scratch больше 10 минут.',
                occurrenceCount: $openedNotScratched->count(),
                threshold: 1,
                context: $this->sampleRows($openedNotScratched),
            ));
            $activeKeys->push('fulfillment.opened_not_scratched');
        }

        $providerPending = $stuckRows->where('reason_key', 'provider_pending')->values();
        if ($providerPending->isNotEmpty()) {
            $alerts->push($this->upsertAlert(
                key: 'fulfillment.provider_pending',
                type: 'fulfillment',
                severity: 'warning',
                surface: 'storefront',
                title: 'Provider выдача pending слишком долго',
                description: 'Есть оплаченные заказы, где provider/preorder выдача ждет больше 10 минут.',
                occurrenceCount: $providerPending->count(),
                threshold: 1,
                context: $this->sampleRows($providerPending),
            ));
            $activeKeys->push('fulfillment.provider_pending');
        }

        $checkoutErrors = $this->recentAnalyticsErrors('checkout');
        if ($checkoutErrors['count'] > 0) {
            $alerts->push($this->upsertAlert(
                key: 'checkout.errors_1h',
                type: 'checkout',
                severity: $checkoutErrors['count'] >= 3 ? 'critical' : 'warning',
                surface: 'storefront',
                title: 'Ошибки checkout за последний час',
                description: 'Checkout отдает warning/error события. Нужно проверить доступность оплаты и выдачи.',
                occurrenceCount: $checkoutErrors['count'],
                threshold: 1,
                context: $checkoutErrors,
                lastAnalyticsEventId: $checkoutErrors['last_event_id'],
            ));
            $activeKeys->push('checkout.errors_1h');
        }

        $aiWarnings = $this->recentAiWarnings();
        if ($aiWarnings['count'] > 0) {
            $alerts->push($this->upsertAlert(
                key: 'ai.degraded_1h',
                type: 'ai',
                severity: $aiWarnings['count'] >= 5 ? 'critical' : 'warning',
                surface: 'ai',
                title: 'Meanly AI degraded',
                description: 'AI чаще уходит в fallback/error. Нужно проверить Ollama и retrieval.',
                occurrenceCount: $aiWarnings['count'],
                threshold: 1,
                context: $aiWarnings,
                lastAnalyticsEventId: $aiWarnings['last_event_id'],
            ));
            $activeKeys->push('ai.degraded_1h');
        }

        $this->resolveMissing($activeKeys);

        return $alerts->values();
    }

    /**
     * @return Collection<int, MeanlyOperationalAlert>
     */
    public function activeAlerts(): Collection
    {
        return MeanlyOperationalAlert::query()
            ->where('status', 'open')
            ->orderByRaw("field(severity, 'critical', 'error', 'warning', 'info')")
            ->latest('last_seen_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stuckFulfillmentRow(Order $order): ?array
    {
        $info = is_array($order->info) ? $order->info : [];
        $safe = (array) data_get($info, 'order_safe', []);
        $safeStatus = (string) data_get($safe, 'status', '');
        $deliveryStatus = (string) data_get($safe, 'delivery_status', '');
        $openedAt = data_get($safe, 'opened_at');
        $scratchProof = data_get($safe, 'scratch_proof');
        $paymentStatus = (string) data_get($info, 'payment_status', '');
        $ageMinutes = max(0, (int) $order->created_at?->diffInMinutes(now()));
        $openedAgeMinutes = $openedAt ? max(0, (int) now()->diffInMinutes(\Illuminate\Support\Carbon::parse($openedAt), true)) : null;
        $paid = $paymentStatus === 'captured'
            || in_array((string) $order->status, ['COMPLETED', 'PROCESSING', 'PAID'], true)
            || filled(data_get($info, 'wallet_ledger_entry_id'));
        $hasFailedItem = $order->items->contains(fn ($item): bool => (string) $item->purchase_status === 'failed');

        if (
            in_array((string) $order->status, ['FAILED', 'CANCELLED'], true)
            || $hasFailedItem
            || $safeStatus === 'provider_redeem_failed'
        ) {
            return $this->row($order, 'provider_failed', 'Выдача упала', 'danger', $ageMinutes, [
                'safe_status' => $safeStatus ?: 'unknown',
                'delivery_status' => $deliveryStatus ?: 'unknown',
            ]);
        }

        if (
            $paid
            && in_array($safeStatus, ['provider_redeem_pending', 'preorder_pending', 'preparing'], true)
            && $ageMinutes >= 10
        ) {
            return $this->row($order, 'provider_pending', 'Provider/выдача pending дольше 10 мин', 'warning', $ageMinutes, [
                'safe_status' => $safeStatus,
                'delivery_status' => $deliveryStatus ?: 'waiting',
            ]);
        }

        if ($paid && blank($openedAt) && blank($scratchProof) && $ageMinutes >= 15) {
            return $this->row($order, 'paid_not_opened', 'Оплачен, но покупатель не открыл выдачу', 'warning', $ageMinutes, [
                'safe_status' => $safeStatus ?: 'ready_or_unknown',
                'delivery_status' => $deliveryStatus ?: 'not_opened',
            ]);
        }

        if (
            $paid
            && filled($openedAt)
            && blank($scratchProof)
            && $openedAgeMinutes !== null
            && $openedAgeMinutes >= 10
        ) {
            return $this->row($order, 'opened_not_scratched', 'Код открыт, но карта не стерта', 'warning', $openedAgeMinutes, [
                'safe_status' => $safeStatus ?: 'opened',
                'delivery_status' => $deliveryStatus ?: 'opened_not_scratched',
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $state
     * @return array<string, mixed>
     */
    private function row(Order $order, string $reasonKey, string $reason, string $severity, int $ageMinutes, array $state): array
    {
        return [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'status' => (string) $order->status,
            'shop' => $order->shop?->name ?? 'Meanly',
            'reason_key' => $reasonKey,
            'reason' => $reason,
            'severity' => $severity,
            'age_minutes' => $ageMinutes,
            'total' => number_format((float) $order->total_amount, 2, '.', ' ').' '.($order->currency ?: 'RUB'),
            'safe_status' => $state['safe_status'] ?? 'unknown',
            'delivery_status' => $state['delivery_status'] ?? 'unknown',
            'safe_url' => URL::signedRoute('meanly.storefront.orders.safe.show', ['order' => $order->uuid]),
            'created_at' => $order->created_at?->format('d.m H:i') ?? '-',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recentAnalyticsErrors(string $eventType): array
    {
        $query = MeanlyAnalyticsEvent::query()
            ->where('occurred_at', '>=', now()->subHour())
            ->where('event_type', $eventType)
            ->where(function ($query): void {
                $query->whereIn('severity', ['warning', 'error', 'critical'])
                    ->orWhere('status_code', '>=', 400);
            });

        $last = (clone $query)->latest('id')->first();

        return [
            'count' => (clone $query)->count(),
            'last_event_id' => $last?->id,
            'last_event_name' => $last?->event_name,
            'last_seen_at' => $last?->occurred_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recentAiWarnings(): array
    {
        $query = MeanlyAnalyticsEvent::query()
            ->where('occurred_at', '>=', now()->subHour())
            ->where('event_type', 'ai')
            ->where(function ($query): void {
                $query->whereIn('severity', ['warning', 'error', 'critical'])
                    ->orWhereIn('event_name', [
                        'ai.chat.fallback',
                        'ai.chat.fallback_exception',
                        'ai.chat.failed',
                        'ai.chat.exception',
                    ]);
            });

        $last = (clone $query)->latest('id')->first();

        return [
            'count' => (clone $query)->count(),
            'last_event_id' => $last?->id,
            'last_event_name' => $last?->event_name,
            'last_seen_at' => $last?->occurred_at?->toJSON(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function sampleRows(Collection $rows): array
    {
        return [
            'sample' => $rows
                ->take(8)
                ->map(fn (array $row): array => [
                    'order_id' => $row['order_id'],
                    'reason' => $row['reason'],
                    'age_minutes' => $row['age_minutes'],
                    'safe_status' => $row['safe_status'],
                    'delivery_status' => $row['delivery_status'],
                ])
                ->values()
                ->all(),
            'oldest_minutes' => (int) ($rows->max('age_minutes') ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function upsertAlert(
        string $key,
        string $type,
        string $severity,
        ?string $surface,
        string $title,
        string $description,
        int $occurrenceCount,
        int $threshold,
        array $context,
        ?int $lastAnalyticsEventId = null,
        ?int $lastSovereignLedgerId = null,
    ): MeanlyOperationalAlert {
        $now = now();

        return MeanlyOperationalAlert::query()->updateOrCreate(
            ['alert_key' => $key],
            [
                'type' => $type,
                'severity' => $severity,
                'surface' => $surface,
                'status' => 'open',
                'title' => $title,
                'description' => $description,
                'occurrence_count' => $occurrenceCount,
                'threshold' => $threshold,
                'last_analytics_event_id' => $lastAnalyticsEventId,
                'last_sovereign_ledger_id' => $lastSovereignLedgerId,
                'context' => $context,
                'first_seen_at' => MeanlyOperationalAlert::query()->where('alert_key', $key)->value('first_seen_at') ?? $now,
                'last_seen_at' => $now,
                'resolved_at' => null,
            ],
        );
    }

    /**
     * @param  Collection<int, string>  $activeKeys
     */
    private function resolveMissing(Collection $activeKeys): void
    {
        MeanlyOperationalAlert::query()
            ->where('status', 'open')
            ->whereNotIn('alert_key', $activeKeys->all())
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
