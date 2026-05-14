@props([
    'theme' => 'dark',
])

@php
    $lightUrl = request()->fullUrlWithQuery(['theme' => 'light']);
    $darkUrl = request()->fullUrlWithQuery(['theme' => 'dark']);
@endphp

<div
    class="fixed bottom-4 right-4 z-[100] flex gap-0.5 rounded-2xl border border-zinc-200/90 bg-white/95 p-1 shadow-lg shadow-zinc-900/10 backdrop-blur-md redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-900/95 redeem-dark:shadow-black/40"
    role="group"
    aria-label="Тема оформления">
    <a href="{{ $lightUrl }}"
        class="rounded-xl px-3 py-2 text-xs font-semibold transition-colors {{ $theme === 'light' ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 redeem-dark:text-zinc-400 redeem-dark:hover:bg-zinc-800 redeem-dark:hover:text-white' }}">
        Светлая
    </a>
    <a href="{{ $darkUrl }}"
        class="rounded-xl px-3 py-2 text-xs font-semibold transition-colors {{ $theme === 'dark' ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 redeem-dark:text-zinc-400 redeem-dark:hover:bg-zinc-800 redeem-dark:hover:text-white' }}">
        Тёмная
    </a>
</div>
