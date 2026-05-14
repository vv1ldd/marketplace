<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $indexes = Schema::getIndexes('customers');
            $indexNames = collect($indexes)->pluck('name');

            // Drop existing plain indexes on email/phone before changing type to TEXT
            if ($indexNames->contains('customers_email_index')) {
                $table->dropIndex('customers_email_index');
            }
            if ($indexNames->contains('customers_phone_index')) {
                $table->dropIndex('customers_phone_index');
            }
            // Also drop unique if it somehow exists
            if ($indexNames->contains('customers_email_unique')) {
                $table->dropUnique('customers_email_unique');
            }

            // Alter PII columns to TEXT to hold encrypted Vault payloads
            $table->text('email')->nullable()->change();
            $table->text('phone')->nullable()->change();
            $table->text('first_name')->nullable()->change();
            $table->text('last_name')->nullable()->change();
            $table->text('middle_name')->nullable()->change();

            // Add Blind Index columns for deterministic search
            $table->string('email_bidx', 64)->nullable()->index()->after('email');
            $table->string('phone_bidx', 64)->nullable()->index()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email_bidx', 'phone_bidx']);

            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
            $table->string('middle_name')->nullable()->change();
        });
    }
};
