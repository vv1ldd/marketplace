@extends('layouts.app')

@section('title', 'Проверка и активация кода')

@section('content')
    <div
        class="w-full bg-zinc-800 border border-zinc-700 @if(!$is_frame) max-w-xl rounded-2xl @endif shadow-xl sm:p-8 p-4">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Введите полученный код</h2>
        <form class="space-y-5" method="POST" action="{{ route('check-code') }}">
            @csrf
            <div class="w-full">
                @if($is_frame)
                    <input hidden name="is_frame" value="1"/>
                @endif
                <input
                    id="code"
                    type="text"
                    placeholder="Код"
                    name="code"
                    value="{{ old('code') }}"
                    autofocus
                    required
                    class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                />
                @error('code')
                <p class="text-red-500 text-sm">{{ $message }}</p>
                @enderror
            </div>
            <button
                type="submit"
                class="cursor-pointer w-full bg-blue-600 hover:bg-blue-700 transition-colors duration-200 text-white font-semibold py-2 px-4 rounded-xl shadow-md focus:ring-2 focus:ring-blue-600 focus:outline-none"
                tabindex="5"
            >
                Далее
            </button>
        </form>
    </div>
@endsection
