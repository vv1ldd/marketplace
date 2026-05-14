<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Models\SovereignLedger;
use Carbon\Carbon;

class LedgerAnalystService
{
    /**
     * Превращает записи леджера в текстовый поток для ИИ
     */
    public function getSemanticTranscript(Shop $shop, int $limit = 50): string
    {
        $entries = SovereignLedger::where('shop_id', $shop->id)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $transcript = "";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('Y-m-d H:i:s');
            $data = json_encode($entry->payload, JSON_UNESCAPED_UNICODE);
            
            $transcript .= "[$date] EVENT: {$entry->event_type} | PAYLOAD: $data | FINGERPRINT: " . substr($entry->fingerprint, 0, 8) . "...\n";
        }

        return $transcript;
    }

    /**
     * Выполняет запрос к локальной Ollama (Llama 3)
     */
    public function analyze(\App\Models\Shop $shop): string
    {
        set_time_limit(0);
        $prompt = $this->buildAnalysisPrompt($shop);
        
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(300)
                ->post('http://localhost:11434/api/generate', [
                    'model' => 'llama3',
                    'prompt' => $prompt['content'],
                    'stream' => false,
                ]);

            if ($response->successful()) {
                return $response->json('response');
            }

            return "Ошибка Ollama: " . $response->body();
        } catch (\Exception $e) {
            return "Ollama не отвечает. Убедитесь, что она запущена (ollama run llama3). Ошибка: " . $e->getMessage();
        }
    }

    /**
     * Формирует полный контекст для запроса к LLM
     */
    public function buildAnalysisPrompt(Shop $shop): array
    {
        $transcript = $this->getSemanticTranscript($shop);
        $stateScanner = app(SovereignStateScanner::class);
        $systemSnapshot = $stateScanner->getSystemSnapshot();
        
        $balance = $shop->legalEntity?->available_balance ?? 0;
        $reserved = $shop->legalEntity?->reserved_balance ?? 0;

        $prompt = <<<EOT
Ты — Суверенный Аналитик Маркетплейса (Sovereign AI Auditor). Твоя задача — проанализировать лог событий (Sovereign Ledger) и дать краткий отчет.
Игнорируй рутинную синхронизацию валют, если там нет аномалий. Сосредоточься на движении средств, заказах и ошибках.

КОНТЕКСТ МАГАЗИНА:
- Название: {$shop->name}
- Баланс: $balance RUB
- В резерве: $reserved RUB

ПОСЛЕДНИЕ СОБЫТИЯ В LEDGER:
$transcript

АКТУАЛЬНОЕ СОСТОЯНИЕ СИСТЕМЫ (ПЕРЕМЕННЫЕ):
$systemSnapshot

ТВОИ ЗАДАЧИ:
1. Оцени финансовую стабильность (хватает ли баланса для текущих заказов).
2. Выяви аномалии (ликвидации, возвраты, ошибки провайдеров).
3. Дай одну стратегическую рекомендацию (например, пополнить баланс или сменить провайдера).

Отвечай на РУССКОМ языке, кратко, в стиле лаконичного военного отчета. Без лишних предисловий.
EOT;

        return [
            'role' => 'system',
            'content' => $prompt
        ];
    }

    /**
     * Превращает ГЛОБАЛЬНЫЕ записи леджера в текстовый поток
     */
    public function getGlobalTranscript(int $limit = 100): string
    {
        $entries = SovereignLedger::whereNull('shop_id')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $transcript = "";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('Y-m-d H:i:s');
            $data = json_encode($entry->payload, JSON_UNESCAPED_UNICODE);
            $transcript .= "[$date] {$entry->event_type}: $data\n";
        }

        return $transcript;
    }

    /**
     * Превращает АБСОЛЮТНО ВСЕ записи леджера (включая магазины и источники) в поток для ИИ
     */
    public function getTribunalTranscript(int $limit = 100): string
    {
        $entries = SovereignLedger::orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $transcript = "";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('Y-m-d H:i:s');
            $source = $entry->trigger_source ?? 'SYSTEM:INTERNAL';
            
            // Merge payload and input data to give LLM maximum context compactly
            $payload = $entry->payload ?? [];
            $input = $entry->input_data ?? [];
            $combinedData = array_merge($payload, $input);
            
            // Remove potentially huge or noisy items
            unset($combinedData['_token'], $combinedData['password']);
            
            $dataJson = json_encode($combinedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($dataJson) > 500) {
                $dataJson = substr($dataJson, 0, 500) . '...[truncated]';
            }

            $transcript .= "[$date] SOURCE: {$source} | EVENT: {$entry->event_type} | DATA: {$dataJson}\n";
        }

        return $transcript;
    }

    /**
     * Анализ конкретного валютного узла через LLM
     */
    public function analyzeCurrencyNode(\App\Models\Currency $currency): string
    {
        set_time_limit(0); // Allow LLM enough time to think

        $entries = SovereignLedger::where('entity_type', get_class($currency))
            ->where('entity_id', $currency->id)
            ->where('event_type', 'currency.synchronized')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->reverse();

        $transcript = "";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('H:i:s');
            $rate = $entry->payload['rate'] ?? 0;
            $lsi = $entry->payload['lsi'] ?? 0;
            $obs = $entry->payload['obs'] ?? 0;
            $cap = $entry->payload['cap'] ?? 0;
            $slip = $entry->payload['slippage'] ?? 0;
            $transcript .= "[$date] Rate: $rate | LSI: " . ($lsi * 100) . "% | Cap: $$cap | Slip: {$slip}bps\n";
        }
        
        $prompt = <<<EOT
Ты — Главный Валютный Стратег (Sovereign Strategist). Проанализируй динамику и СОСТОЯНИЕ ЛИКВИДНОСТИ валюты {$currency->code} ({$currency->name}).

ДИНАМИКА ПОСЛЕДНИХ СИНХРОНИЗАЦИЙ (MDK Ledger):
$transcript

ТЕКУЩЕЕ СОСТОЯНИЕ:
- Liquidity Stress Index (LSI): {$currency->liquidity_stress_index}
- Наблюдаемость (Observability): {$currency->observability_score}
- Доверие (Confidence): {$currency->confidence_score}
- Макс. объем (Capacity): \${$currency->max_executable_size}
- Проскальзывание (Slippage): {$currency->estimated_slippage}

ТВОИ ЗАДАЧИ:
1. Оцени РЕАЛЬНУЮ исполнимость ордера на \$10k на основе Capacity и Slippage.
2. Выяви тренды ликвидности (сужается ли стакан, растет ли задержка/стресс).
3. Дай краткий вердикт: "Deep Liquidity", "Thin Market", "Toxic/Slipping" или "Locked".

Отвечай на РУССКОМ языке, максимально технично.
EOT;

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(300)
                ->post('http://localhost:11434/api/generate', [
                    'model' => 'llama3',
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            return $response->successful() ? $response->json('response') : "Ошибка: " . $response->body();
        } catch (\Exception $e) {
            return "Ollama (Llama 3) недоступна для локального инсайта. Ошибка: " . $e->getMessage();
        }
    }

    /**
     * Анализ глобальных валютных рисков через Ollama
     */
    public function analyzeGlobalCurrencies(): string
    {
        set_time_limit(0);
        $transcript = $this->getGlobalTranscript();
        $stateScanner = app(SovereignStateScanner::class);
        $systemSnapshot = $stateScanner->getSystemSnapshot();
        
        $prompt = <<<EOT
Ты — Главный Суверенный Аналитик (Sovereign Risk Analyst). Проанализируй глобальный лог ликвидности.

ПРАВИЛА АНАЛИЗА:
1. Событие CURRENCY_BATCH_SYNC означает штатную синхронизацию всей системы. Не перечисляй валюты из него, если нет аномалий.
2. Фокусируйся на CORE валютах: RUB, USD, USDT, EUR, TRY, KZT.
3. Ищи критические отклонения (Slippage > 100bps, LSI > 0.5, Confidence < 0.3).

ПОСЛЕДНИЕ СОСТОЯНИЯ В ГЛОБАЛЬНОМ ЛЕДЖЕРЕ:
$transcript

АКТУАЛЬНОЕ СОСТОЯНИЕ СИСТЕМЫ (ПЕРЕМЕННЫЕ):
$systemSnapshot

ТВОИ ЗАДАЧИ:
1. Найди Bottlenecks (валюты с низким Capacity или высоким стрессом LSI).
2. Оцени системный риск. Где данные "шумят" или вызывают сомнения?
3. Дай прогноз по стабильности моста RUB/USDT/TRY.
4. Вынеси вердикт состоянию системы: "Operational", "Degraded" или "Critical".

Отвечай на РУССКОМ языке, в стиле разведсводки (Intelligence Report). Лаконично и по существу.
EOT;

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(300)
                ->post('http://localhost:11434/api/generate', [
                    'model' => 'llama3',
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            return $response->successful() ? $response->json('response') : "Ошибка: " . $response->body();
        } catch (\Exception $e) {
            return "Ollama недоступна: " . $e->getMessage();
        }
    }

    /**
     * Экспорт данных для будущего обучения (Fine-tuning)
     */
    public function exportForTraining(Shop $shop, string $idealResponse): string
    {
        $prompt = $this->buildAnalysisPrompt($shop);
        $data = [
            'instruction' => $prompt['content'],
            'input' => '',
            'output' => $idealResponse
        ];

        return json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
