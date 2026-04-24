<div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div class="p-2 bg-primary-100 dark:bg-primary-900 rounded-lg text-primary-600 dark:text-primary-400">
            <x-heroicon-o-key class="w-6 h-6" />
        </div>
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">API Приложения</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Управление внешними API ключами и интеграциями</p>
        </div>
    </div>
    
    <a href="{{ \App\Filament\Resources\ApiApplicationResource::getUrl() }}" 
       class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-500 transition-colors">
        <span>Перейти к списку</span>
        <x-heroicon-m-chevron-right class="w-4 h-4" />
    </a>
</div>
