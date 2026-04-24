<div class="flex items-center justify-between p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm max-w-2xl">
    <div>
        <h3 class="text-sm font-bold text-gray-900 dark:text-white">API Приложения</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">Управление внешними ключами и интеграциями</p>
    </div>
    
    <a href="{{ \App\Filament\Resources\ApiApplicationResource::getUrl() }}" 
       class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-500 shadow-sm transition-all active:scale-95">
        <span>Перейти к списку</span>
    </a>
</div>
