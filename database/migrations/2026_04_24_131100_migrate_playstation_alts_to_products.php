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
        // Прямой SQL запрос максимально быстрый, так как не гоняет данные через PHP
        DB::statement("
            INSERT INTO products (
                sku, name, type, price_rub, price_try, base_price, 
                type_form_id, data, is_manual, send_to_ym_at, 
                created_at, updated_at
            )
            SELECT 
                sku, 
                COALESCE(name, 'Unknown'), 
                IF(sku LIKE 'VOUCHER-%', 'voucher', 'game'), 
                woo_price_rub, 
                woo_price_try, 
                base_price, 
                type_form_id, 
                data, 
                is_manual, 
                send_to_ym_at, 
                COALESCE(created_at, NOW()), 
                COALESCE(updated_at, NOW())
            FROM play_station_alts
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                type = VALUES(type),
                price_rub = VALUES(price_rub),
                price_try = VALUES(price_try),
                base_price = VALUES(base_price),
                type_form_id = VALUES(type_form_id),
                data = VALUES(data),
                is_manual = VALUES(is_manual),
                send_to_ym_at = VALUES(send_to_ym_at),
                updated_at = NOW()
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to clear products as it's a new table
    }
};
