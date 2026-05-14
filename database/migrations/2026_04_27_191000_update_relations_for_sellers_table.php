<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add seller_id column to tables
        Schema::table('shop_user', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('user_id')->constrained('sellers')->cascadeOnDelete();
        });

        Schema::table('legal_entities', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('user_id')->constrained('sellers')->nullOnDelete();
        });

        // 2. Data Migration: Move Partners from users to sellers
        $partners = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'b2b_partner')
            ->select('users.*')
            ->get();

        foreach ($partners as $partner) {
            // Insert into sellers
            $sellerId = DB::table('sellers')->insertGetId([
                'first_name' => $partner->first_name,
                'last_name' => $partner->last_name,
                'middle_name' => $partner->middle_name,
                'email' => $partner->email,
                'phone' => $partner->phone,
                'password' => $partner->password,
                'is_active' => true,
                'created_at' => $partner->created_at,
                'updated_at' => $partner->updated_at,
            ]);

            // Update relations
            DB::table('shop_user')->where('user_id', $partner->id)->update(['seller_id' => $sellerId]);
            DB::table('legal_entities')->where('user_id', $partner->id)->update(['seller_id' => $sellerId]);
        }
    }

    public function down(): void
    {
        Schema::table('shop_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
        });

        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
        });
    }
};
