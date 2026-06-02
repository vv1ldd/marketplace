<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wildflow_kernel_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->string('provider', 64)->index();
            $table->string('marketplace_reference', 180)->nullable();
            $table->string('proxy_reference', 180)->unique();
            $table->string('vendor_reference', 180)->nullable()->index();
            $table->string('service_sku', 180);
            $table->decimal('price', 16, 4)->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('status', 32)->default('processing')->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'provider', 'marketplace_reference'], 'wf_kernel_order_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wildflow_kernel_orders');
    }
};
