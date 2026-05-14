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
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            $table->string('trigger_source')->nullable()->after('payload')->comment('Who or what initiated the state mutation');
            $table->json('input_data')->nullable()->after('trigger_source')->comment('Sanitized parameters passed to the mutating function');
            $table->json('output_state')->nullable()->after('input_data')->comment('The resulting state footprint of the entity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            $table->dropColumn(['trigger_source', 'input_data', 'output_state']);
        });
    }
};
