<?php

namespace App\Models\Order;

use App\Models\PlayStation\PlayStationTypeForm;
use App\Models\Product;
use App\Models\User;
use App\Models\WildflowCatalog;
use App\Services\StandardizationService;
use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($item) {
            if ($item->original_code) {
                $order = $item->order;
                if ($order && in_array($order->progress_id, [1, 2, 3])) {
                    $allItemsHasCode = OrderItems::where('order_id', $item->order_id)
                        ->whereNull('original_code')
                        ->doesntExist();

                    $order->update([
                        'progress_id' => $allItemsHasCode ? 4 : 3,
                    ]);
                }
            }
        });

        static::updated(function ($item) {
            $changes = $item->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            // 🛡️ Sovereign Ledger: Record manual interventions
            $criticalKeys = ['original_code', 'purchase_status', 'is_activated', 'client_info'];
            $intersect = array_intersect(array_keys($changes), $criticalKeys);

            if (!empty($intersect) && $item->order?->shop) {
                app(\App\Services\LedgerService::class)->record($item->order->shop, 'VOUCHER_MANUAL_ADJUSTMENT', $item, [
                    'changes' => $changes,
                    'original' => array_intersect_key($item->getOriginal(), $changes)
                ]);
            }
        });
    }

    protected $casts = [
        'activate_till' => 'datetime',
        'client_info' => \App\Casts\VaultEncryptedJson::class,
        'is_activated' => 'boolean',
        'is_redeemed' => 'boolean',
        'activated_at' => 'datetime',
        'purchase_status' => 'string',
        'redeem_started_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'key' => \App\Casts\VaultEncrypted::class . ':key_bidx',
        'original_code' => \App\Casts\VaultEncrypted::class,
        'nominal_amount' => 'decimal:2',
    ];

    /**
     * Find an order item by its SVC voucher key using a Blind Index.
     * Use this instead of ->where('key', $code) everywhere.
     */
    public static function findByKey(string $key): ?static
    {
        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($key)), $salt);
        return static::where('key_bidx', $bidx)->first();
    }

    public static function findByKeyWith(string $key, array $relations): ?static
    {
        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($key)), $salt);
        return static::with($relations)->where('key_bidx', $bidx)->first();
    }

    /**
     * Public support reference for customer-facing screens.
     *
     * Prefer the Simple Layer 1 ledger fingerprint so support can resolve it by hash prefix.
     * Fall back to a deterministic HMAC without exposing the internal UUID.
     */
    public function supportReference(): string
    {
        return $this->transactionReference();
    }

    public function transactionReference(): string
    {
        return app(\App\Services\SimpleLayer1TransactionReferenceService::class)->forModel($this);
    }

    public function providerReference(): string
    {
        return filled($this->provider_order_id)
            ? (string) $this->provider_order_id
            : $this->transactionReference();
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderItemTypes::class, 'type_id', 'id');
    }

    public function typeForm(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PlayStationTypeForm::class, 'type_form_id', 'id');
    }

    // app/Models/OrderItem.php
    public function game()
    {
        return $this->belongsTo(\App\Models\Product::class, 'sku', 'sku');
    }

    /**
     * Форма redeem с чекбоксами PlayStation Network (type_form_id = 1).
     * Никогда не показывается для Wildflow-товаров (SKU начинается с WF- или WFC-).
     */
    public function showPlaystationRedeemAccountForm(): bool
    {
        // Wildflow-товары никогда не требуют PS-форму
        $sku = (string) ($this->sku ?? '');
        if (str_starts_with($sku, 'WF-') || str_starts_with($sku, 'WFC-')) {
            return false;
        }

        if ((int) ($this->type_form_id ?? 0) !== 1) {
            return false;
        }

        $product = $this->relationLoaded('game') ? $this->game : $this->game()->first();
        if ($product instanceof Product) {
            if ($product->wildflow_catalog_sku || $product->provider_id) {
                return false;
            }

            return method_exists($product, 'skipsPlaystationRedeemAccountForm')
                ? ! $product->skipsPlaystationRedeemAccountForm()
                : true;
        }

        return ! str_starts_with($sku, 'VOUCHER-');
    }

    /**
     * Нужны ли на /redeem поля ФИО и телефон (региональный KYC). Global = только email + код подтверждения.
     */
    public function redeemCollectsExtendedProfile(): bool
    {
        if ($this->showPlaystationRedeemAccountForm()) {
            return true;
        }

        $order = $this->relationLoaded('order') ? $this->order : $this->order()->first();
        $shop = $order?->relationLoaded('shop') ? $order->shop : $order?->shop()->first();

        return (bool) ($shop?->redeem_requires_extended_profile ?? false);
    }

    /*
        public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
        {
            return $this->belongsTo(User::class, 'user_id')
                ->whereColumn('orders.user_id', 'users.id')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->select('users.*');
        }
    */

    public function getStandardizedDataAttribute(): ?array
    {
        $catalog = WildflowCatalog::findForOrderOfferSku($this->sku);
        if (! $catalog) {
            return null;
        }

        $catalog->loadMissing(['brand', 'region']);

        $standardizer = app(StandardizationService::class);

        $data = $this->original_code
            ? $standardizer->standardizeRetailCode([
                'card_number' => $this->original_code,
            ], $catalog)
            : $standardizer->standardizeCatalogItem($catalog);

        return $this->mergeStandardizedShapeForViews($data, $catalog, $standardizer);
    }

    /**
     * finish.blade.php и др. ожидают product, credentials, geography, assets, redemption в одном виде.
     * standardizeCatalogItem даёт identity/sku без product; standardizeRetailCode — без geography/assets.
     */
    protected function mergeStandardizedShapeForViews(array $data, WildflowCatalog $catalog, StandardizationService $standardizer): array
    {
        if (! isset($data['product'])) {
            $data['product'] = [
                'sku' => $data['sku'] ?? $catalog->sku,
                'title' => data_get($data, 'identity.title', $catalog->title),
                'brand' => data_get($data, 'identity.brand', $catalog->brand?->name ?? 'Product'),
            ];
        }

        if (! isset($data['credentials'])) {
            $data['credentials'] = [
                'code' => $this->original_code,
                'pin' => null,
                'serial' => null,
                'valid_until' => null,
            ];
        } elseif ($this->original_code && empty($data['credentials']['code'])) {
            $data['credentials']['code'] = $this->original_code;
        }

        if (! isset($data['geography']) || ! isset($data['assets'])) {
            $shell = $standardizer->standardizeCatalogItem($catalog);
            $data['geography'] = $data['geography'] ?? $shell['geography'];
            $data['assets'] = $data['assets'] ?? $shell['assets'];
        }

        $data['redemption'] ??= [
            'activation_url' => null,
            'instructions' => null,
        ];

        if (filled($this->original_code)) {
            $data['redemption']['instructions'] = $catalog->redeemFinishCustomerInstructions();
        }

        return $data;
    }
}
