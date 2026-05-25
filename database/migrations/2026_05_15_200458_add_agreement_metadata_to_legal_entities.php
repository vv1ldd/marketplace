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
        if (Schema::hasColumn('legal_entities', 'agreement_metadata')) {
            return;
        }

        Schema::table('legal_entities', function (Blueprint $table) {
            $after = Schema::hasColumn('legal_entities', 'agreement_signature') ? 'agreement_signature' : 'agreement_signed_at';

            $table->json('agreement_metadata')->nullable()->after($after);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('legal_entities', 'agreement_metadata')) {
            return;
        }

        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn('agreement_metadata');
        });
    }
};
