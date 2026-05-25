<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

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

        try {
            $model = config('services.ollama.model');
            $url = rtrim(config('services.ollama.url'), '/');
            $response = Http::timeout(300)
                ->post("$url/api/generate", [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if ($response->successful()) {
                return $response->json('response');
            }

            return "Ошибка Ollama: " . $response->body();
        } catch (\Exception $e) {
            return "Ollama не отвечает. Убедитесь, что модель {$model} запущена локально. Детали: " . $e->getMessage();
        }
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
Ты — Sovereign AI Operations Director, суверенный ассистент супер-администратора платформы Meanly.
Ты находишься в Глобальном Операционном Центре платформы (Operations Command Center) и помогаешь супер-администратору мониторить транзакции, складские остатки, партнеров, магазины и интеграции.

ПРОФИЛЬ СУПЕР-АДМИНИСТРАТОРА:
- Имя: {$user->name} ({$user->email})
- Доступ: Глобальный супер-администратор (God Mode)
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

        try {
            $model = config('services.ollama.model');
            $url = rtrim(config('services.ollama.url'), '/');
            $response = Http::timeout(300)
                ->post("$url/api/generate", [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if ($response->successful()) {
                return $response->json('response');
            }

            return "Извините, возникла ошибка связи с операционным ядром.";
        } catch (\Exception $e) {
            return "Я временно не могу связаться с Ollama. Проверьте запуск модели {$model} (Детали: " . $e->getMessage() . ")";
        }
    }
}
