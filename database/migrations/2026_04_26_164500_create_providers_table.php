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
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Название провайдера');
            $table->string('type')->unique()->comment('Тип драйвера (wildflow, playstation и т.д.)');
            $table->boolean('is_active')->default(true);
            $table->json('credentials')->nullable()->comment('API ключи и доступы');
            $table->json('settings')->nullable()->comment('Курсы валют, наценки, лимиты');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        // Seed default providers if they don't exist
        // We can do this in a seeder or just keep it here for initial setup
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
