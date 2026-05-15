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
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->json('agreement_metadata')->nullable()->after('agreement_signature');
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn('agreement_metadata');
        });
    }
};
