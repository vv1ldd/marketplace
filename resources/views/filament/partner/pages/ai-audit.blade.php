<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-1 flex flex-col gap-y-6">
                <x-filament::section>
                    <x-slot name="heading">
                        Статус интеллектуального анализа
                    </x-slot>

                    <div class="flex items-center space-x-4">
                        <div @class([
                            'w-3 h-3 rounded-full animate-pulse',
                            'bg-success-500' => !$isAnalyzing,
                            'bg-warning-500' => $isAnalyzing,
                        ])></div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            {{ $isAnalyzing ? 'ИИ анализирует цепочку событий...' : 'Система готова к аудиту' }}
                        </span>
                    </div>

                    <p class="mt-4 text-sm text-gray-500 italic">
                        ИИ просматривает ваш Sovereign Ledger, проверяет целостность хешей и ищет аномалии в финансовых потоках.
                    </p>
                </x-filament::section>

                @if($auditResult)
                    <x-filament::section icon="heroicon-o-chat-bubble-bottom-center-text" icon-color="primary">
                        <x-slot name="heading">
                            Результат аудита
                        </x-slot>

                        <div class="prose dark:prose-invert max-w-none text-gray-800 dark:text-gray-200 text-sm leading-relaxed">
                            {!! nl2br(e($auditResult)) !!}
                        </div>
                    </x-filament::section>
                @endif
            </div>

            <div class="xl:col-span-2">
                @livewire(\App\Filament\Widgets\SovereignChatWidget::class)
            </div>
        </div>
    </div>
</x-filament-panels::page>
