<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_cases', function (Blueprint $table) {
            $table->string('owner_team')->nullable()->after('status');
            $table->timestamp('sla_due_at')->nullable()->after('owner_team');
            $table->boolean('auto_created')->default(false)->after('sla_due_at');
            $table->text('auto_reason')->nullable()->after('auto_created');

            $table->index(['owner_team', 'status']);
            $table->index('sla_due_at');
            $table->index('auto_created');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_cases', function (Blueprint $table) {
            $table->dropIndex(['owner_team', 'status']);
            $table->dropIndex(['sla_due_at']);
            $table->dropIndex(['auto_created']);
            $table->dropColumn(['owner_team', 'sla_due_at', 'auto_created', 'auto_reason']);
        });
    }
};
