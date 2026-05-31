<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('canonical_product_search_profiles')) {
            return;
        }

        Schema::create('canonical_product_search_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_product_identity_id');
            $table->text('search_text');
            $table->json('search_tokens');
            $table->json('search_aliases');
            $table->json('search_metadata');
            $table->unsignedInteger('profile_version')->index();
            $table->timestamp('last_rebuild_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('canonical_product_identity_id', 'canonical_search_profiles_identity_fk')
                ->references('id')
                ->on('canonical_product_identities')
                ->cascadeOnDelete();
            $table->unique('canonical_product_identity_id', 'canonical_search_profiles_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_product_search_profiles');
    }
};
