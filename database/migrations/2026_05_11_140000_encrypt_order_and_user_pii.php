<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Orders and OrderItems: Encrypt JSON blobs using Schema builder or direct SQL
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('info')->nullable()->change();
            });
            Schema::table('order_items', function (Blueprint $table) {
                $table->text('client_info')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE orders MODIFY info TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE order_items MODIFY client_info TEXT DEFAULT NULL');
        }

        // 2. Add Blind Indices for names in Users, Sellers, Customers
        foreach (['users', 'sellers', 'customers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'first_name_bidx')) {
                    $table->string('first_name_bidx', 64)->nullable()->after('first_name')->index();
                }
                if (!Schema::hasColumn($tableName, 'last_name_bidx')) {
                    $table->string('last_name_bidx', 64)->nullable()->after('last_name')->index();
                }
                if (!Schema::hasColumn($tableName, 'middle_name_bidx')) {
                    $table->string('middle_name_bidx', 64)->nullable()->after('middle_name')->index();
                }
                
                // Ensure name columns are TEXT to support encrypted blobs
                $table->text('first_name')->nullable()->change();
                $table->text('last_name')->nullable()->change();
                $table->text('middle_name')->nullable()->change();
            });
        }

        // 3. Migrate data
        $vault = app(\App\Services\VaultTransitService::class);

        // Orders
        DB::table('orders')->chunkById(500, function ($orders) use ($vault) {
            foreach ($orders as $order) {
                if ($order->info && !str_starts_with($order->info, 'vault:')) {
                    DB::table('orders')->where('id', $order->id)->update([
                        'info' => $vault->encrypt($order->info)
                    ]);
                }
            }
        });

        // Order Items
        DB::table('order_items')->chunkById(500, function ($items) use ($vault) {
            foreach ($items as $item) {
                if ($item->client_info && !str_starts_with($item->client_info, 'vault:')) {
                    DB::table('order_items')->where('id', $item->id)->update([
                        'client_info' => $vault->encrypt($item->client_info)
                    ]);
                }
            }
        });

        // Users, Sellers, Customers Names
        foreach (['users', 'sellers', 'customers'] as $tableName) {
            DB::table($tableName)->chunkById(500, function ($records) use ($vault, $tableName) {
                foreach ($records as $record) {
                    $updates = [];
                    foreach (['first_name', 'last_name', 'middle_name'] as $field) {
                        $val = $record->$field;
                        if ($val && !str_starts_with($val, 'vault:')) {
                            $updates[$field] = $vault->encrypt($val);
                            $updates[$field . '_bidx'] = $vault->computeBlindIndex($val);
                        } elseif ($val && str_starts_with($val, 'vault:')) {
                            // If already encrypted but bidx is missing, we need to decrypt first to compute bidx
                            try {
                                $decrypted = $vault->decrypt($val);
                                $updates[$field . '_bidx'] = $vault->computeBlindIndex($decrypted);
                            } catch (\Exception $e) {}
                        }
                    }
                    if (!empty($updates)) {
                        DB::table($tableName)->where('id', $record->id)->update($updates);
                    }
                }
            });
        }
    }

    public function down(): void
    {
    }
};
