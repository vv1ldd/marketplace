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
        Schema::create('telegram_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_channel_id')->constrained('direct_channels')->cascadeOnDelete();
            $table->foreignId('wildflow_catalog_id')->nullable()->constrained('wildflow_catalogs')->nullOnDelete();
            $table->string('message_id')->nullable();
            $table->integer('clicks')->default(0);
            $table->integer('purchases')->default(0);
            $table->decimal('posted_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_posts');
    }
};
