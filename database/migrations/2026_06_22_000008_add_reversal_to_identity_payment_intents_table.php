<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_payment_intents', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of_intent_id')->nullable()->after('idempotency_key');
            $table->string('reversal_reason', 128)->nullable()->after('reversal_of_intent_id');

            $table->unique('reversal_of_intent_id', 'ip_intents_reversal_of_uq');
            $table->foreign('reversal_of_intent_id', 'ip_intents_reversal_of_fk')
                ->references('id')
                ->on('identity_payment_intents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('identity_payment_intents', function (Blueprint $table) {
            $table->dropForeign('ip_intents_reversal_of_fk');
            $table->dropUnique('ip_intents_reversal_of_uq');
            $table->dropColumn(['reversal_of_intent_id', 'reversal_reason']);
        });
    }
};
