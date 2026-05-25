<?php

namespace App\Models\Order;

use App\Helpers\NormalizePhone;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'uuid',
        'status',
        'sub_status',
        'info',
        'client_info',
        'chat_id',
        'customer_id',
        'comment',
        'is_problem',
        'assigned_user_id',
        'assigned_at',
        'code_activated',
        'account_data_on_send',
        'shop_id',
        'is_test',
        'progress_id',
        'user_id',
        'dispute_decision',
        'sales_channel',
        'business_id',
        'campaign_id',
        'total_amount',
        'currency',
        'total_amount_base',
        'exchange_rate',
        'cost_amount',
        'cost_currency',
        'cost_amount_base',
        'margin_base',
        'search_log_id',
    ];

    protected $casts = [
        'info' => \App\Casts\VaultEncryptedJson::class,
        'client_info' => \App\Casts\VaultEncryptedJson::class,
        'code_activated' => 'boolean',
        'is_problem' => 'boolean',
        'is_test' => 'boolean',
        'total_amount' => 'decimal:2',
        'total_amount_base' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'cost_amount' => 'decimal:2',
        'cost_amount_base' => 'decimal:2',
        'margin_base' => 'decimal:2',
    ];

    public function searchLog(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CatalogSearchLog::class);
    }

    public function searchAttributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order\OrderSearchAttribution::class);
    }


    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'id');
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    // Alias for legacy OrderForm compatibility (commit 03d451d used $order->user)
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Shop::class);
    }

    public function legalEntity(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            \App\Models\LegalEntity::class,
            \App\Models\Shop::class,
            'id',
            'id',
            'shop_id',
            'legal_entity_id'
        );
    }

    public function progress(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderProgress::class, 'progress_id', 'id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    // Scope — доступные для взятия исполнителем (старые)
    public function scopeAvailableForExecutor($query)
    {
        return $query->where('code_activated', true)
            ->whereNull('assigned_user_id')
            ->where('is_problem', false)
            ->where('progress_id', 1)
            ->whereDate('created_at', '>=', '2025-10-01')
            ->orderBy('created_at', 'asc');
    }

    public function scopeAvailableForSupport($query)
    {
        return $query->where('is_problem', true)
            ->whereNull('assigned_user_id')
            ->where('progress_id', '<>', 4)
            ->whereDate('created_at', '>=', '2025-10-01')
            ->orderBy('created_at', 'asc');
    }

    public function scopeCheckLimit($query)
    {
        return $query->where('assigned_user_id', auth()->user()->id);
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderComment::class);
    }

    public function supportTicket(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Ticket::class);
    }

    public function transactionReference(): string
    {
        return app(\App\Services\SimpleLayer1TransactionReferenceService::class)->forModel($this);
    }

    public function ymNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(YmNotification::class, 'order_id', 'order_id');
    }

    /**
     * Заказ из песочницы Яндекс.Маркета: в info лежит fake из ответа Маркета — реальный Wildflow не вызываем.
     */
    public function isYandexSandboxOrder(): bool
    {
        return (bool) data_get($this->info, 'fake', false);
    }

    public function shouldRedeemThroughProvider(): bool
    {
        return ! $this->isYandexSandboxOrder()
            || (bool) data_get($this->info, 'redeem_live_provider', false)
            || (bool) data_get($this->info, 'wildflow_sandbox_e2e', false);
    }

    /**
     * Локальный dev-заказ (php artisan dev:issue-*): симуляция выдачи кода без Wildflow, не путать с Яндекс-тестом.
     */
    public function isDevRedeemSimulation(): bool
    {
        return (bool) data_get($this->info, 'dev_simulation', false);
    }

    /**
     * Dev-заказ: тот же сценарий что прод (очередь, pending, опрос / письмо со ссылкой), но код выдаёт job без Wildflow.
     */
    public function isDevAsyncRedeemDemo(): bool
    {
        return (bool) data_get($this->info, 'dev_async_redeem_demo', false);
    }

    /**
     * Если у заказа ещё нет user_id, пробуем найти существующего покупателя (customers):
     * колонка customer_id, затем телефон и email из client_info — та же логика, что в UserController::updateOrCreate.
     */
    public function findInferredCustomer(): ?Customer
    {
        if ($this->user_id) {
            return null;
        }

        if ($this->customer_id) {
            return Customer::query()->find($this->customer_id);
        }

        $info = $this->client_info;
        if (! is_array($info)) {
            return null;
        }

        $phoneRaw = $info['phone'] ?? null;
        if ($phoneRaw) {
            $normalized = NormalizePhone::normalize((string) $phoneRaw);
            if ($normalized) {
                $byPhone = Customer::findByPhone($normalized);
                if ($byPhone) {
                    return $byPhone;
                }
            }
        }

        $email = isset($info['email']) ? trim((string) $info['email']) : '';
        if ($email !== '') {
            return Customer::findByEmail($email);
        }

        return null;
    }

    /**
     * Извлекает итоговую сумму и валюту из всех известных источников.
     * Приоритет: info->buyerTotal (Яндекс Маркет) → order_items.price_rub → order_items.price_try → 0
     *
     * @return array{amount: float, currency: string}
     */
    public function resolveTotalFromInfo(): array
    {
        $info = is_array($this->info) ? $this->info : [];

        // 1. Формат Яндекс Маркета
        if (!empty($info['buyerTotal'])) {
            $cur = strtoupper($info['currency'] ?? 'RUB');
            return [
                'amount'   => (float) $info['buyerTotal'],
                'currency' => $cur === 'RUR' ? 'RUB' : $cur,
            ];
        }

        if (!empty($info['itemsTotal'])) {
            return ['amount' => (float) $info['itemsTotal'], 'currency' => 'RUB'];
        }

        // 3. Прямые заказы — берём из позиций
        $rubTotal = $this->items()->sum(\Illuminate\Support\Facades\DB::raw('price_rub * count'));
        $tryTotal = $this->items()->sum(\Illuminate\Support\Facades\DB::raw('price_try * count'));

        // Защита от аномалий (цены в копейках > 50 000 за один ключ)
        if ($rubTotal > 50000) $rubTotal /= 100;
        if ($tryTotal > 50000) $tryTotal /= 100;

        if ($rubTotal > 0) {
            return ['amount' => (float) $rubTotal, 'currency' => 'RUB'];
        }

        if ($tryTotal > 0) {
            return ['amount' => (float) $tryTotal, 'currency' => 'TRY'];
        }

        return ['amount' => 0.0, 'currency' => 'RUB'];
    }

    /**
     * Комплексный расчет всех финансовых показателей заказа.
     * Выручка, Себестоимость, Курсы и Маржа.
     */
    public function resolveFinancials(): void
    {
        // 1. Выручка
        $total = $this->resolveTotalFromInfo();
        $this->total_amount = $total['amount'];
        $this->currency = $total['currency'];

        // 2. Себестоимость
        $items = $this->items;
        $tryCost = 0;
        $rubCost = 0;

        foreach($items as $item) {
            $pRub = (float) $item->price_rub;
            $pTry = (float) $item->price_try;

            // Если цены идентичны — это ошибка импорта, считаем как рубли
            if ($pRub > 0 && $pRub === $pTry) {
                $pTry = 0;
            }

            // Защита от копеек (если одна позиция > 50к)
            if ($pRub > 50000) $pRub /= 100;
            if ($pTry > 50000) $pTry /= 100;

            $rubCost += ($pRub * $item->count);
            $tryCost += ($pTry * $item->count);
        }

        if ($tryCost > 0) {
            $this->cost_amount = $tryCost;
            $this->cost_currency = 'TRY';
        } elseif ($rubCost > 0) {
            $this->cost_amount = $rubCost;
            $this->cost_currency = 'RUB';
        } else {
            $this->cost_amount = 0;
            $this->cost_currency = 'RUB';
        }

        // 3. Курсы и нормализация
        $rates = \Illuminate\Support\Facades\DB::table('currencies')
            ->whereIn('code', [$this->currency, $this->cost_currency])
            ->get()
            ->keyBy('code');

        $revRate = (float) ($rates->get($this->currency)->rate_to_rub ?? 1.0);
        $costRate = (float) ($rates->get($this->cost_currency)->rate_to_rub ?? 1.0);

        $this->exchange_rate = $revRate;
        $this->total_amount_base = $this->total_amount * $revRate;
        $this->cost_amount_base = $this->cost_amount * $costRate;

        // 4. Маржа
        $this->margin_base = $this->total_amount_base - $this->cost_amount_base;
    }
}
