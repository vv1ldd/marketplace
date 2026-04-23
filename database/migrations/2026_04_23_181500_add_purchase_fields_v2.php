<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'purchase_status')) {
                $table->string('purchase_status')->default('none')->after('is_activated');
            }
            if (!Schema::hasColumn('order_items', 'purchase_error')) {
                $table->text('purchase_error')->nullable()->after('purchase_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_status', 'purchase_error']);
        });
    }
};
