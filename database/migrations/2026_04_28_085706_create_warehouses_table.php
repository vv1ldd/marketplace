<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $col) {
            $col->id();
            $col->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $col->bigInteger('ym_id')->index();
            $col->string('name');
            $col->string('type')->nullable(); // FBS, DBS, etc.
            $col->boolean('is_active')->default(true);
            $col->json('data')->nullable();
            $col->timestamps();

            $col->unique(['shop_id', 'ym_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
