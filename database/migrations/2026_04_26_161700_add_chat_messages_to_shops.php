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
        Schema::table('shops', function (Blueprint $table) {
            $table->text('ym_chat_greeting')->nullable()->after('ym_logo')->comment('Приветственное сообщение в чате');
            $table->text('ym_chat_finish')->nullable()->after('ym_chat_greeting')->comment('Сообщение при доставке кода');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['ym_chat_greeting', 'ym_chat_finish']);
        });
    }
};
