<div class="p-4">
    @if(!$report && !$loading)
        <div class="text-center py-6">
            <x-filament::button 
                wire:click="generateReport" 
                icon="heroicon-o-cpu-chip" 
                color="primary"
                size="lg"
            >
                Запустить AI-Анализ (Llama 3)
            </x-filament::button>
            <p class="mt-2 text-xs text-gray-500">
                ИИ проанализирует последние 20 записей в Ledger для выявления аномалий.
            </p>
        </div>
    @endif

    @if($loading)
        <div class="space-y-4 animate-pulse">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
            <div class="h-20 bg-gray-100 dark:bg-gray-800 rounded w-full"></div>
            <div class="text-center text-sm text-primary-500 font-medium">
                Sovereign AI Strategy Engine is calculating...
            </div>
        </div>
    @endif

    @if($report)
        <div class="bg-gray-900 text-green-400 p-6 rounded-2xl font-mono text-sm shadow-2xl border border-gray-800 leading-relaxed overflow-auto max-h-[400px]">
            <div class="flex items-center space-x-2 mb-4 border-b border-gray-800 pb-2">
                <x-heroicon-s-command-line class="w-4 h-4" />
                <span class="text-xs uppercase tracking-widest text-gray-500">MDK AI Strategic Insight</span>
            </div>
            {!! nl2br(e($report)) !!}
            <div class="mt-6 pt-4 border-t border-gray-800 text-[10px] text-gray-600 flex justify-between">
                <span>Model: Llama 3 (Ollama)</span>
                <span>Audit: Pass</span>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <x-filament::button 
                wire:click="generateReport" 
                icon="heroicon-o-arrow-path" 
                color="gray"
                size="xs"
            >
                Пересчитать
            </x-filament::button>
        </div>
    @endif
</div>
