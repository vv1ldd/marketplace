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
        Schema::table('brands', function (Blueprint $table) {
            $table->string('primary_color')->nullable()->after('logo');
            $table->string('secondary_color')->nullable()->after('primary_color');
            $table->string('text_color')->default('#FFFFFF')->after('secondary_color');
            $table->text('description')->nullable()->after('name');
            $table->string('cover_path')->nullable()->after('logo');
            $table->json('identity_settings')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'text_color', 'description', 'cover_path', 'identity_settings']);
        });
    }
};
