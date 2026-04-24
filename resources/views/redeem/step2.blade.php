@extends('layouts.app')

@section('title', 'Укажите ваш email-адрес')

@section('content')
    <div
        class="w-full bg-zinc-800 border border-zinc-700 @if(!session('is_frame')) max-w-xl rounded-2xl @endif shadow-xl sm:p-8 p-4">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Укажите ваш email-адрес</h2>
        <form class="space-y-5" method="POST" action="{{ route('redeem.email.submit') }}">
            @csrf
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