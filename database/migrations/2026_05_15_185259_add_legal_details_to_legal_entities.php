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
            if (!Schema::hasColumn('legal_entities', 'status')) {
                $table->string('status')->default('pending')->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['agreement_signed_at', 'agreement_signature', 'bank_bic', 'bank_account', 'ogrn', 'legal_address', 'status']);
        });
    }
};
