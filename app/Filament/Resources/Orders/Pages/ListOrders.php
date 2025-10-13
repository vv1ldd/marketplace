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
use Filament\Tables\Table;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function table(Table $table): Table
    {
        $user_id = auth()->user()->id;

        $is_executor = auth()->user()->hasRole('executor');

        if ($is_executor) {
            return $table->modifyQueryUsing(fn($query) => $query->where('assigned_user_id', $user_id)->where('is_problem', false));
        } else {
            return $table;
        }
    }

    protected function getHeaderActions(): array
    {
        $is_executor = auth()->user()->hasRole('executor');


        return [
            ...($is_executor ? [
                Action::make('takeOrder')
                    ->label('Взять заказ')
//                    ->form(function () {
//                        return [
//                            TextInput::make('comment')
//                        ];
//                    })
                    ->action(function () {

                        $order = Order::availableForExecutor()->first();

                        if (!$order) {
                            Notification::make()
                                ->warning()
                                ->title('Нет доступных заказов')
                                ->send();

                            return;
                        }

                        $order->update([
                            'assigned_user_id' => auth()->id(),
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
