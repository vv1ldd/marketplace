<?php

namespace App\Filament\Partner\Resources;

use App\Models\User;
use App\Models\Shop;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

class TeamMemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'admin.shops.tabs.managers';

    public static function getLabel(): string
    {
        return __('admin.shops.tabs.managers');
    }

    public static function getPluralLabel(): string
    {
        return __('admin.shops.tabs.managers');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();
        return parent::getEloquentQuery()
            ->whereHas('managedLegalEntities', function (Builder $query) use ($tenant) {
                $query->where('legal_entities.id', $tenant->id);
            });
    }

    public static function form(\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->label(__('admin.common.fields.email'))
                            ->disabled(fn ($record) => $record !== null), // Can't change email of existing user
                        
                        Forms\Components\TextInput::make('first_name')
                            ->label(__('admin.common.fields.first_name'))
                            ->required(),
                        
                        Forms\Components\TextInput::make('last_name')
                            ->label(__('admin.common.fields.last_name'))
                            ->required(),

                        Forms\Components\Select::make('role')
                            ->label(__('admin.common.fields.role'))
                            ->options([
                                'manager' => 'Manager',
                                'viewer' => 'Viewer (Read Only)',
                            ])
                            ->required()
                            ->dehydrated(false) // Handle in save logic
                            ->afterStateHydrated(function ($set, $record) {
                                if ($record) {
                                    $tenant = Filament::getTenant();
                                    $pivot = $record->managedLegalEntities()
                                        ->where('legal_entities.id', $tenant->id)
                                        ->first()?->pivot;
                                    
                                    if ($pivot) {
                                        $set('role', $pivot->role);
                                    }
                                }
                            }),
                    ])->columns(2)
            ]);
    }

    protected static bool $isScopedToTenant = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('admin.common.fields.name'))
                    ->state(fn ($record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name']),
                
                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin.common.fields.email'))
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('role')
                    ->label(__('admin.common.fields.role'))
                    ->state(fn ($record) => 
                        $record->managedLegalEntities()
                            ->where('legal_entities.id', Filament::getTenant()->id)
                            ->first()?->pivot?->role ?? 'N/A'
                    ),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->label(__('admin.common.remove'))
                    ->modalHeading(__('admin.common.remove_confirm'))
                    ->action(function ($record) {
                        $tenant = Filament::getTenant();
                        $record->managedLegalEntities()->detach($tenant->id);
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $tenant = Filament::getTenant();
                            foreach ($records as $record) {
                                $record->managedLegalEntities()->detach($tenant->id);
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => TeamMemberResource\Pages\ListTeamMembers::route('/'),
            'create' => TeamMemberResource\Pages\CreateTeamMember::route('/create'),
            'edit' => TeamMemberResource\Pages\EditTeamMember::route('/{record}/edit'),
        ];
    }
}
