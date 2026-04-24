<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ApiApplication;
use App\Models\Shop;

return new class extends Migration
{
    public function up()
    {
        // Fetch all API applications that have a domain match in shops
        $apps = ApiApplication::whereNotNull('domain')->get();

        foreach ($apps as $app) {
            $shop = Shop::where('domain', $app->domain)->first();

            if ($shop) {
                // Move the token to the shop if the shop doesn't have one yet, or overwrite it
                $shop->update([
                    'store_api_token' => $app->token ?: $shop->store_api_token,
                ]);
            }
        }
    }

    public function down()
    {
        // No easy way to reverse this
    }
};
