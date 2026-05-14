<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->string('legal_address_bidx', 64)->nullable()->after('legal_address')->index();
            $table->string('postal_address_bidx', 64)->nullable()->after('postal_address')->index();
        });

        $entities = DB::table('legal_entities')->get();
        $vault = app(\App\Services\VaultTransitService::class);

        foreach ($entities as $entity) {
            $updates = [];
            foreach (['legal_address', 'postal_address'] as $field) {
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
            foreach (['legal_address', 'postal_address'] as $field) {
                if ($entity->$field && str_starts_with($entity->$field, 'vault:v1:')) {
                    try {
                        $updates[$field] = $vault->decrypt($entity->$field);
                    } catch (\Exception $e) {}
                }
            }
            if (!empty($updates)) {
                DB::table('legal_entities')->where('id', $entity->id)->update($updates);
            }
        }

        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['legal_address_bidx', 'postal_address_bidx']);
        });
    }
};
