<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop existing unique index before changing type
        $indexes = collect(DB::select('SHOW INDEX FROM api_applications'))->pluck('Key_name')->unique();
        if ($indexes->contains('api_applications_token_unique')) {
            Schema::table('api_applications', function (Blueprint $table) {
                $table->dropUnique('api_applications_token_unique');
            });
        }

        // 2. ApiApplications: Use direct SQL for modifications
        DB::statement('ALTER TABLE api_applications MODIFY token TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE api_applications MODIFY first_name TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE api_applications MODIFY last_name TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE api_applications MODIFY phone TEXT DEFAULT NULL');

        Schema::table('api_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('api_applications', 'token_bidx')) {
                $table->string('token_bidx', 64)->nullable()->after('token')->index();
            }
            if (!Schema::hasColumn('api_applications', 'phone_bidx')) {
                $table->string('phone_bidx', 64)->nullable()->after('phone')->index();
            }
        });

        // 3. Migrate data
        $vault = app(\App\Services\VaultTransitService::class);

        DB::table('api_applications')->chunkById(100, function ($apps) use ($vault) {
            foreach ($apps as $app) {
                $updates = [];
                foreach (['token', 'first_name', 'last_name', 'phone'] as $field) {
                    if ($app->$field && !str_starts_with($app->$field, 'vault:')) {
                        $updates[$field] = $vault->encrypt($app->$field);
                        if ($field === 'token' || $field === 'phone') {
                            $updates[$field . '_bidx'] = $vault->computeBlindIndex($app->$field);
                        }
                    }
                }
                if (!empty($updates)) {
                    DB::table('api_applications')->where('id', $app->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
    }
};
