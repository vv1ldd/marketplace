<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $indexes = Schema::getIndexes('users');
            $hasEmailUnique = collect($indexes)->contains('name', 'users_email_unique');
            
            if ($hasEmailUnique) {
                $table->dropUnique('users_email_unique');
            }
            
            // Alter columns to TEXT for encrypted payload
            $table->text('email')->nullable()->change();
            $table->text('phone')->nullable()->change();
            $table->text('first_name')->nullable()->change();
            $table->text('last_name')->nullable()->change();
            
            // Add Blind Index columns
            $table->string('email_bidx', 64)->nullable()->unique()->after('email');
            $table->string('phone_bidx', 64)->nullable()->index()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_bidx', 'phone_bidx']);
            
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });
    }
};
