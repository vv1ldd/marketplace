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
            // Drop old balance if it exists, or we can just keep it. We'll add the new ones specifically.
            $table->decimal('available_balance', 12, 2)->default(0)->after('email');
            $table->decimal('reserved_balance', 12, 2)->default(0)->after('available_balance');
            $table->string('currency', 3)->default('RUB')->after('reserved_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['available_balance', 'reserved_balance', 'currency']);
        });
    }
};
