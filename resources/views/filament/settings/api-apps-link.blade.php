<div class="flex items-center justify-between p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm max-w-2xl">
    <div class="flex items-center gap-4">
        <div class="flex-shrink-0 p-2 bg-primary-50 dark:bg-primary-950 rounded-lg">
            <x-heroicon-o-key class="w-6 h-6 text-primary-600 dark:text-primary-400" />
        </div>
        <div>
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">API Приложения</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Внешние ключи и интеграции</p>
        </div>
    </div>
    
    <a href="{{ \App\Filament\Resources\ApiApplicationResource::getUrl() }}" 
       class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-500 shadow-sm transition-all active:scale-95">
        <span>Перейти</span>
        <x-heroicon-m-chevron-right class="w-4 h-4" />
    </a>
</div>
