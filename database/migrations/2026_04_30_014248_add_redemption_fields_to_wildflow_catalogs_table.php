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
        if (Schema::hasColumn('wildflow_catalogs', 'redemption_instructions')) {
            return;
        }

        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $after = Schema::hasColumn('wildflow_catalogs', 'description') ? 'description' : 'data';

            $table->text('redemption_instructions')->nullable()->after($after);
            $table->string('activation_url')->nullable()->after('redemption_instructions');
            $table->string('reward_type')->nullable()->after('activation_url');
            $table->string('upc')->nullable()->after('reward_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('wildflow_catalogs', 'redemption_instructions')) {
            return;
        }

        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $table->dropColumn(['redemption_instructions', 'activation_url', 'reward_type', 'upc']);
        });
    }
};
