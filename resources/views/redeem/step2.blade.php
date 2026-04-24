@extends('layouts.app')

@section('title', 'Укажите ваш email-адрес')

@section('content')
    <div
        class="w-full bg-zinc-800 border border-zinc-700 @if(!session('is_frame')) max-w-xl rounded-2xl @endif shadow-xl sm:p-8 p-4">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Укажите ваш email-адрес</h2>
        <form class="space-y-5" method="POST" action="{{ route('redeem.email.submit') }}">
            @csrf
            <div class="w-full">
                <label class="block text-sm text-zinc-300 mb-2 font-medium">Куда отправить код подтверждения?<span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <!-- Email Option -->
                    <label class="relative flex items-center p-3 rounded-xl border border-zinc-600 bg-zinc-700/50 cursor-pointer hover:bg-zinc-700 transition-colors">
                        <input type="radio" name="method" value="email" checked class="w-4 h-4 text-blue-600 bg-zinc-800 border-zinc-600 focus:ring-blue-600 focus:ring-offset-zinc-800">
                        <div class="ml-3 flex flex-col">
                            <span class="text-sm text-white">Email (почта)</span>
                            <span class="text-xs text-zinc-400">Основной канал</span>
                        </div>
                    </label>

                    @if(isset($order) && $order->chat_id)
                    <!-- YM Chat Option -->
                    <label class="relative flex items-center p-3 rounded-xl border border-blue-600/50 bg-blue-600/10 cursor-pointer hover:bg-blue-600/20 transition-colors">
                        <input type="radio" name="method" value="chat" class="w-4 h-4 text-blue-600 bg-zinc-800 border-zinc-600 focus:ring-blue-600 focus:ring-offset-zinc-800">
                        <div class="ml-3 flex flex-col">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-white font-medium">Чат Яндекс.Маркета</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-500/20 text-green-400 font-bold uppercase tracking-wider">Бесплатно</span>
                            </div>
                            <span class="text-xs text-blue-400">Мгновенная доставка</span>
                        </div>
                    </label>
                    @endif

                    <!-- SMS Option (Placeholder) -->
                    <label class="relative flex items-center p-3 rounded-xl border border-zinc-700 bg-zinc-800/50 cursor-not-allowed opacity-60">
                        <input type="radio" disabled class="w-4 h-4 text-zinc-600 bg-zinc-900 border-zinc-700">
                        <div class="ml-3 flex flex-col">
                            <span class="text-sm text-zinc-500">СМС код</span>
                            <span class="text-xs text-orange-500/70">Скоро</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="w-full">
                @if(session('is_frame'))
                    <input hidden name="is_frame" value="1" />
                @endif
                <label class="block text-sm text-zinc-300 mb-1" for="email">Ваш почтовый адрес<span
                        class="text-red-500">*</span></label>
                <input type="email" placeholder="ivan@mail.ru" name="email" value="{{ old('email') }}" autofocus required
                    class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2" />
                <p class="text-[11px] text-zinc-500 mt-2 leading-tight">
                    * Email используется для регистрации в системе и на него будет отправлен купленный товар по умолчанию.
                </p>
                @error('email')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                class="cursor-pointer w-full bg-blue-600 hover:bg-blue-700 transition-colors duration-200 text-white font-semibold py-2 px-4 rounded-xl shadow-md focus:ring-2 focus:ring-blue-600 focus:outline-none"
                tabindex="5">
                Далее
            </button>
        </form>
    </div>
@endsection