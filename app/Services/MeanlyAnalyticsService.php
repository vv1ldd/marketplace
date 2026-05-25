<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\MeanlyAnalyticsEvent;
use App\Models\Order\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MeanlyAnalyticsService
{
    private const SLOW_REQUEST_MS = 1200;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $attributes
     */
    public function track(string $eventName, array $context = [], array $attributes = []): ?MeanlyAnalyticsEvent
    {
        try {
            $request = request();
            $eventName = trim($eventName);

            if ($eventName === '') {
                return null;
            }

            $durationMs = $this->nullableInt($attributes['duration_ms'] ?? null);
            $statusCode = $this->nullableInt($attributes['status_code'] ?? null);
            $eventType = (string) ($attributes['event_type'] ?? Str::before($eventName, '.'));

            if ($eventType === '') {
                $eventType = 'event';
            }

            $event = MeanlyAnalyticsEvent::create([
                'event_type' => Str::limit($eventType, 64, ''),
                'event_name' => Str::limit($eventName, 160, ''),
                'surface' => $this->surface($attributes, $request),
                'severity' => $this->severity($attributes, $statusCode),
                'request_id' => $this->requestId($attributes, $request),
                'user_id' => $this->nullableInt($attributes['user_id'] ?? $request->user()?->id),
                'session_hash' => $this->sessionHash($request),
                'visitor_hash' => $this->hashValue($attributes['visitor_id'] ?? data_get($context, 'visitor_id')),
                'ip_hash' => $this->hashValue($request->ip()),
                'user_agent_hash' => $this->hashValue((string) $request->userAgent()),
                'route_name' => Str::limit((string) ($attributes['route_name'] ?? $request->route()?->getName()), 255, '') ?: null,
                'route_action' => Str::limit((string) ($attributes['route_action'] ?? $request->route()?->getActionName()), 255, '') ?: null,
                'method' => Str::limit((string) ($attributes['method'] ?? $request->method()), 12, '') ?: null,
                'path' => Str::limit((string) ($attributes['path'] ?? $request->path()), 1024, '') ?: null,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'is_slow' => (bool) ($attributes['is_slow'] ?? ($durationMs !== null && $durationMs >= self::SLOW_REQUEST_MS)),
                'product_id' => $this->nullableInt($attributes['product_id'] ?? data_get($context, 'product_id')),
                'order_id' => $this->nullableInt($attributes['order_id'] ?? data_get($context, 'order_id')),
                'shop_id' => $this->nullableInt($attributes['shop_id'] ?? data_get($context, 'shop_id')),
                'legal_entity_id' => $this->nullableInt($attributes['legal_entity_id'] ?? data_get($context, 'legal_entity_id')),
                'provider_type' => $this->nullableString($attributes['provider_type'] ?? data_get($context, 'provider_type'), 64),
                'category' => $this->nullableString($attributes['category'] ?? data_get($context, 'category'), 128),
                'currency' => $this->nullableString($attributes['currency'] ?? data_get($context, 'currency'), 12),
                'error_class' => $this->nullableString($attributes['error_class'] ?? null, 255),
                'error_message' => $this->nullableString($attributes['error_message'] ?? null, 2000),
                'error_fingerprint' => $this->nullableString($attributes['error_fingerprint'] ?? null, 64),
                'context' => $this->sanitizeContext($context),
                'occurred_at' => $attributes['occurred_at'] ?? now(),
            ]);

            $this->mirrorBusinessCheckpointToLedger($event, $context, $attributes);

            return $event;
        } catch (Throwable $e) {
            Log::debug('Meanly analytics write skipped: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $attributes
     */
    public function trackException(Throwable $exception, string $eventName = 'error.exception', array $context = [], array $attributes = []): ?MeanlyAnalyticsEvent
    {
        return $this->track($eventName, $context, $attributes + [
            'event_type' => 'error',
            'severity' => 'error',
            'error_class' => $exception::class,
            'error_message' => $exception->getMessage(),
            'error_fingerprint' => $this->exceptionFingerprint($exception),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function measure(string $eventName, callable $callback, array $context = [], array $attributes = []): mixed
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();

            $this->track($eventName, $context, $attributes + [
                'event_type' => $attributes['event_type'] ?? 'performance',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $result;
        } catch (Throwable $exception) {
            $this->trackException($exception, $eventName.'.failed', $context, $attributes + [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function surface(array $attributes, Request $request): ?string
    {
        if (filled($attributes['surface'] ?? null)) {
            return $this->nullableString($attributes['surface'], 64);
        }

        $path = '/'.ltrim($request->path(), '/');
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            str_starts_with($path, '/meanly-ai') || str_contains($routeName, 'chat') => 'ai',
            str_starts_with($path, '/store') || str_starts_with($path, '/catalog') || $path === '/' => 'storefront',
            str_starts_with($path, '/partner') => 'b2b',
            str_starts_with($path, '/api') => 'api',
            str_starts_with($path, '/admin') || str_starts_with($path, '/ops') => 'ops',
            default => 'app',
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function severity(array $attributes, ?int $statusCode): string
    {
        if (filled($attributes['severity'] ?? null)) {
            return Str::limit((string) $attributes['severity'], 24, '');
        }

        if ($statusCode !== null && $statusCode >= 500) {
            return 'error';
        }

        if ($statusCode !== null && $statusCode >= 400) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requestId(array $attributes, Request $request): ?string
    {
        $requestId = $attributes['request_id']
            ?? $request->headers->get('X-Request-Id')
            ?? $request->attributes->get('meanly_request_id');

        return $this->nullableString($requestId, 64);
    }

    private function sessionHash(Request $request): ?string
    {
        try {
            if (! $request->hasSession()) {
                return null;
            }

            return $this->hashValue($request->session()->getId());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $attributes
     */
    private function mirrorBusinessCheckpointToLedger(MeanlyAnalyticsEvent $event, array $context, array $attributes): void
    {
        if (! $this->shouldMirrorToLedger($event, $attributes)) {
            return;
        }

        try {
            $shop = $this->resolveShop($event);
            $legalEntity = $this->resolveLegalEntity($event, $shop);
            $entity = $this->resolveLedgerEntity($event);

            $ledgerEntry = app(LedgerService::class)->record(
                shop: $shop,
                eventType: 'ANALYTICS_CHECKPOINT',
                entity: $entity,
                payload: [
                    'analytics_event_id' => $event->id,
                    'analytics_event_name' => $event->event_name,
                    'analytics_event_type' => $event->event_type,
                    'surface' => $event->surface,
                    'severity' => $event->severity,
                    'status_code' => $event->status_code,
                    'duration_ms' => $event->duration_ms,
                    'product_id' => $event->product_id,
                    'order_id' => $event->order_id,
                    'shop_id' => $event->shop_id,
                    'legal_entity_id' => $event->legal_entity_id,
                    'provider_type' => $event->provider_type,
                    'category' => $event->category,
                    'currency' => $event->currency,
                    'request_id' => $event->request_id,
                    'context' => $this->sanitizeContext($context),
                ],
                legalEntity: $legalEntity,
                triggerSource: 'DID:SYS | MEANLY_ANALYTICS_BRIDGE',
                inputData: [
                    'event_name' => $event->event_name,
                    'event_type' => $event->event_type,
                    'mirror_reason' => $attributes['mirror_reason'] ?? 'business_checkpoint',
                ],
                outputState: [
                    'analytics_event_id' => $event->id,
                    'occurred_at' => $event->occurred_at?->toJSON(),
                ],
            );

            $event->forceFill(['sovereign_ledger_id' => $ledgerEntry->id])->save();
        } catch (Throwable $e) {
            Log::warning('Meanly analytics ledger mirror skipped: '.$e->getMessage(), [
                'analytics_event_id' => $event->id,
                'event_name' => $event->event_name,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function shouldMirrorToLedger(MeanlyAnalyticsEvent $event, array $attributes): bool
    {
        if (($attributes['mirror_to_ledger'] ?? null) === true) {
            return true;
        }

        if (($attributes['mirror_to_ledger'] ?? null) === false) {
            return false;
        }

        return in_array($event->event_name, [
            'checkout.order.created',
            'checkout.wallet.confirmed',
            'fulfillment.issue.opened',
            'fulfillment.issue.scratched',
            'provider.order.started',
            'provider.order.succeeded',
            'provider.order.failed',
            'finance.capture.confirmed',
            'stock.replenished',
            'stock.depleted',
        ], true);
    }

    private function resolveShop(MeanlyAnalyticsEvent $event): ?Shop
    {
        if ($event->shop_id) {
            return Shop::query()->find($event->shop_id);
        }

        if ($event->order_id) {
            return Order::query()->find($event->order_id)?->shop;
        }

        if ($event->product_id) {
            return Product::query()->find($event->product_id)?->shop;
        }

        return null;
    }

    private function resolveLegalEntity(MeanlyAnalyticsEvent $event, ?Shop $shop): ?LegalEntity
    {
        if ($event->legal_entity_id) {
            return LegalEntity::query()->find($event->legal_entity_id);
        }

        return $shop?->legalEntity;
    }

    private function resolveLedgerEntity(MeanlyAnalyticsEvent $event): ?Model
    {
        if ($event->order_id) {
            $order = Order::query()->find($event->order_id);
            if ($order) {
                return $order;
            }
        }

        if ($event->product_id) {
            $product = Product::query()->find($event->product_id);
            if ($product) {
                return $product;
            }
        }

        return $event;
    }

    private function exceptionFingerprint(Throwable $exception): string
    {
        return hash('sha256', implode('|', [
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
            Str::limit($exception->getMessage(), 160, ''),
        ]));
    }

    private function hashValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return hash('sha256', (string) config('app.key').'|'.$value);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        return $this->sanitizeArray($context);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $value, int $depth = 0): array
    {
        if ($depth > 4) {
            return ['_truncated' => true];
        }

        $sanitized = [];
        $count = 0;

        foreach ($value as $key => $item) {
            if ($count >= 80) {
                $sanitized['_truncated'] = true;
                break;
            }

            $keyString = is_string($key) ? $key : (string) $key;
            $sanitized[$keyString] = $this->sanitizeValue($keyString, $item, $depth + 1);
            $count++;
        }

        return $sanitized;
    }

    private function sanitizeValue(string $key, mixed $value, int $depth): mixed
    {
        $sensitiveExactKeys = [
            'assertion',
            'card',
            'code',
            'credential',
            'csrf',
            'email',
            'key',
            'password',
            'phone',
            'pin',
            'secret',
            'signature',
            'token',
            'voucher',
        ];

        $normalizedKey = Str::lower($key);
        foreach ($sensitiveExactKeys as $needle) {
            if (
                $normalizedKey === $needle
                || str_ends_with($normalizedKey, '_'.$needle)
                || str_ends_with($normalizedKey, $needle.'_value')
                || str_ends_with($normalizedKey, $needle.'_raw')
            ) {
                return '[redacted]';
            }
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value, $depth);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return Str::limit((string) $value, 500, '...');
    }
}
