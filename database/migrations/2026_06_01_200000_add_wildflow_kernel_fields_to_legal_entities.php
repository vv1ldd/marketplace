<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entities', function (Blueprint $table): void {
            if (! Schema::hasColumn('legal_entities', 'wildflow_financial_secret')) {
                $table->text('wildflow_financial_secret')->nullable()->after('wildflow_api_token');
            }

            if (! Schema::hasColumn('legal_entities', 'wildflow_ip_whitelist')) {
                $table->json('wildflow_ip_whitelist')->nullable()->after('wildflow_financial_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table): void {
            if (Schema::hasColumn('legal_entities', 'wildflow_ip_whitelist')) {
                $table->dropColumn('wildflow_ip_whitelist');
            }

            if (Schema::hasColumn('legal_entities', 'wildflow_financial_secret')) {
                $table->dropColumn('wildflow_financial_secret');
            }
        });
    }
};
