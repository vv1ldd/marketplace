<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_governance_stream_events', function (Blueprint $table) {
            $table->id();
            $table->string('stream_id', 128);
            $table->unsignedBigInteger('version');
            $table->string('event_id', 255);
            $table->string('event_type', 128);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['stream_id', 'version']);
            $table->unique('event_id');
            $table->index('stream_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_governance_stream_events');
    }
};
