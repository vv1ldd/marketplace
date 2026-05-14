<div class="flex flex-col space-y-4">
    @php
        $messages = $getRecord()->messages()->orderBy('created_at', 'asc')->get();
    @endphp

    @if($messages->isEmpty())
        <div class="flex flex-col items-center justify-center p-8 text-gray-500 bg-white dark:bg-gray-900 rounded-xl border border-dashed">
            <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="w-12 h-12 mb-2 opacity-20" />
            <p class="text-sm">История переписки пуста</p>
        </div>
    @else
        <div class="flex flex-col space-y-0 max-h-[700px] overflow-y-auto px-2 custom-scrollbar">
            @foreach($messages as $message)
                @include('filament.resources.tickets.components.message-item', [
                    'message' => $message,
                    'isLast' => $loop->last
                ])
            @endforeach
        </div>
    @endif
</div>
