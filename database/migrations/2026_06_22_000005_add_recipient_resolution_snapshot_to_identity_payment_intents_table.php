<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_payment_intents', function (Blueprint $table) {
            $table->json('recipient_resolution_snapshot')->nullable()->after('routing_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('identity_payment_intents', function (Blueprint $table) {
            $table->dropColumn('recipient_resolution_snapshot');
        });
    }
};
