<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Застревания выдачи
        </x-slot>

        <x-slot name="description">
            Оплаченные заказы, где покупатель не дошел до открытия/стирания кода, или provider выдача зависла.
        </x-slot>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Всего stuck</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $total }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Критично</div>
                <div class="mt-1 text-2xl font-bold text-danger-600">{{ $failedCount }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Предупреждения</div>
                <div class="mt-1 text-2xl font-bold text-warning-600">{{ $warningCount }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Самое старое</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $oldestMinutes }} мин</div>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3">Заказ</th>
                        <th class="px-4 py-3">Причина</th>
                        <th class="px-4 py-3">Статус</th>
                        <th class="px-4 py-3">Возраст</th>
                        <th class="px-4 py-3">Сумма</th>
                        <th class="px-4 py-3">Действие</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-950 dark:text-white">{{ $row['order_id'] }}</div>
                                <div class="text-xs text-gray-500">#{{ $row['id'] }} · {{ $row['shop'] }} · {{ $row['created_at'] }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' => $row['severity'] === 'danger',
                                    'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300' => $row['severity'] !== 'danger',
                                ])>
                                    {{ $row['reason'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                <div>{{ $row['status'] }}</div>
                                <div>safe: {{ $row['safe_status'] }}</div>
                                <div>delivery: {{ $row['delivery_status'] }}</div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ $row['age_minutes'] }} мин</td>
                            <td class="px-4 py-3">{{ $row['total'] }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ $row['safe_url'] }}" target="_blank" class="text-primary-600 underline underline-offset-4">
                                    Открыть выдачу
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                Сейчас застрявших выдач не найдено.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
