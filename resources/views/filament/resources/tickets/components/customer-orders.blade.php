@php
    $user = $getRecord()->user;
    if ($user) {
        $orders = \App\Models\Order\Order::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
    } else {
        $orders = collect();
    }
@endphp

<div class="mt-4 space-y-3">
    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200">Последние заказы:</h3>
    
    @if($orders->isEmpty())
        <p class="text-xs text-gray-500">У клиента пока нет заказов.</p>
    @else
        <div class="flex flex-col gap-2">
            @foreach($orders as $order)
                @php
                    $isProblem = $order->is_problem;
                    $isActive = !in_array($order->progress_id, [4, 5]) && $order->status !== 'CANCELLED'; // 4 - Обработан, 5 - Отменен
                    
                    $bgColor = 'bg-gray-50 dark:bg-gray-800';
                    $borderColor = 'border-gray-200 dark:border-gray-700';
                    
                    if ($isProblem) {
                        $bgColor = 'bg-danger-50 dark:bg-danger-500/10';
                        $borderColor = 'border-danger-200 dark:border-danger-500/20';
                    } elseif ($isActive) {
                        $bgColor = 'bg-warning-50 dark:bg-warning-500/10';
                        $borderColor = 'border-warning-200 dark:border-warning-500/20';
                    }
                @endphp
                
                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order->id]) }}" 
                   target="_blank"
                   class="block p-3 border rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-700/50 {{ $bgColor }} {{ $borderColor }}">
                    <div class="flex justify-between items-start mb-1">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            Заказ #{{ $order->id }}
                        </span>
                        @if($isProblem)
                            <span class="inline-flex items-center rounded-md bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 ring-1 ring-inset ring-danger-600/10 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20">Проблема</span>
                        @elseif($isActive)
                            <span class="inline-flex items-center rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/10 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20">Активен</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/10 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20">Завершен</span>
                        @endif
                    </div>
                    
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex flex-col gap-0.5">
                        <span>{{ $order->created_at->format('d.m.Y H:i') }}</span>
                        <span class="truncate">Магазин: {{ $order->shop?->name ?? '—' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
        
        <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $user->id]) }}" 
           target="_blank"
           class="inline-block mt-2 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
            Перейти в профиль клиента &rarr;
        </a>
    @endif
</div>
