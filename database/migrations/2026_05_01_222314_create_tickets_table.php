<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Creator (if admin)
            $table->foreignId('seller_id')->nullable()->constrained()->onDelete('cascade'); // Creator (if partner)
            $table->string('subject');
            $table->string('status')->default('open'); // open, in_progress, closed
            $table->string('priority')->default('medium'); // low, medium, high
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
