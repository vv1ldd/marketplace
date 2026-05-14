<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop existing unique constraints that will conflict
        $this->dropIndexIfExists('legal_entities', 'legal_entities_inn_unique');
        $this->dropIndexIfExists('legal_entities', 'legal_entities_inn_bidx_unique');

        // 2. Clean up any partially created bidx columns
        Schema::table('legal_entities', function (Blueprint $table) {
            foreach ([
                'name_bidx', 'short_name_bidx', 'inn_bidx', 'kpp_bidx', 'ogrn_bidx',
                'director_name_bidx', 'phone_bidx', 'email_bidx',
                'bank_name_bidx', 'bank_bic_bidx', 'bank_account_bidx', 'bank_correspondent_account_bidx'
            ] as $col) {
                if (Schema::hasColumn('legal_entities', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 3. Change column types using direct SQL to avoid Doctrine/DBAL issues
        DB::statement('ALTER TABLE legal_entities MODIFY name TEXT NOT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY short_name TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY inn TEXT NOT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY kpp TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY ogrn TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY director_name TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY phone TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY email TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY bank_name TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY bank_bic TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY bank_account TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE legal_entities MODIFY bank_correspondent_account TEXT DEFAULT NULL');

        // 4. Add bidx columns
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->string('name_bidx', 64)->nullable()->after('name')->index();
            $table->string('short_name_bidx', 64)->nullable()->after('short_name')->index();
            $table->string('inn_bidx', 64)->nullable()->after('inn');
            $table->string('kpp_bidx', 64)->nullable()->after('kpp')->index();
            $table->string('ogrn_bidx', 64)->nullable()->after('ogrn')->index();
            $table->string('director_name_bidx', 64)->nullable()->after('director_name')->index();
            $table->string('phone_bidx', 64)->nullable()->after('phone')->index();
            $table->string('email_bidx', 64)->nullable()->after('email')->index();
            $table->string('bank_name_bidx', 64)->nullable()->after('bank_name')->index();
            $table->string('bank_bic_bidx', 64)->nullable()->after('bank_bic')->index();
            $table->string('bank_account_bidx', 64)->nullable()->after('bank_account')->index();
            $table->string('bank_correspondent_account_bidx', 64)->nullable()->after('bank_correspondent_account')->index();
        });

        // 5. Add unique constraint to inn_bidx
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->unique('inn_bidx', 'legal_entities_inn_bidx_unique');
        });

        // 6. Migrate existing data
        $entities = DB::table('legal_entities')->get();
        $vault = app(\App\Services\VaultTransitService::class);

        foreach ($entities as $entity) {
            $updates = [];
            foreach ([
                'name', 'short_name', 'inn', 'kpp', 'ogrn', 
                'director_name', 'phone', 'email', 
                'bank_name', 'bank_bic', 'bank_account', 'bank_correspondent_account'
            ] as $field) {
                if ($entity->$field && !str_starts_with($entity->$field, 'vault:v1:')) {
                    $updates[$field] = $vault->encrypt($entity->$field);
                    $updates[$field . '_bidx'] = $vault->computeBlindIndex($entity->$field);
                }
            }
            
            if (!empty($updates)) {
                DB::table('legal_entities')->where('id', $entity->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        $entities = DB::table('legal_entities')->get();
        $vault = app(\App\Services\VaultTransitService::class);

        foreach ($entities as $entity) {
            $updates = [];
            foreach ([
                'name', 'short_name', 'inn', 'kpp', 'ogrn', 
                'director_name', 'phone', 'email', 
                'bank_name', 'bank_bic', 'bank_account', 'bank_correspondent_account'
            ] as $field) {
                if ($entity->$field && str_starts_with($entity->$field, 'vault:v1:')) {
                    try {
                        $updates[$field] = $vault->decrypt($entity->$field);
                    } catch (\Exception $e) {
                    }
                }
            }
            if (!empty($updates)) {
                DB::table('legal_entities')->where('id', $entity->id)->update($updates);
            }
        }

        Schema::table('legal_entities', function (Blueprint $table) {
            $this->dropIndexIfExists('legal_entities', 'legal_entities_inn_bidx_unique');
            
            $table->dropColumn([
                'name_bidx', 'short_name_bidx', 'inn_bidx', 'kpp_bidx', 'ogrn_bidx',
                'director_name_bidx', 'phone_bidx', 'email_bidx',
                'bank_name_bidx', 'bank_bic_bidx', 'bank_account_bidx', 'bank_correspondent_account_bidx'
            ]);

            DB::statement('ALTER TABLE legal_entities MODIFY name VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY short_name VARCHAR(255) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY inn VARCHAR(12) NOT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY kpp VARCHAR(9) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY ogrn VARCHAR(15) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY director_name VARCHAR(255) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY phone VARCHAR(255) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY email VARCHAR(255) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY bank_name VARCHAR(255) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY bank_bic VARCHAR(9) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY bank_account VARCHAR(20) DEFAULT NULL');
            DB::statement('ALTER TABLE legal_entities MODIFY bank_correspondent_account VARCHAR(20) DEFAULT NULL');
            
            $table->unique('inn', 'legal_entities_inn_unique');
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $conn = Schema::getConnection();
        $dbName = $conn->getDatabaseName();
        
        $exists = DB::select("
            SELECT 1 FROM information_schema.statistics 
            WHERE table_schema = ? 
            AND table_name = ? 
            AND index_name = ?
        ", [$dbName, $table, $index]);

        if (!empty($exists)) {
            Schema::table($table, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        }
    }
};
