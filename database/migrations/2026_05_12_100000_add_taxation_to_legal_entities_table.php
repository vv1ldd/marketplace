<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            // 🌍 Country scope
            $table->string('country_code', 2)->default('RU')->after('email_bidx')->index();

            // 💼 Primary Tax Engine configuration
            $table->string('tax_system')->nullable()->after('country_code')->index(); 
            $table->decimal('tax_rate', 5, 2)->default(0.00)->after('tax_system'); 

            // ⚠️ VAT Threshold Trigger (Crucial for RU 2025 updates)
            $table->boolean('is_vat_payer')->default(false)->after('tax_rate');
            $table->decimal('vat_rate', 5, 2)->nullable()->after('is_vat_payer'); // 0%, 5%, 7%, 10%, 20%
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'tax_system', 'tax_rate', 'is_vat_payer', 'vat_rate']);
        });
    }
};
