<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, image, boolean
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Seed initial settings
        DB::table('system_settings')->insert([
            ['key' => 'system_name', 'value' => 'WILDCLOUD', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'system_logo', 'value' => null, 'type' => 'image', 'group' => 'branding'],
            ['key' => 'default_redeem_url', 'value' => 'https://wildcloud.ru/redeem', 'type' => 'string', 'group' => 'video_engine'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
