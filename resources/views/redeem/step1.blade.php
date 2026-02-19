@extends('layouts.app')

@section('title', 'Шаг 1. Введите полученный код')

@section('content')
    <div
        class="w-full bg-zinc-800 border border-zinc-700 @if(!session('is_frame')) max-w-xl rounded-2xl @endif shadow-xl sm:p-8 p-4">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Шаг 1. Введите полученный код</h2>
        <form class="space-y-5" method="POST" action="{{ route('redeem.step1.submit') }}">
            @csrf
            <div class="w-full">
                @if(session('is_frame'))
                    <input hidden name="is_frame" value="1" />
                @endif
                <label class="block text-sm text-zinc-300 mb-1" for="first_name">В формате W1C-XXXX-XXXX-XXXX<span
                        class="text-red-500">*</span></label>
                <input id="code" type="text" maxlength="18" minlength="18" spellcheck="false" autocomplete="off"
                    pattern="^W1C-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$" placeholder="Код" name="code"
                    value="{{ old('code') }}" autofocus required
                    class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2" />
                @error('code')
                    <p class="text-red-500 text-sm">{{ $message }}</p>
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

@section('scripts')
<script>
    const input = document.getElementById('code');
    const staticPrefix = 'W1C-';

    function formatCode(raw) {
        raw = raw.toUpperCase().replace(/[^A-Z0-9]/g, '');

        // убираем префикс, если случайно вставлен
        raw = raw.replace(/^W1C/i, '');

        let parts = [];
        for (let i = 0; i < 12 && i < raw.length; i += 4) {
            parts.push(raw.substring(i, i + 4));
        }

        return staticPrefix + parts.join('-');
    }

    input.addEventListener('focus', () => {
        if (!input.value.startsWith(staticPrefix)) {
            input.value = staticPrefix;
        }
    });

    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        input.value = formatCode(pasted);
    });

    input.addEventListener('input', () => {
        const caretPos = input.selectionStart;
        const before = input.value;

        input.value = formatCode(before);

        // Мягкий возврат курсора
        const diff = input.value.length - before.length;
        input.selectionEnd = input.selectionStart = caretPos + diff;
    });
</script>
@endSection