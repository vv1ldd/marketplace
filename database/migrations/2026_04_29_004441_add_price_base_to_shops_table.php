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
        if (Schema::hasColumn('shops', 'price_base')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            $after = Schema::hasColumn('shops', 'markup_percent') ? 'markup_percent' : 'id';

            $table->string('price_base')->default('purchase')->after($after);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('shops', 'price_base')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('price_base');
        });
    }
};
