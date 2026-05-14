<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entities', function (Blueprint $blueprint) {
            $blueprint->bigInteger('balance')->default(0)->after('email')->comment('Balance in cents');
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $blueprint) {
            $blueprint->dropColumn('balance');
        });
    }
};
