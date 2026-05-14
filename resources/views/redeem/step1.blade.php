@extends('layouts.app')

@section('title', 'Активация ваучера')

@section('content')
    <x-redeem.panel headline="Активация ваучера" icon="ticket">
        <x-slot name="lead">
            Вставьте <span class="font-medium text-zinc-800 redeem-dark:text-zinc-300">код с карты или из письма о покупке</span>.
            Затем укажете email и подтвердите его — откроется страница с <span class="font-medium text-zinc-800 redeem-dark:text-zinc-300">кодом
                для сервиса</span> (пополнение баланса и т.п.).
        </x-slot>
        <x-slot name="sublead">
            Если код у провайдера готовится несколько секунд, страница обновится сама; копию с инструкцией мы
            также отправим на вашу почту — как на шаге ожидания после активации.
        </x-slot>

        @if (!empty($redeemShop?->name))
            <p class="mb-5 text-center text-xs text-zinc-500">
                Оформление через магазин:
                <span class="font-semibold text-zinc-800 redeem-dark:text-zinc-200">{{ $redeemShop->name }}</span>
            </p>
        @endif

        <form class="space-y-5" method="POST" action="{{ route('redeem.code.submit') }}">
            @csrf
            <div class="w-full">
                @if (session('is_frame'))
                    <input hidden name="is_frame" value="1" />
                @endif
                <label class="mb-2 block text-sm font-medium text-zinc-700 redeem-dark:text-zinc-300" for="code">Код ваучера<span
                        class="text-red-500 redeem-dark:text-red-400">*</span></label>
                <input id="code" type="text" maxlength="64" spellcheck="false" autocomplete="off"
                    placeholder="Например: PREFIX-XXXX-XXXX-XXXX" name="code" value="{{ old('code', request('code')) }}" autofocus
                    required
                    class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 font-mono text-sm tracking-wide text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:focus:border-blue-500/50 redeem-dark:shadow-none" />
                @error('code')
                    <p class="mt-2 text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                @enderror
                @if (session('redeem_support'))
                    @php
                        $rs = session('redeem_support');
                        $tg = $rs['support_telegram'] ?? null;
                        $tgHref = null;
                        if (filled($tg)) {
                            $tgTrim = trim((string) $tg);
                            if (str_starts_with($tgTrim, 'http://') || str_starts_with($tgTrim, 'https://')) {
                                $tgHref = $tgTrim;
                            } elseif (str_starts_with($tgTrim, '@')) {
                                $tgHref = 'https://t.me/' . ltrim($tgTrim, '@');
                            } else {
                                $tgHref = 'https://t.me/' . ltrim($tgTrim, '@');
                            }
                        }
                    @endphp
                    <div
                        class="mt-4 rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-sm redeem-dark:border-amber-500/40 redeem-dark:bg-amber-950/40 redeem-dark:text-amber-100">
                        <p class="font-semibold text-amber-900 redeem-dark:text-amber-50">Поддержка магазина
                            @if (!empty($rs['shop_name']))
                                <span class="font-normal opacity-90">({{ $rs['shop_name'] }})</span>
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-amber-800/90 redeem-dark:text-amber-200/90">Если нужна помощь с
                            заказом или кодом, напишите нам:</p>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @if (filled($rs['support_email'] ?? null))
                                <li>
                                    <span class="text-amber-800/80 redeem-dark:text-amber-300/80">Email:</span>
                                    <a class="font-medium text-amber-900 underline decoration-amber-600/50 hover:decoration-amber-800 redeem-dark:text-amber-100"
                                        href="mailto:{{ $rs['support_email'] }}">{{ $rs['support_email'] }}</a>
                                </li>
                            @endif
                            @if (filled($tg))
                                <li>
                                    <span class="text-amber-800/80 redeem-dark:text-amber-300/80">Telegram:</span>
                                    <a class="font-medium text-amber-900 underline decoration-amber-600/50 hover:decoration-amber-800 redeem-dark:text-amber-100"
                                        href="{{ $tgHref }}" rel="noopener noreferrer" target="_blank">{{ $tg }}</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif
            </div>
            <button type="submit"
                class="w-full cursor-pointer rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white shadow-lg shadow-blue-900/15 transition-colors hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white redeem-dark:shadow-blue-900/20 redeem-dark:focus:ring-offset-zinc-900"
                tabindex="5">
                Далее
            </button>
        </form>

        <p class="mx-auto mt-6 max-w-md text-center text-[11px] leading-relaxed text-zinc-500 redeem-dark:text-zinc-600">
            Уже активировали, но не видите код продукта? Проверьте почту или откройте ссылку
            «Открыть страницу с кодом» из письма — можно вернуться сюда и ввести тот же ваучерный код ещё раз.
        </p>
    </x-redeem.panel>
@endsection

@section('scripts')
    <script>
        const input = document.getElementById('code');
        const staticPrefix = @json($prefix);

        function ensureTrailingDash(p) {
            const c = String(p || '').replace(/[^A-Z0-9]/g, '').toUpperCase();
            if (!c.length) {
                return '';
            }
            return c.endsWith('-') ? c : (c + '-');
        }

        /**
         * Префикс с сервера — реальный voucher_prefix магазина (?shop= / домен), может быть пустым.
         * Полный код: до первого «-» — префикс магазина, далее три блока по 4 символа.
         */
        /**
         * Форматирование кода. 
         * Поддерживает как старые короткие коды, так и новые длинные SVC-ваучеры.
         */
        function formatCode(raw) {
            let s = String(raw || '').toUpperCase().replace(/[^A-Z0-9-]/g, '');
            if (!s.length) return '';

            // Если это SVC код, мы просто нормализуем его (A-Z, 0-9 и дефисы) и не ограничиваем длину так строго
            if (s.startsWith('SVC-')) {
                return s.substring(0, 64); 
            }

            const known = String(staticPrefix || '').trim();
            let usePrefix = known.length ?
                ensureTrailingDash(known.replace(/[^A-Z0-9-]/g, '').toUpperCase()) :
                '';
            let body = s.replace(/[^A-Z0-9]/g, '');

            const firstHyphen = s.indexOf('-');
            if (firstHyphen !== -1) {
                const head = s.slice(0, firstHyphen).replace(/[^A-Z0-9]/g, '').toUpperCase();
                const tail = s.slice(firstHyphen + 1).replace(/[^A-Z0-9]/g, '').toUpperCase();
                if (head.length && tail.length) {
                    usePrefix = ensureTrailingDash(head);
                    body = tail;
                }
            }

            if (!usePrefix) return s.substring(0, 64);

            const prefixLetters = usePrefix.replace(/[^A-Z0-9]/g, '').toUpperCase();
            if (prefixLetters.length && body.startsWith(prefixLetters)) {
                body = body.slice(prefixLetters.length);
            }

            const parts = [];
            // Увеличиваем лимит блоков для поддержки длинных кодов
            for (let i = 0; i < 40 && i < body.length; i += 4) {
                parts.push(body.substring(i, i + 4));
            }

            return usePrefix + parts.join('-');
        }

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            input.value = formatCode(pasted);
        });

        input.addEventListener('input', () => {
            const caretPos = input.selectionStart;
            const before = input.value;

            const next = formatCode(before);
            input.value = next;

            const diff = next.length - before.length;
            let pos = caretPos + diff;
            if (pos < 0) pos = 0;
            if (pos > next.length) pos = next.length;
            input.selectionEnd = input.selectionStart = pos;
        });
    </script>
@endsection
