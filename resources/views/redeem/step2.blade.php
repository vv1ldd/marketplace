@extends('layouts.app')

@section('title', 'Email для активации')

@section('content')
    <x-redeem.panel headline="Укажите email" icon="mail">
        <x-slot name="lead">
            На этот адрес придёт <span class="font-medium text-zinc-800 redeem-dark:text-zinc-300">код подтверждения</span>, а после выдачи —
            <span class="font-medium text-zinc-800 redeem-dark:text-zinc-300">код продукта</span> и ссылка на страницу с ним.
        </x-slot>
        <x-slot name="sublead">
            Используйте почту, к которой у вас есть доступ: без подтверждения мы не сможем завершить активацию.
        </x-slot>

        <form class="space-y-5" method="POST" action="{{ route('redeem.email.submit') }}">
            @csrf
            <input type="hidden" name="intent" value="{{ request()->query('intent') ?? session('order_item_info.intent_token') ?? '' }}" />
            <div class="w-full">
                @if (session('is_frame'))
                    <input hidden name="is_frame" value="1" />
                @endif
                <label class="mb-2 block text-sm font-medium text-zinc-700 redeem-dark:text-zinc-300" for="email">Почтовый адрес<span
                        class="text-red-500 redeem-dark:text-red-400">*</span></label>
                <input type="email" id="email" placeholder="you@example.com" name="email"
                    value="{{ old('email') }}" autofocus required
                    class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none" />
                <p class="mt-2 text-[11px] leading-relaxed text-zinc-500 redeem-dark:text-zinc-500">
                    Email сохраняется в заказе и используется для регистрации в системе и доставки товара по умолчанию.
                </p>
                @error('email')
                    <p class="mt-2 text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                class="w-full cursor-pointer rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white shadow-lg shadow-blue-900/15 transition-colors hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white redeem-dark:shadow-blue-900/20 redeem-dark:focus:ring-offset-zinc-900"
                tabindex="5">
                Далее
            </button>
        </form>
    </x-redeem.panel>
@endsection
