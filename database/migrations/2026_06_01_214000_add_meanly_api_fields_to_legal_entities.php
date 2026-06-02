<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entities', function (Blueprint $table): void {
            if (! Schema::hasColumn('legal_entities', 'meanly_api_token')) {
                $table->string('meanly_api_token')->nullable()->after('wildflow_api_token');
            }

            if (! Schema::hasColumn('legal_entities', 'meanly_financial_secret')) {
                $table->text('meanly_financial_secret')->nullable()->after('meanly_api_token');
            }

            if (! Schema::hasColumn('legal_entities', 'meanly_ip_whitelist')) {
                $table->json('meanly_ip_whitelist')->nullable()->after('meanly_financial_secret');
            }
        });

        if (
            Schema::hasColumn('legal_entities', 'wildflow_api_token')
            && Schema::hasColumn('legal_entities', 'meanly_api_token')
        ) {
            DB::table('legal_entities')
                ->whereNull('meanly_api_token')
                ->whereNotNull('wildflow_api_token')
                ->update(['meanly_api_token' => DB::raw('wildflow_api_token')]);
        }

        if (
            Schema::hasColumn('legal_entities', 'wildflow_financial_secret')
            && Schema::hasColumn('legal_entities', 'meanly_financial_secret')
        ) {
            DB::table('legal_entities')
                ->whereNull('meanly_financial_secret')
                ->whereNotNull('wildflow_financial_secret')
                ->update(['meanly_financial_secret' => DB::raw('wildflow_financial_secret')]);
        }

        if (
            Schema::hasColumn('legal_entities', 'wildflow_ip_whitelist')
            && Schema::hasColumn('legal_entities', 'meanly_ip_whitelist')
        ) {
            DB::table('legal_entities')
                ->whereNull('meanly_ip_whitelist')
                ->whereNotNull('wildflow_ip_whitelist')
                ->update(['meanly_ip_whitelist' => DB::raw('wildflow_ip_whitelist')]);
        }
    }

    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table): void {
            if (Schema::hasColumn('legal_entities', 'meanly_ip_whitelist')) {
                $table->dropColumn('meanly_ip_whitelist');
            }

            if (Schema::hasColumn('legal_entities', 'meanly_financial_secret')) {
                $table->dropColumn('meanly_financial_secret');
            }

            if (Schema::hasColumn('legal_entities', 'meanly_api_token')) {
                $table->dropColumn('meanly_api_token');
            }
        });
    }
};
