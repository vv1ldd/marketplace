<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_governance_projection_cache', function (Blueprint $table) {
            $table->string('stream_id', 128)->primary();
            $table->unsignedBigInteger('through_version');
            $table->json('registry_projection');
            $table->json('governance_projection');
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_governance_projection_cache');
    }
};
