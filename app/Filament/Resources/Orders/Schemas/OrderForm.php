<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Mail\SendAccountDataMail;
use App\Models\Order\Order;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\User;
use App\Services\AccountGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Facades\Mail;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $order = $schema->getRecord();

        $order_user_meta = $order->user?->meta ?? null;

        $is_create = !$order;
        $is_update = !$is_create;
        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');
        $super_admin = auth()->user()->hasRole('super_admin');

        return $schema
            ->components([
                Section::make('Заказ')->collapsible()->schema([
                    Grid::make(3)->schema([
                        TextInput::make('id')
                            ->label('Номер заказа')
                            ->readOnly($is_update)
                            ->copyable()
                            ->hidden($is_create)
                            ->required($is_create),
                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->required()
                            ->hidden($is_executor || $is_support)
                            ->label('Юзер'),
                        Select::make('progress_id')
                            ->relationship('progress', 'name')
                            ->required()
                            ->label('Прогресс по заказу'),
                        Toggle::make('is_problem')
                            ->inline(false)
                            ->label('Проблемный заказ')
                            ->default(false),
                        DateTimePicker::make('created_at')
                            ->label('Дата создания')
                            ->disabled(),
//                        Textarea::make('comment')
//                            ->label('Комментарий')
//                            ->rows(2)
//                            ->columnSpanFull(),
                    ])

                ])->columnSpanFull(),
            ]);
    }
}
