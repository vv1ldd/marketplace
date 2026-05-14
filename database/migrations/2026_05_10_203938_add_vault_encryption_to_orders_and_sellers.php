<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders.client_info: expand to LONGTEXT for encrypted JSON payload
        Schema::table('orders', function (Blueprint $table) {
            $table->longText('client_info')->nullable()->change();
        });

        // sellers: same treatment as users (email, phone, name fields)
        Schema::table('sellers', function (Blueprint $table) {
            $indexes = Schema::getIndexes('sellers');
            $indexNames = collect($indexes)->pluck('name');

            if ($indexNames->contains('sellers_email_unique')) {
                $table->dropUnique('sellers_email_unique');
            }
            if ($indexNames->contains('sellers_email_index')) {
                $table->dropIndex('sellers_email_index');
            }
            if ($indexNames->contains('sellers_phone_unique')) {
                $table->dropUnique('sellers_phone_unique');
            }
            if ($indexNames->contains('sellers_phone_index')) {
                $table->dropIndex('sellers_phone_index');
            }

            $table->text('email')->nullable()->change();
            $table->text('phone')->nullable()->change();
            $table->text('first_name')->nullable()->change();
            $table->text('last_name')->nullable()->change();
            $table->text('middle_name')->nullable()->change();

            $table->string('email_bidx', 64)->nullable()->unique()->after('email');
            $table->string('phone_bidx', 64)->nullable()->index()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('client_info')->nullable()->change();
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['email_bidx', 'phone_bidx']);
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
            $table->string('middle_name')->nullable()->change();
        });
    }
};
