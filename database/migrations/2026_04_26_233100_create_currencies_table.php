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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // USD, EUR, TRY...
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->decimal('rate_to_rub', 18, 4)->default(1.0);
            $table->decimal('manual_rate', 18, 4)->nullable();
            $table->boolean('is_auto_update')->default(true);
            $table->timestamps();
        });

        // Seed some defaults
        \DB::table('currencies')->insert([
            ['code' => 'USD', 'name' => 'US Dollar', 'rate_to_rub' => 100.0, 'created_at' => now()],
            ['code' => 'EUR', 'name' => 'Euro', 'rate_to_rub' => 110.0, 'created_at' => now()],
            ['code' => 'TRY', 'name' => 'Turkish Lira', 'rate_to_rub' => 3.0, 'created_at' => now()],
            ['code' => 'GBP', 'name' => 'British Pound', 'rate_to_rub' => 125.0, 'created_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
