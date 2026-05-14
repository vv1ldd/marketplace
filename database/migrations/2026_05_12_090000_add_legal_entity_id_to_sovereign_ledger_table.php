<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            // 🤝 Integrate the Legal Entity (Partner level) scope alongside direct shop linkage
            $table->foreignId('legal_entity_id')
                  ->after('shop_id')
                  ->nullable()
                  ->constrained('legal_entities')
                  ->onDelete('set null')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_entity_id');
        });
    }
};
