<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            // Alter to TEXT if exists, or add as TEXT
            if (Schema::hasColumn('legal_entities', 'agreement_signature')) {
                $table->text('agreement_signature')->nullable()->change();
            } else {
                $table->text('agreement_signature')->nullable()->after('agreement_signed_at');
            }
            
            $table->string('agreement_signature_bidx', 64)->nullable()->after('agreement_signature')->index();
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['agreement_signature_bidx']);
            // We keep agreement_signature but maybe revert type? 
            // Usually not necessary for down unless it breaks something.
        });
    }
};
