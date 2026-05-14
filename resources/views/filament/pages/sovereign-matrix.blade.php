<x-filament-panels::page>
    <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-sm text-left divide-y divide-gray-200 dark:divide-white/5">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-4 font-bold text-gray-950 dark:text-white bg-gray-200 dark:bg-gray-900 sticky left-0 z-10 shadow-[1px_0_0_0_#d1d5db] dark:shadow-[1px_0_0_0_#1f2937] border-b border-gray-300 dark:border-gray-700" style="min-width: 120px;">
                            Base ↓ \ Quote →
                        </th>
                        @foreach($currencies as $col)
                            <th class="px-6 py-4 font-semibold text-gray-600 dark:text-gray-300 text-right border-b border-gray-200 dark:border-white/10" style="min-width: 120px; white-space: nowrap;">
                                {{ $col }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach($currencies as $row)
                        <tr class="transition duration-150 hover:bg-primary-50 dark:hover:bg-primary-500/10">
                            <th class="px-6 py-4 font-bold text-gray-950 dark:text-white bg-gray-50 dark:bg-gray-800/80 sticky left-0 z-10 shadow-[1px_0_0_0_#e5e7eb] dark:shadow-[1px_0_0_0_#374151] group-hover:bg-primary-100 dark:group-hover:bg-primary-900/50" style="min-width: 120px;">
                                {{ $row }}
                            </th>
                            @foreach($currencies as $col)
                                @php
                                    $val = $matrix[$row][$col] ?? 0;
                                    $isBase = $row === $col;
                                    
                                    // Heatmap visual logic
                                    $bgClass = '';
                                    $textClass = 'text-gray-700 dark:text-gray-300';
                                    
                                    if ($isBase) {
                                        $bgClass = 'bg-gray-100 dark:bg-gray-800/50';
                                        $textClass = 'text-gray-400 dark:text-gray-600';
                                    } elseif ($val > 100) {
                                        $textClass = 'text-danger-600 dark:text-danger-400 font-semibold';
                                    } elseif ($val < 1) {
                                        $textClass = 'text-success-600 dark:text-success-400 font-semibold';
                                    }
                                @endphp
                                <td class="px-6 py-4 text-right font-mono border-l border-gray-100 dark:border-white/5 {{ $bgClass }} {{ $textClass }}" style="min-width: 120px; white-space: nowrap;">
                                    @if($isBase)
                                        <span class="opacity-50">—</span>
                                    @else
                                        {{ $val < 0.01 ? number_format($val, 6) : number_format($val, 4) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 text-sm text-gray-500 dark:text-gray-400 flex items-start gap-2">
        <x-heroicon-o-information-circle style="width: 24px; height: 24px; flex-shrink: 0;" class="text-primary-500" />
        <div>
            <p class="font-medium text-gray-950 dark:text-white mb-1">Как читать эту матрицу:</p>
            <p>Значения в ячейках показывают стоимость 1 единицы валюты из строки (Base) в валюте из столбца (Quote).</p>
            <p class="mt-1">Например, пересечение строки <strong>USD</strong> и столбца <strong>RUB</strong> показывает стоимость 1 Доллара в Рублях. 
            Если вы переведете USD через цепочку (например, USDT -> BYBIT -> RUB), вы получите именно этот курс.</p>
        </div>
    </div>
</x-filament-panels::page>
