<?php

namespace App\Filament\Kernel\Resources;

use App\Filament\Kernel\Resources\SystemSettings\Pages\ManageSystemSettings;
use App\Models\SystemSetting;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getNavigationGroup(): ?string
    {
        return 'Администрирование';
    }

    protected static ?int $navigationSort = 102;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setting Details')
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->disabled()
                            ->maxLength(255),
                        TextInput::make('group')
                            ->disabled()
                            ->maxLength(255),
                        Select::make('type')
                            ->options([
                                'string' => 'String',
                                'image' => 'Image',
                                'boolean' => 'Boolean',
                            ])
                            ->disabled(),

                        // Dynamic value field based on type
                        FileUpload::make('value')
                            ->label('Image Value')
                            ->image()
                            ->directory('system/branding')
                            ->visible(fn ($record) => $record?->type === 'image'),

                        TextInput::make('value')
                            ->label('Text Value')
                            ->visible(fn ($record) => $record?->type === 'string'),

                        Toggle::make('value')
                            ->label('Enabled')
                            ->visible(fn ($record) => $record?->type === 'boolean'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('group')->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->limit(50)
                    ->formatStateUsing(fn ($record) => $record->type === 'image' ? 'Image File' : $record->value),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSystemSettings::route('/'),
        ];
    }
}
