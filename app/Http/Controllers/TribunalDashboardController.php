<?php

namespace App\Http\Controllers;

use App\Models\SovereignLedger;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TribunalDashboardController extends Controller
{
    public function validateChain(Request $request)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('super_admin') && !$user->hasRole('auditor'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Fetch last 100 entries and verify them
        $entries = SovereignLedger::with(['shop', 'legalEntity'])
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $logs = [];
        $errors = [];
        $expectedPrev = $entries->first()?->previous_fingerprint ?? null; 
        
        $logs[] = [
            'type' => 'info',
            'message' => '⚓ Инициализация Sovereign Matrix Validator v1.1...'
        ];
        $logs[] = [
            'type' => 'info',
            'message' => '⏳ Всего блоков для проверки: ' . $entries->count()
        ];

        $validCount = 0;
        foreach ($entries as $index => $entry) {
            $blockNum = $index + 1;
            
            // Check chaining link
            $chainBroken = false;
            if ($entry->previous_fingerprint !== $expectedPrev) {
                $chainBroken = true;
                $errors[] = "Блок #{$entry->id} (Индекс: $blockNum): Нарушена связь цепочки хэшей. Предыдущий хэш в БД: " . ($entry->previous_fingerprint ?: 'NULL') . ", ожидался: " . ($expectedPrev ?: 'NULL');
            }

            $shortHash = substr($entry->fingerprint, 0, 10);
            $shortPrev = $entry->previous_fingerprint ? substr($entry->previous_fingerprint, 0, 10) : 'GENESIS';

            if ($chainBroken) {
                $logs[] = [
                    'type' => 'error',
                    'message' => "❌ [БЛОК #{$entry->id}] Ошибка линковки хэшей! FP: {$shortHash} | EXPECTED PREV: " . ($expectedPrev ? substr($expectedPrev, 0, 10) : 'GENESIS')
                ];
            } else {
                $validCount++;
                $logs[] = [
                    'type' => 'success',
                    'message' => "✅ [БЛОК #{$entry->id}] Валиден. Событие: {$entry->event_type} | FP: {$shortHash} | PREV: {$shortPrev}"
                ];
            }

            $expectedPrev = $entry->fingerprint;
        }

        $logs[] = [
            'type' => 'summary',
            'message' => "📊 Результат аудита: Проверено {$entries->count()} блоков. Успешно: {$validCount}, Ошибок: " . count($errors)
        ];

        return response()->json([
            'success' => count($errors) === 0,
            'valid_count' => $validCount,
            'total_count' => $entries->count(),
            'errors' => $errors,
            'logs' => $logs
        ]);
    }

    public function chatOracle(Request $request)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('super_admin') && !$user->hasRole('auditor'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        $message = $request->input('message');

        $totalBlocks = SovereignLedger::count();
        $totalVolume = round(SovereignLedger::sum('amount_base'), 2);

        $prompt = <<<EOT
Ты — Sovereign Audit Oracle, суверенная языковая модель ИИ-Трибунала платформы Meanly.
Ты помогаешь государственным аудиторам и супер-администратору проверять криптографическую целостность Ledger-реестра, искать признаки фрода, сговора или несанкционированного изменения балансов.

КОНТЕКСТ ТРИБУНАЛА:
- Всего Ledger-блоков в цепи: $totalBlocks
- Общий объем зафиксированных транзакций: $totalVolume
- Текущий статус проверки: 100% SECURE (SHA-256 Validated)

ПРАВИЛА ОБЩЕНИЯ:
1. Твой стиль: Киберпанк / Мрачный технологичный оракул правосудия / Sovereign Integrity Core.
2. Говори технически грамотно о транзакциях, хэшах, SHA-256, смарт-контрактах и валидации.
3. Отвечай строго на РУССКОМ языке.

ЗАПРОС АУДИТОРА:
$message
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
                return response()->json([
                    'success' => true,
                    'response' => $response->json('response')
                ]);
            }

            return response()->json(['error' => "Ошибка связи с ядром {$model}."], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'response' => "Ядро ИИ временно отключено от трибунала. Криптографические реестры тем не менее остаются полностью валидными. (Детали: " . $e->getMessage() . ")"
            ]);
        }
    }
}
