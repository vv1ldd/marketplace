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
        Schema::create('sovereign_balance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('type'); // 'top_up', 'grant_credit'
            $table->decimal('amount', 16, 4);
            $table->string('currency', 10)->default('RUB');
            $table->string('status', 20)->default('pending'); // 'pending', 'approved', 'rejected'
            $table->string('l1_address', 60);
            $table->foreignId('passkey_id')->nullable()->constrained('passkeys')->nullOnDelete();
            $table->longText('signature_assertion'); // JSON WebAuthn signature assertion
            $table->text('comment')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sovereign_balance_requests');
    }
};
