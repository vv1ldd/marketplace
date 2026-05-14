<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;

class MigrateVaultPii extends Command
{
    protected $signature = 'vault:migrate-pii 
                            {--model=all : Which model to process: all, users, customers, order_items, orders, sellers}
                            {--chunk=500 : Chunk size for batch processing}';

    protected $description = 'Encrypt existing plaintext PII in the database and generate blind indexes';

    public function handle(): void
    {
        $model = $this->option('model');
        $chunk = (int) $this->option('chunk');

        if (in_array($model, ['all', 'users'])) {
            $this->migrateModel('Users', \App\Models\User::class, $chunk, ['email', 'phone', 'first_name', 'last_name', 'middle_name']);
        }

        if (in_array($model, ['all', 'customers'])) {
            $this->migrateModel('Customers', \App\Models\Customer::class, $chunk, ['email', 'phone', 'first_name', 'last_name', 'middle_name']);
        }

        if (in_array($model, ['all', 'sellers'])) {
            $this->migrateModel('Sellers', \App\Models\Seller::class, $chunk, ['email', 'phone', 'first_name', 'last_name', 'middle_name']);
        }

        if (in_array($model, ['all', 'order_items'])) {
            $this->migrateModel('Order Items', \App\Models\Order\OrderItems::class, $chunk, ['key', 'original_code']);
        }

        if (in_array($model, ['all', 'orders'])) {
            $this->migrateJsonModel('Orders (client_info)', \App\Models\Order\Order::class, $chunk, 'client_info');
        }

        $this->info('✅ Vault PII Migration complete.');
    }

    protected function migrateModel(string $label, string $modelClass, int $chunk, array $fields): void
    {
        $total = $modelClass::count();
        $this->info("Starting [{$label}] — {$total} records, fields: [" . implode(', ', $fields) . "]...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $encryptedCount = 0;
        $errorCount = 0;

        $modelClass::chunk($chunk, function ($records) use (&$encryptedCount, &$errorCount, $bar, $fields) {
            foreach ($records as $record) {
                $needsSave = false;

                foreach ($fields as $field) {
                    $raw = $record->getRawOriginal($field);

                    // Only encrypt if data exists and is NOT already a vault ciphertext
                    if (!empty($raw) && !str_starts_with((string)$raw, 'vault:')) {
                        $record->$field = $raw;
                        $needsSave = true;
                    }
                }

                if ($needsSave) {
                    try {
                        $record->save();
                        $encryptedCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->newLine();
                        $this->error("Failed ID {$record->id}: " . $e->getMessage());
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("  Encrypted: {$encryptedCount} | Skipped: " . ($total - $encryptedCount - $errorCount) . " | Errors: {$errorCount}");
    }

    protected function migrateJsonModel(string $label, string $modelClass, int $chunk, string $field): void
    {
        $total = $modelClass::count();
        $this->info("Starting [{$label}] — {$total} records, JSON field: [{$field}]...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $encryptedCount = 0;
        $errorCount = 0;

        $modelClass::chunk($chunk, function ($records) use (&$encryptedCount, &$errorCount, $bar, $field) {
            foreach ($records as $record) {
                $raw = $record->getRawOriginal($field);

                // Only process if not already encrypted
                if (!empty($raw) && !str_starts_with((string)$raw, 'vault:')) {
                    // Decode existing JSON to pass through the VaultEncryptedJson cast
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && count($decoded) > 0) {
                        try {
                            $record->$field = $decoded;
                            $record->save();
                            $encryptedCount++;
                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->newLine();
                            $this->error("Failed ID {$record->id}: " . $e->getMessage());
                        }
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("  Encrypted: {$encryptedCount} | Skipped: " . ($total - $encryptedCount - $errorCount) . " | Errors: {$errorCount}");
    }
}
