<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\PlayStation\PlayStationAlt;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('play_station_alts')->orderBy('id')->chunk(1000, function ($alts) {
            $data = [];
            foreach ($alts as $alt) {
                $data[] = [
                    'sku' => $alt->sku,
                    'name' => $alt->name ?? 'Unknown',
                    'type' => str_starts_with($alt->sku, 'VOUCHER-') ? 'voucher' : 'game',
                    'price_rub' => $alt->woo_price_rub,
                    'price_try' => $alt->woo_price_try,
                    'base_price' => $alt->base_price,
                    'type_form_id' => $alt->type_form_id,
                    'data' => $alt->data,
                    'is_manual' => $alt->is_manual,
                    'send_to_ym_at' => $alt->send_to_ym_at,
                    'created_at' => $alt->created_at ?? now(),
                    'updated_at' => $alt->updated_at ?? now(),
                ];
            }

            if (!empty($data)) {
                DB::table('products')->upsert($data, ['sku'], [
                    'name', 'type', 'price_rub', 'price_try', 'base_price', 
                    'type_form_id', 'data', 'is_manual', 'send_to_ym_at', 'updated_at'
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to clear products as it's a new table
    }
};
