<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entity_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->onDelete('cascade');
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('role')->default('manager');
            $table->timestamps();
            
            // Allow same seller or user to be added once per entity
            // (But they might have both IDs, so we just track them as they are)
        });

        // Data migration: move owners/managers from shop_user to legal_entity_managers
        $shopUsers = DB::table('shop_user')->get();
        foreach ($shopUsers as $su) {
            $shop = DB::table('shops')->find($su->shop_id);
            if ($shop && $shop->legal_entity_id) {
                $exists = DB::table('legal_entity_managers')
                    ->where('legal_entity_id', $shop->legal_entity_id)
                    ->where(function($q) use ($su) {
                        $q->where('seller_id', $su->seller_id)
                          ->orWhere('user_id', $su->user_id);
                    })
                    ->exists();

                if (!$exists) {
                    DB::table('legal_entity_managers')->insert([
                        'legal_entity_id' => $shop->legal_entity_id,
                        'seller_id' => $su->seller_id,
                        'user_id' => $su->user_id,
                        'role' => $su->role,
                        'created_at' => $su->created_at,
                        'updated_at' => $su->updated_at,
                    ]);
                }
            }
        }

        // Also add direct owners from legal_entities.seller_id to the managers table
        $legalEntities = DB::table('legal_entities')->whereNotNull('seller_id')->get();
        foreach ($legalEntities as $le) {
            $exists = DB::table('legal_entity_managers')
                ->where('legal_entity_id', $le->id)
                ->where('seller_id', $le->seller_id)
                ->exists();

            if (!$exists) {
                DB::table('legal_entity_managers')->insert([
                    'legal_entity_id' => $le->id,
                    'seller_id' => $le->seller_id,
                    'user_id' => $le->user_id, // Also copy user_id if present
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entity_managers');
    }
};
