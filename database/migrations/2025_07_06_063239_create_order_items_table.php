<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->boolean('is_activated')->default(false);
            $table->bigInteger('order_id');
            $table->uuid('sku');
            $table->integer('count');
            $table->date('activate_till');
            $table->timestamps();

            $table->unique(['order_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
