<?php

namespace App\Filament\Widgets;

use App\Services\Ai\LedgerAnalystService;
use Filament\Widgets\Widget;
use Livewire\Component;

class SovereignChatWidget extends Widget
{
    protected string $view = 'livewire.filament.widgets.sovereign-chat-widget';
    
    protected static ?int $sort = -10;

    protected int | string | array $columnSpan = 'full';

    public string $message = '';
    public array $chatHistory = [];
    public bool $isTyping = false;

    public function mount()
    {
        $this->chatHistory[] = [
            'role' => 'assistant',
            'content' => 'Приветствую! Я Sovereign AI. Я подключен к вашему детерминированному Ledger и готов помочь с аналитикой или управлением системой. О чем хотите узнать?',
            'time' => now()->format('H:i')
        ];
    }

    public function sendMessage()
    {
        if (empty(trim($this->message))) return;

        $userMsg = $this->message;
        $this->chatHistory[] = [
            'role' => 'user',
            'content' => $userMsg,
            'time' => now()->format('H:i')
        ];
        
        $this->message = '';
        $this->isTyping = true;

        // Trigger the AI response in the next request cycle
        $this->js('$wire.getAiResponse()');
    }

    // We use a separate method for async response to keep UI responsive
    public function getAiResponse(): void
    {
        set_time_limit(0); // Heavy sync + AI analysis can take time
        $lastUserMsg = collect($this->chatHistory)->where('role', 'user')->last()['content'] ?? '';
        
        $analyst = app(LedgerAnalystService::class);
        $transcript = $analyst->getTribunalTranscript(30);
        
        $commandContext = "";
        if (str_contains(strtolower($lastUserMsg), 'sync')) {
            $commandContext = "ПОДСКАЗКА: Пользователь интересуется синхронизацией. Упомяни, что это можно сделать через php artisan app:update-currency-rates.";
        }
        if (str_contains(strtolower($lastUserMsg), 'ledger') || str_contains(strtolower($lastUserMsg), 'леджер')) {
            $commandContext = "ПОДСКАЗКА: Пользователь спрашивает про леджер. Напомни, что леджер детерминирован и его можно сбросить через sovereign:ledger-reset.";
        }

        $prompt = <<<EOT
Ты — Sovereign AI Assistant, интерфейс управления системой.
Ты работаешь внутри админ-панели и имеешь доступ к Sovereign Ledger.

ПРАВИЛА ОБЩЕНИЯ:
1. Отвечай кратко и технично, как терминал управления.
2. Не повторяй лог событий дословно. Давай аналитическую выжимку.
3. Если событий много, выдели только CORE (RUB, USD, USDT, TRY) или ошибки.
4. Избегай фраз типа "Повторюсь" или "Как я уже сказал".
5. Твой стиль: Cyberpunk / System Terminal.

ПОСЛЕДНИЕ СОБЫТИЯ В СИСТЕМЕ:
$transcript

$commandContext

ЗАПРОС ПОЛЬЗОВАТЕЛЯ:
$lastUserMsg

ТВОЯ ЗАДАЧА: Дать интеллектуальный ответ на основе контекста системы.
EOT;

        $response = app(\App\Services\Llm\LlmProviderManager::class)->generateText($prompt, [
            'timeout' => 300,
            'temperature' => 0.2,
            'system' => 'You are a private marketplace operations terminal. Answer in Russian.',
        ]);
        $aiContent = $response->ok
            ? $response->text
            : "Я временно не могу связаться с LLM provider layer ({$response->provider}).";

        $this->chatHistory[] = [
            'role' => 'assistant',
            'content' => $aiContent,
            'time' => now()->format('H:i')
        ];
        
        $this->isTyping = false;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view($this->view);
    }
}
