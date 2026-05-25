<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Shops: Encrypt Credentials
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('shops', function (Blueprint $table) {
                $table->text('api_key')->nullable()->change();
                $table->text('client_secret')->nullable()->change();
                $table->text('woo_consumer_secret')->nullable()->change();
                $table->text('smtp_password')->nullable()->change();
                $table->text('telegram_bot_token')->nullable()->change();
                $table->text('notification_token')->nullable()->change();
                $table->text('import_token')->nullable()->change();
            });
            Schema::table('order_comments', function (Blueprint $table) {
                $table->text('comment')->nullable()->change();
            });
            Schema::table('ticket_messages', function (Blueprint $table) {
                $table->text('message')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE shops MODIFY api_key TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE shops MODIFY client_secret TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE shops MODIFY woo_consumer_secret TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE shops MODIFY smtp_password TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE shops MODIFY telegram_bot_token TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE shops MODIFY notification_token TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE shops MODIFY import_token TEXT DEFAULT NULL');

            // 2. Communications: Encrypt Comments and Messages
            DB::statement('ALTER TABLE order_comments MODIFY comment TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE ticket_messages MODIFY message TEXT DEFAULT NULL');
        }

        $vault = app(\App\Services\VaultTransitService::class);

        // Migrate Shops
        DB::table('shops')->chunkById(100, function ($shops) use ($vault) {
            foreach ($shops as $shop) {
                $updates = [];
                foreach ([
                    'api_key', 'client_secret', 'woo_consumer_secret', 
                    'smtp_password', 'telegram_bot_token', 
                    'notification_token', 'import_token'
                ] as $field) {
                    if ($shop->$field && !str_starts_with($shop->$field, 'vault:')) {
                        $updates[$field] = $vault->encrypt($shop->$field);
                    }
                }
                if (!empty($updates)) {
                    DB::table('shops')->where('id', $shop->id)->update($updates);
                }
            }
        });

        // Migrate Order Comments
        DB::table('order_comments')->chunkById(500, function ($comments) use ($vault) {
            foreach ($comments as $c) {
                if ($commentVal = ($c->comment ?? null)) {
                    if (!str_starts_with($commentVal, 'vault:')) {
                        DB::table('order_comments')->where('id', $c->id)->update([
                            'comment' => $vault->encrypt($commentVal)
                        ]);
                    }
                }
            }
        });

        // Migrate Ticket Messages
        DB::table('ticket_messages')->chunkById(500, function ($messages) use ($vault) {
            foreach ($messages as $m) {
                if ($msgVal = ($m->message ?? null)) {
                    if (!str_starts_with($msgVal, 'vault:')) {
                        DB::table('ticket_messages')->where('id', $m->id)->update([
                            'message' => $vault->encrypt($msgVal)
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // One-way hardening
    }
};
