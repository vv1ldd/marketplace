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
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->decimal('native_token_balance', 18, 4)->default(1000.0000)->after('reserved_balance');
            $table->decimal('native_token_reserved', 18, 4)->default(0.0000)->after('native_token_balance');
            $table->string('native_token_currency', 10)->default('SL1')->after('native_token_reserved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['native_token_balance', 'native_token_reserved', 'native_token_currency']);
        });
    }
};
