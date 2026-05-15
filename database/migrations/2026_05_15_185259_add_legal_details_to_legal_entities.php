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
            $table->timestamp('agreement_signed_at')->nullable();
            $table->text('agreement_signature')->nullable();
            $table->string('bank_bic')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('ogrn')->nullable();
            $table->text('legal_address')->nullable();
            $table->string('status')->default('pending'); // pending, active, rejected
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['agreement_signed_at', 'agreement_signature', 'bank_bic', 'bank_account', 'ogrn', 'legal_address', 'status']);
        });
    }
};
