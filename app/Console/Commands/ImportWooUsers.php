<?php

namespace App\Console\Commands;

use App\Helpers\NormalizePhone;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportWooUsers extends Command
{
    protected $signature = 'import:woo-users';
    protected $description = 'Import users from WooCommerce databases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $log = Log::channel('monitor_import_woo_users')->withContext([
            'log_id' => Str::random(8),
        ]);

        $db_connection = [
            'ps_plus',
            'ps_store',
            '1gros_prod'
        ];

        foreach ($db_connection as $connection) {

            $db_connection = DB::connection($connection);

            $log->info("Импорт из: {$connection}");

            $users = DB::connection($connection)->table('wp_users')->get();

            foreach ($users as $wpUser) {

                if (empty($wpUser->user_email)) {
                    continue;
                }

                $exists = User::where('email', $wpUser->user_email)->exists();

                if ($exists) {
                    continue;
                }

                $log->debug("Новый юзер", [$wpUser]);

                $meta = $db_connection->table('wp_usermeta')
                    ->where('user_id', $wpUser->ID)
                    ->pluck('meta_value', 'meta_key');

                User::create([
                    'email' => $wpUser->user_email,
                    'first_name' => data_get($meta, 'first_name') ?? $wpUser->display_name,
                    'password' => $wpUser->user_pass,
                    'phone' => isset($meta['billing_phone']) ? NormalizePhone::normalize($meta['billing_phone']) : null,
                    'source_site' => $connection,
                    'source_user_id' => $wpUser->ID,
                    'created_at' => $wpUser->user_registered,
                ]);
            }

        }

        $this->info("Импорт завершён.");
    }
}
