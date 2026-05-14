@props([
    'message',
    'isLast' => false,
])

@php
    $isAdmin = $message->is_admin_reply;
    $author = $isAdmin ? 'Администратор (Поддержка)' : ($message->seller?->name ?? 'Клиент');
    
    // Стили заголовка как на скриншоте (синий фон для поддержки, серый для клиента)
    $headerBg = $isAdmin ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-800/50';
    $avatarBg = $isAdmin ? 'bg-blue-200 text-blue-700 dark:bg-blue-800 dark:text-blue-200' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-200';
    $actionText = $isAdmin ? 'ответил(а)' : 'добавил(а) ответ';
@endphp

<div class="flex flex-col w-full border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden mb-6 shadow-sm">
    {{-- Шапка сообщения --}}
    <div class="flex items-center gap-4 px-5 py-4 {{ $headerBg }} border-b border-gray-200 dark:border-gray-800">
        {{-- Квадратная/скругленная аватарка --}}
        <div class="flex-shrink-0 w-10 h-10 rounded-md flex items-center justify-center font-bold text-sm {{ $avatarBg }}">
            {{ mb_substr($author, 0, 1) }}
        </div>
        
        {{-- Информация --}}
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-2">
            <span class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                {{ $author }}
            </span>
            <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ $actionText }}, {{ $message->created_at->diffForHumans() }}
            </span>
        </div>
    </div>

    {{-- Тело сообщения --}}
    <div class="px-5 py-5 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 leading-relaxed prose prose-sm dark:prose-invert max-w-none">
        {!! nl2br(e($message->message)) !!}
    </div>
</div>
