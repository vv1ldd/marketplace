<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\WarehouseStock;
use App\Models\Order\Order;
use App\Services\Llm\LlmProviderManager;
use Carbon\Carbon;

class PartnerAnalystService
{
    /**
     * Выполняет ИИ-аудит магазина в рамках B2B-кабинета
     */
    public function analyze(Shop $shop): string
    {
        set_time_limit(0);
        $prompt = $this->buildAnalysisPrompt($shop);
        
        $response = app(LlmProviderManager::class)->generateText($prompt, [
            'timeout' => 300,
            'temperature' => 0.2,
            'system' => 'You are a private B2B marketplace operations analyst. Answer in Russian.',
        ]);

        return $response->ok
            ? $response->text
            : "LLM provider layer недоступен ({$response->provider}). Детали: {$response->error}";
    }

    /**
     * Формирует изолированный промпт для ИИ-аудита магазина
     */
    public function buildAnalysisPrompt(Shop $shop): string
    {
        $legalEntity = $shop->legalEntity;
        $transcript = $this->getShopTranscript($shop);
        $snapshot = $legalEntity ? $this->getPartnerSnapshot($legalEntity) : "";

        $balance = $legalEntity ? number_format($legalEntity->available_balance, 2, '.', ' ') : '0.00';
        $reserved = $legalEntity ? number_format($legalEntity->reserved_balance, 2, '.', ' ') : '0.00';

        return <<<EOT
Ты — Приватный Суверенный ИИ-Аналитик (Partner AI Auditor). Твоя задача — проанализировать состояние магазина и лог событий его леджера, чтобы предоставить краткую выжимку владельцу кабинета.
Не упоминай глобальные переменные или другие магазины платформы. Анализируй строго в границах этого кабинета.

КОНТЕКСТ МАГАЗИНА:
- Название магазина: {$shop->name}
- Юридическое лицо: {$legalEntity->name}
- ИНН: {$legalEntity->inn}
- Баланс: $balance RUB (В резерве: $reserved RUB)

ЛОКАЛЬНЫЙ ЛЕДЖЕР СОБЫТИЙ МАГАЗИНА:
$transcript

АКТУАЛЬНЫЕ МЕТРИКИ КАБИНЕТА:
$snapshot

ТВОИ ЗАДАЧИ:
1. Оцени финансовую достаточность (хватает ли баланса для текущего объема заказов и поставок).
2. Выяви аномалии (ошибки импорта товаров из Yandex Market, отмены заказов, сбои).
3. Сформулируй одну точечную B2B-рекомендацию (например, скорректировать остатки на складе или пополнить баланс).

Отвечай строго на РУССКОМ языке, лаконично, в стиле приватного бизнес-консультанта.
EOT;
    }

    /**
     * Получает срез событий конкретного магазина
     */
    public function getShopTranscript(Shop $shop, int $limit = 30): string
    {
        $entries = SovereignLedger::where('shop_id', $shop->id)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $transcript = "";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('Y-m-d H:i:s');
            $source = $entry->trigger_source ?? 'PARTNER:INTERNAL';
            
            $payload = $entry->payload ?? [];
            $input = $entry->input_data ?? [];
            $combinedData = array_merge($payload, $input);
            unset($combinedData['_token'], $combinedData['password']);
            
            $dataJson = json_encode($combinedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($dataJson) > 300) {
                $dataJson = substr($dataJson, 0, 300) . '...[truncated]';
            }

            $transcript .= "[$date] SOURCE: $source | EVENT: {$entry->event_type} | DATA: $dataJson\n";
        }

        return $transcript ?: "События в леджере этого магазина отсутствуют.";
    }

    /**
     * Собирает изолированный снимок показателей для партнера
     */
    public function getPartnerSnapshot(LegalEntity $legalEntity): string
    {
        $shopIds = $legalEntity->shops()->pluck('id')->toArray();
        $today = Carbon::today();

        // 1. Складские остатки
        $lowStock = WarehouseStock::whereHas('product', fn($q) => $q->whereIn('shop_id', $shopIds))
            ->where('count', '<', 5)
            ->with('product')
            ->limit(5)
            ->get()
            ->map(fn($s) => "{$s->product?->sku}: {$s->count} шт.")
            ->implode(', ');

        $totalVouchers = \App\Models\ProductInventory::where('is_used', false)
            ->where('status', 'available')
            ->whereIn('shop_id', $shopIds)
            ->count();

        // 2. Статистика заказов
        $ordersToday = Order::whereIn('shop_id', $shopIds)->whereDate('created_at', $today)->count();
        $pendingOrders = Order::whereIn('shop_id', $shopIds)->whereIn('progress_id', [1, 2, 3])->count();
        $failedOrders = Order::whereIn('shop_id', $shopIds)->whereDate('created_at', $today)->where('status', 'CANCELLED')->count();

        $successRate = $ordersToday > 0 ? round((($ordersToday - $failedOrders) / $ordersToday) * 100, 1) : 100;

        return "--- СОСТОЯНИЕ СКЛАДОВ И ИНВЕНТАРЯ ---\n" .
               "Доступных ваучеров в вашем стоке: {$totalVouchers}\n" .
               "Ваши критические остатки (<5 шт): " . ($lowStock ?: "Нет") . "\n\n" .
               "--- ОПЕРАЦИОННАЯ СТАТИСТИКА ---\n" .
               "Ваших заказов сегодня: {$ordersToday} (Успешность: {$successRate}%)\n" .
               "Ожидают обработки: {$pendingOrders}\n" .
               "Отмен сегодня: {$failedOrders}";
    }

    /**
     * Обрабатывает интерактивное общение с партнером в чате
     */
    public function chat(User $user, string $message): string
    {
        $legalEntity = $user->legalEntities()->first();
        $legalName = $legalEntity ? $legalEntity->name : 'Частный B2B-Партнер';
        $inn = $legalEntity ? $legalEntity->inn : '—';
        $balance = $legalEntity ? number_format($legalEntity->available_balance, 2, '.', ' ') : '0.00';
        $reserved = $legalEntity ? number_format($legalEntity->reserved_balance, 2, '.', ' ') : '0.00';

        // Изолированные логи
        $transcript = "";
        if ($legalEntity) {
            $shopIds = $legalEntity->shops()->pluck('id')->toArray();
            $entries = SovereignLedger::whereIn('shop_id', $shopIds)
                ->orderBy('id', 'desc')
                ->limit(30)
                ->get()
                ->reverse();

            foreach ($entries as $entry) {
                $date = $entry->created_at->format('Y-m-d H:i:s');
                $source = $entry->trigger_source ?? 'PARTNER:INTERNAL';
                $payload = $entry->payload ?? [];
                $input = $entry->input_data ?? [];
                $combinedData = array_merge($payload, $input);
                unset($combinedData['_token'], $combinedData['password']);
                
                $dataJson = json_encode($combinedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (strlen($dataJson) > 300) {
                    $dataJson = substr($dataJson, 0, 300) . '...[truncated]';
                }
                $transcript .= "[$date] SOURCE: $source | EVENT: {$entry->event_type} | DATA: $dataJson\n";
            }
        }
        if (empty($transcript)) {
            $transcript = "Нет записанных транзакций и событий для ваших магазинов в Ledger.";
        }

        $commandContext = "";
        if (str_contains(strtolower($message), 'sync')) {
            $commandContext = "ПОДСКАЗКА: Пользователь интересуется синхронизацией. Упомяни, что это можно сделать через php artisan app:update-currency-rates.";
        }
        if (str_contains(strtolower($message), 'ledger') || str_contains(strtolower($message), 'леджер')) {
            $commandContext = "ПОДСКАЗКА: Пользователь спрашивает про леджер. Напомни, что леджер детерминирован и его можно сбросить через sovereign:ledger-reset.";
        }

        $prompt = <<<EOT
Ты — Sovereign AI Assistant, приватный интеллектуальный ассистент B2B-партнера маркетплейса Meanly.
Ты функционируешь внутри защищенного личного кабинета партнера и анализируешь транзакции, складские операции, интеграции и безопасность только его компании.

ПРОФИЛЬ ТЕКУЩЕГО КАБИНЕТА:
- Владелец кабинета: {$user->name} ({$user->sovereignIdentityAddress()})
- Юридическое лицо: $legalName (ИНН: $inn)
- Доступный баланс: $balance RUB (В резерве/холде: $reserved RUB)

ПРАВИЛА ОБЩЕНИЯ:
1. Отвечай кратко и технично, как кибернетический терминал B2B-консоли.
2. Фокусируйся исключительно на транзакциях, поставках, заказах, кодах активации и балансах этого партнера.
3. Помогай отвечать на вопросы о его финансах, складах, интеграциях (например, Yandex Market) и статусе безопасности.
4. Твой стиль: Cyberpunk / Merchant Systems Terminal.

ПОСЛЕДНИЕ СОБЫТИЯ В ЛЕДЖЕРЕ ЭТОГО КАБИНЕТА:
$transcript

$commandContext

ЗАПРОС ПОЛЬЗОВАТЕЛЯ:
$message

ТВОЯ ЗАДАЧА: Дать интеллектуальный ответ на основе контекста кабинета партнера.
EOT;

        $response = app(LlmProviderManager::class)->generateText($prompt, [
            'timeout' => 300,
            'temperature' => 0.2,
            'system' => 'You are a private B2B marketplace assistant. Answer in Russian.',
        ]);

        return $response->ok
            ? $response->text
            : "Я временно не могу связаться с LLM provider layer ({$response->provider}). Детали: {$response->error}";
    }
}
