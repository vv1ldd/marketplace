<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meanly_analytics_events', function (Blueprint $table): void {
            $table->foreignId('sovereign_ledger_id')
                ->nullable()
                ->after('id')
                ->constrained('sovereign_ledger')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meanly_analytics_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sovereign_ledger_id');
        });
    }
};
