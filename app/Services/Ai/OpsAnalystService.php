<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Services\Llm\LlmProviderManager;
use Carbon\Carbon;

class OpsAnalystService
{
    /**
     * Выполняет глобальный ИИ-аудит всей платформы для Супер-администратора
     */
    public function analyzeGlobalSystem(): string
    {
        set_time_limit(0);
        
        $totalPartners = LegalEntity::count();
        $totalShops = Shop::count();
        $totalOrders = Order::count();
        $totalProducts = \App\Models\Product::count();
        $totalVolume = round(\Illuminate\Support\Facades\DB::table('order_items')->sum('price_rub') / 100, 2);
        
        $lowStockCount = \App\Models\WarehouseStock::where('count', '<', 5)->count();
        $criticalErrors = \App\Models\Product::whereNotNull('ym_errors')->count();
        
        // Grab recent global ledger transactions
        $entries = SovereignLedger::orderBy('id', 'desc')->limit(30)->get()->reverse();
        $transcript = "";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('Y-m-d H:i:s');
            $source = $entry->trigger_source ?? 'SYSTEM:INTERNAL';
            $payload = $entry->payload ?? [];
            $input = $entry->input_data ?? [];
            $combinedData = array_merge($payload, $input);
            unset($combinedData['_token'], $combinedData['password']);
            
            $dataJson = json_encode($combinedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($dataJson) > 200) {
                $dataJson = substr($dataJson, 0, 200) . '...';
            }
            $transcript .= "[$date] SOURCE: $source | EVENT: {$entry->event_type} | AMOUNT: " . ($entry->amount_base ?? 0) . " | DATA: $dataJson\n";
        }

        $prompt = <<<EOT
Ты — Приватный Суверенный ИИ-Аудитор всей платформы Meanly (Global Operations AI Auditor). Твоя задача — проанализировать глобальное состояние системы и лог событий Sovereign Ledger, чтобы предоставить краткую выжимку Супер-администратору платформы.

ГЛОБАЛЬНЫЙ СИСТЕМНЫЙ КОНТЕКСТ:
- Всего партнеров (ИП/ТОО): $totalPartners
- Всего магазинов: $totalShops
- Всего заказов в системе: $totalOrders
- Общий торговый оборот: $totalVolume RUB
- Всего товаров в каталоге: $totalProducts
- Товаров с критическим остатком: $lowStockCount
- Ошибок интеграций (Yandex Market): $criticalErrors

ПОСЛЕДНИЕ СОБЫТИЯ В ГЛОБАЛЬНОМ LEDGER:
$transcript

ТВОИ ЗАДАЧИ:
1. Дай оценку общей операционной стабильности системы (все ли транзакции проходят корректно).
2. Выяви потенциальные риски или аномалии (ошибки у партнеров, критически низкие складские остатки).
3. Сформулируй одно точечное административное решение для улучшения работы платформы.

Отвечай строго на РУССКОМ языке, лаконично, в стиле приватного операционного консультанта.
EOT;

        $response = app(LlmProviderManager::class)->generateText($prompt, [
            'timeout' => 300,
            'temperature' => 0.2,
            'system' => 'You are a private global marketplace operations auditor. Answer in Russian.',
        ]);

        return $response->ok
            ? $response->text
            : "LLM provider layer недоступен ({$response->provider}). Детали: {$response->error}";
    }

    /**
     * Обрабатывает интерактивное общение с супер-администратором в глобальном чате
     */
    public function chatGlobal(User $user, string $message): string
    {
        $totalPartners = LegalEntity::count();
        $totalShops = Shop::count();
        $totalOrders = Order::count();
        $totalProducts = \App\Models\Product::count();
        $totalVolume = round(\Illuminate\Support\Facades\DB::table('order_items')->sum('price_rub') / 100, 2);

        $entries = SovereignLedger::orderBy('id', 'desc')->limit(30)->get()->reverse();
        $transcript = "";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('Y-m-d H:i:s');
            $source = $entry->trigger_source ?? 'SYSTEM:INTERNAL';
            $payload = $entry->payload ?? [];
            $input = $entry->input_data ?? [];
            $combinedData = array_merge($payload, $input);
            unset($combinedData['_token'], $combinedData['password']);
            
            $dataJson = json_encode($combinedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($dataJson) > 200) {
                $dataJson = substr($dataJson, 0, 200) . '...';
            }
            $transcript .= "[$date] SOURCE: $source | EVENT: {$entry->event_type} | DATA: $dataJson\n";
        }

        $prompt = <<<EOT
Ты — Sovereign AI Operations Director, суверенный ассистент sovereign validator платформы Meanly.
Ты находишься в Глобальном Операционном Центре платформы (Operations Command Center) и помогаешь sovereign validator мониторить транзакции, складские остатки, партнеров, магазины и интеграции.

ПРОФИЛЬ SOVEREIGN VALIDATOR:
- Имя: {$user->name} ({$user->sovereignIdentityAddress()})
- Доступ: Sovereign Validator
- Партнеров в системе: $totalPartners
- Магазинов в системе: $totalShops
- Всего заказов: $totalOrders
- Торговый оборот всей платформы: $totalVolume RUB

ПРАВИЛА ОБЩЕНИЯ:
1. Отвечай кратко, технично, профессионально, как глобальный операционный терминал.
2. Твой стиль: Sci-Fi / Cyberpunk / Sovereign Ledger Operations Center.
3. Помогай отвечать на вопросы о состоянии серверов, интеграциях, ликвидности партнеров и безопасности реестра.

ПОСЛЕДНИЕ ТРАНЗАКЦИИ В ГЛОБАЛЬНОМ LEDGER:
$transcript

ЗАПРОС СУПЕР-АДМИНИСТРАТОРА:
$message

ТВОЯ ЗАДАЧА: Дать интеллектуальный и полезный ответ на основе операционного контекста всей платформы Meanly.
EOT;

        $response = app(LlmProviderManager::class)->generateText($prompt, [
            'timeout' => 300,
            'temperature' => 0.2,
            'system' => 'You are a private global marketplace operations assistant. Answer in Russian.',
        ]);

        return $response->ok
            ? $response->text
            : "Я временно не могу связаться с LLM provider layer ({$response->provider}). Детали: {$response->error}";
    }
}
