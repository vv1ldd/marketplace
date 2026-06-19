<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_governance_stream_events', function (Blueprint $table) {
            $table->string('event_id', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('identity_governance_stream_events', function (Blueprint $table) {
            $table->string('event_id', 191)->change();
        });
    }
};
