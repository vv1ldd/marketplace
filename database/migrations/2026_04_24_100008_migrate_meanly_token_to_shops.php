<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Settings;
use App\Models\Shop;

return new class extends Migration
{
    public function up()
    {
        $token = Settings::get('MEANLY_TOKEN');

        if ($token) {
            // Try to find the MEANLY shop by name or domain
            $shop = Shop::where('name', 'MEANLY')
                ->orWhere('domain', 'meanly.ru')
                ->first();

            if ($shop) {
                $shop->update(['store_api_token' => $token]);
            }
        }
    }

    public function down()
    {
        // No need to revert data migration, but could clear the token if needed.
    }
};
