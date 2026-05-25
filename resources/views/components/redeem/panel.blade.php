@props([
    'headline',
    'icon' => 'ticket',
    'bodyClass' => 'p-6 sm:p-8',
])

@php
    $inFrame = (bool) session('is_frame');
@endphp

<div class="redeem-panel-shell {{ $inFrame ? 'w-full' : 'w-full max-w-2xl mx-auto px-4 py-6 sm:py-10' }}">
    <div
        class="redeem-panel overflow-hidden border border-zinc-200/90 bg-white/95 shadow-xl shadow-zinc-900/5 backdrop-blur-xl redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-900/50 redeem-dark:shadow-2xl redeem-dark:shadow-black/40 {{ $inFrame ? '' : 'rounded-3xl' }}">
        <div
            class="redeem-panel-header border-b border-zinc-200/80 bg-gradient-to-r from-blue-100/90 to-indigo-100/70 px-5 pb-6 pt-8 text-center redeem-dark:border-zinc-700/30 redeem-dark:from-blue-600/20 redeem-dark:to-indigo-600/20">
            @if ($icon !== 'none')
                <div
                    class="redeem-panel-icon mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200/80 redeem-dark:bg-indigo-600/25 redeem-dark:text-indigo-200 redeem-dark:ring-indigo-400/25"
                    aria-hidden="true">
                    @switch($icon)
                        @case('mail')
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            @break
                        @case('shield-check')
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            @break
                        @case('clock')
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            @break
                        @default
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                    @endswitch
                </div>
            @endif
            <h1 class="redeem-panel-title text-2xl font-extrabold tracking-tight text-zinc-900 sm:text-3xl redeem-dark:text-white">
                {{ $headline }}</h1>
            @isset($lead)
                <div
                    class="redeem-panel-lead mx-auto mt-3 max-w-xl text-sm leading-relaxed text-zinc-600 redeem-dark:text-zinc-400">
                    {{ $lead }}</div>
            @endisset
            @isset($sublead)
                <p
                    class="redeem-panel-sublead mx-auto mt-3 max-w-lg text-xs leading-relaxed text-zinc-500 redeem-dark:text-zinc-500">
                    {{ $sublead }}</p>
            @endisset
        </div>
        <div class="redeem-panel-body {{ $bodyClass }}">
            {{ $slot }}
        </div>
    </div>
</div>
