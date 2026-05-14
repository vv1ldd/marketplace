<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_comments', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('user_id');
        });

        // Проставляем дефолтный тип для старых комментариев
        \Illuminate\Support\Facades\DB::table('order_comments')->update([
            'user_type' => \App\Models\User::class,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_comments', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
