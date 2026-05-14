<?php

namespace App\Filament\Partner\Resources;

use App\Models\Customer;
use App\Filament\Resources\Users\Tables\CustomersTable;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    public static function canAccess(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.customers.customers');
    }

    public static function getLabel(): string
    {
        return __('admin.customers.customer');
    }

    public static function getPluralLabel(): string
    {
        return __('admin.customers.customers');
    }

    /*
     * Scope to current shop
     */


    protected static bool $isScopedToTenant = true;
    
    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
    }

    public static function table(Table $table): Table
    {
        // Reuse the project's standard customers table configuration
        return CustomersTable::configure($table)
            ->headerActions([])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CustomerResource\Pages\ListCustomers::route('/'),
        ];
    }
}
