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
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->integer('accessibility_score')->default(100)->after('name_tk'); // 0-100 (100 = full ease)
            $table->string('regulatory_status')->default('friendly')->after('accessibility_score'); // friendly, grey, restricted
            $table->boolean('has_capital_controls')->default(false)->after('regulatory_status');
            $table->text('local_notes')->nullable()->after('has_capital_controls');
            $table->foreignId('primary_currency_id')->nullable()->constrained('currencies')->onDelete('set null')->after('local_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->dropForeign(['primary_currency_id']);
            $table->dropColumn(['accessibility_score', 'regulatory_status', 'has_capital_controls', 'local_notes', 'primary_currency_id']);
        });
    }
};
