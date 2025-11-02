<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order\Order;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;


    public function getTabs(): array
    {
        $super_admin = auth()->user()->hasRole('super_admin');

        if ($super_admin) {
            return [
                'all' => Tab::make('Все')->badge(fn() => Order::count()),
                'Не обработаны' => Tab::make()
                    ->badge(fn() => Order::where('progress_id', '<>', 4)->count())
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('progress_id', '<>', 4)->where('is_problem', false)),
                'Проблемные' => Tab::make()
                    ->badge(fn() => Order::where('is_problem', true)->count())
                    ->badgeColor('danger')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('is_problem', true))
            ];
        } else {
            return [];
        }
    }

    public function table(Table $table): Table
    {
        $user_id = auth()->user()->id;

        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');

        if ($is_executor) {
            return $table->modifyQueryUsing(fn($query) => $query->where('assigned_user_id', $user_id)->where('is_problem', false)->where('progress_id', '<>', 4));
        } else if ($is_support) {
            return $table->modifyQueryUsing(fn($query) => $query->where('assigned_user_id', $user_id)->where('is_problem', true)->where('progress_id', '<>', 4));
        } else {
            return $table;
        }
    }

    protected function getHeaderActions(): array
    {
        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');

        return [
            ...($is_executor || $is_support ? [
                Action::make('takeOrder')
                    ->label('Взять заказ')
//                    ->form(function () {
//                        return [
//                            TextInput::make('comment')
//                        ];
//                    })
                    ->action(function () use ($is_support, $is_executor) {

                        if ($is_executor) {

                            $check_limit = Order::checkLimit()->count();

                            if ($check_limit >= 1) {

                                Notification::make()
                                    ->warning()
                                    ->title('Завершите обработку уже взятых заказов, чтобы взять новый.')
                                    ->send();

                                return;
                            }

                            $order = Order::availableForExecutor()->first();
                        } else if ($is_support) {
                            $order = Order::availableForSupport()->first();
                        }

                        if (!$order) {
                            Notification::make()
                                ->warning()
                                ->title('Нет доступных заказов')
                                ->send();

                            return;
                        }

                        $order->update([
                            'assigned_user_id' => auth()->id(),
                            'assigned_at' => now()
                        ]);

                        Notification::make()
                            ->success()
                            ->title("Заказ #{$order->id} взят")
                            ->send();

                        // Используем Livewire redirect, если это действие в Widget или Page
                        return redirect()->to("/orders/{$order->id}/edit");
                    })
            ] : [CreateAction::make()])
        ];
    }
}
