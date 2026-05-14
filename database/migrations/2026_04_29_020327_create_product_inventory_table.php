<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_inventory', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('shop_id')->constrained()->onDelete('cascade');
            $blueprint->string('sku')->index();
            $blueprint->text('code');
            $blueprint->boolean('is_used')->default(false)->index();
            $blueprint->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('set null');
            $blueprint->timestamp('expires_at')->nullable();
            $blueprint->timestamps();

            // Unique constraint to prevent duplicate codes for the same shop (optional but safe)
            // $blueprint->unique(['shop_id', 'code']); // Code can be long text, so careful with index size
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_inventory');
    }
};
