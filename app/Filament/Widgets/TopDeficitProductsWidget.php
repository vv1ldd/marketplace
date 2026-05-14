<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopDeficitProductsWidget extends BaseWidget
{
    protected static ?string $heading = '🚨 Топ Упущенных Продаж (Дефицит)';
    
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        // Smart Ledger Inspector: Dissecting JSON payloads to sum theoretical demand deficits.
        $sub = DB::table('sovereign_ledger')
            ->where('event_type', 'PROVIDER_STOCK_DEFICIT')
            ->select([
                DB::raw("MIN(sovereign_ledger.id) as id"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(sovereign_ledger.payload, '$.sku')) as sku"),
                DB::raw("CAST(SUM(JSON_UNQUOTE(JSON_EXTRACT(sovereign_ledger.payload, '$.requested_quantity'))) AS UNSIGNED) as total_deficit"),
                DB::raw('COALESCE(MAX(products.name), MAX(wildflow_catalogs.sku), "Unknown Product") as product_name'),
            ])
            ->leftJoin('wildflow_catalogs', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(sovereign_ledger.payload, '$.sku'))"), '=', 'wildflow_catalogs.sku')
            ->leftJoin('products', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(sovereign_ledger.payload, '$.sku'))"), '=', 'products.sku')
            ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(sovereign_ledger.payload, '$.sku'))"))
            ->orderByDesc('total_deficit')
            ->limit(10);

        $query = \App\Models\SovereignLedger::query()->fromSub($sub, 'sovereign_ledger');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Товар, которого не хватило')
                    ->weight('bold')
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('sku')
                    ->label('Артикул (SKU)')
                    ->copyable()
                    ->fontFamily('mono'),
                
                Tables\Columns\TextColumn::make('total_deficit')
                    ->label('Недополучено спроса (шт.)')
                    ->badge()
                    ->color('danger')
                    ->alignment('right')
                    ->description('Раз не хватило стока'),
            ])
            ->emptyStateHeading('Всё спокойно')
            ->emptyStateDescription('Дефицитных событий пока не зафиксировано. Весь спрос удовлетворяется!')
            ->paginated(false);
    }
}
