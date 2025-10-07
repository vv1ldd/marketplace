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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->bigInteger('order_id')->unique();
            $table->integer('type_id')->nullable();
            $table->string('status')->default('NEW');
            $table->string('sub_status')->nullable();
            $table->integer('progress_id')->default(1);
            $table->json('info');
            $table->json('client_info');
            $table->integer('chat_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_problem')->default(false);
            $table->bigInteger('assigned_user_id')->nullable();
            $table->boolean('code_activated')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
