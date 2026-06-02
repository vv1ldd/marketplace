<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wildflow_credit_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->decimal('amount', 16, 4);
            $table->string('reference', 160);
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'reference']);
            $table->index(['reference', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wildflow_credit_reservations');
    }
};
