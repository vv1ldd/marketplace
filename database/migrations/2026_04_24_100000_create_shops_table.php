<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('business_id')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('api_key')->nullable();
            $table->string('notification_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_purchase_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
