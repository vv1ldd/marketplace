<x-filament-panels::page>
    <div class="space-y-6 max-w-5xl mx-auto">
        {{-- Список сообщений (Infolist) --}}
        <div class="w-full">
            {{ $this->infolist }}
        </div>

        {{-- Форма ответа --}}
        <div class="bg-white dark:bg-gray-900 p-8 rounded-xl border shadow-sm ring-1 ring-gray-900/5">
            <h3 class="text-lg font-bold mb-6 flex items-center space-x-2">
                <x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" class="w-5 h-5 text-primary-500" />
                <span>Ваше сообщение</span>
            </h3>

            <form wire:submit="createMessage" class="space-y-6">
                {{ $this->form }}

                <div class="flex justify-end pt-6 border-t border-gray-100 dark:border-gray-800 mt-4">
                    <x-filament::button size="lg" type="submit" color="primary" icon="heroicon-m-paper-airplane">
                        Отправить сообщение
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
