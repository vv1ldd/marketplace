<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            if (! Schema::hasColumn('shops', 'is_distribution_center')) {
                $table->boolean('is_distribution_center')->default(false)->after('is_sandbox');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            if (Schema::hasColumn('shops', 'is_distribution_center')) {
                $table->dropColumn('is_distribution_center');
            }
        });
    }
};
