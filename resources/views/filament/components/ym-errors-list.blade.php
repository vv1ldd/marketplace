<div class="space-y-4">
    @if(!empty($errors))
        @foreach($errors as $error)
            <div class="flex items-start gap-3 p-3 bg-danger-50 border border-danger-200 rounded-lg dark:bg-danger-900/20 dark:border-danger-800">
                <div class="flex-shrink-0">
                    <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    @if(isset($error['code']))
                        <div class="text-sm font-bold text-danger-900 dark:text-danger-100">
                            {{ $error['code'] }}
                        </div>
                    @endif
                    <div class="text-sm text-danger-700 dark:text-danger-300">
                        {{ $error['message'] ?? 'Неизвестная ошибка' }}
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="text-sm text-gray-500 italic">
            Ошибок не обнаружено.
        </div>
    @endif
</div>
