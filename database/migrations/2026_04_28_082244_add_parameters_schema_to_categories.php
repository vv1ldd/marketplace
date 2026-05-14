<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('parameters_schema')->nullable()->after('description');
            $table->timestamp('parameters_fetched_at')->nullable()->after('parameters_schema');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['parameters_schema', 'parameters_fetched_at']);
        });
    }
};
