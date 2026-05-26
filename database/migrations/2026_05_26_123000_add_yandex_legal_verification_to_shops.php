<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'ym_legal_verification')) {
                $table->json('ym_legal_verification')->nullable()->after('ym_min_selling_price');
            }

            if (! Schema::hasColumn('shops', 'ym_legal_verified_at')) {
                $table->timestamp('ym_legal_verified_at')->nullable()->after('ym_legal_verification');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'ym_legal_verified_at')) {
                $table->dropColumn('ym_legal_verified_at');
            }

            if (Schema::hasColumn('shops', 'ym_legal_verification')) {
                $table->dropColumn('ym_legal_verification');
            }
        });
    }
};
