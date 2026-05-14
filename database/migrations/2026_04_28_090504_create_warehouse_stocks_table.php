<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $col) {
            $col->id();
            $col->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $col->foreignId('product_id')->constrained()->cascadeOnDelete();
            $col->integer('count')->default(0);
            $col->timestamp('synced_at')->nullable();
            $col->timestamps();

            $col->unique(['warehouse_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
