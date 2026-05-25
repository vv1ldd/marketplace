<?php

namespace App\Filament\Resources\B2B;

use App\Filament\Resources\B2B\Pages\CreateSellerTerminal;
use App\Filament\Resources\B2B\Pages\EditSellerTerminal;
use App\Filament\Resources\B2B\Pages\ListSellerTerminals;
use App\Models\SellerTerminal;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;

class SellerTerminalResource extends Resource
{
    protected static ?string $model = SellerTerminal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Терминалы продавцов';

    public static function getLabel(): ?string
    {
        return 'Терминал продавца';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Терминалы продавцов';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('legal_entity_id')
                    ->label('Продавец (Юр. лицо)')
                    ->relationship('legalEntity', 'short_name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('terminal_id')
                    ->label('ID Терминала')
                    ->default(fn () => SellerTerminal::generateTerminalId())
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('daily_limit')
                    ->label('Суточный лимит (RUB)')
                    ->numeric()
                    ->default(0)
                    ->helperText('0 = без лимита')
                    ->required(),

                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),

                DateTimePicker::make('expires_at')
                    ->label('Срок действия')
                    ->placeholder('Бессрочно'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('terminal_id')
                    ->label('ID Терминала')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('legalEntity.name')
                    ->label('Продавец')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('daily_limit')
                    ->label('Суточный лимит')
                    ->money('RUB')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Активен'),

                TextColumn::make('last_used_at')
                    ->label('Последнее использование')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('last_ip')
                    ->label('IP-адрес')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expires_at')
                    ->label('Истекает')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('regeneratePin')
                    ->label('Сгенерировать PIN')
                    ->icon('heroicon-m-key')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Регенерация PIN-кода терминала')
                    ->modalDescription('Вы действительно хотите сгенерировать новый PIN? Старый PIN перестанет работать немедленно.')
                    ->action(function (SellerTerminal $record) {
                        $newPin = SellerTerminal::generatePin();
                        $record->update([
                            'terminal_pin' => $newPin,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('PIN-код успешно сгенерирован!')
                            ->body("Новый PIN: **{$newPin}**\n\nСкопируйте его прямо сейчас. Он зашифрован в базе данных и больше никогда не будет показан.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 11;

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellerTerminals::route('/'),
            'create' => CreateSellerTerminal::route('/create'),
            'edit' => EditSellerTerminal::route('/{record}/edit'),
        ];
    }
}
