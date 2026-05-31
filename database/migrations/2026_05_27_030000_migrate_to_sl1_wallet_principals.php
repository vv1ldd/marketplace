<?php

use App\Models\LegalEntity;
use App\Models\User;
use App\Services\VaultTransitService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'entity_l1_address')) {
                $table->text('entity_l1_address')->nullable()->after('meta');
            }
            if (! Schema::hasColumn('users', 'entity_l1_address_bidx')) {
                $table->string('entity_l1_address_bidx', 64)->nullable()->unique()->after('entity_l1_address');
            }
            if (! Schema::hasColumn('users', 'key_l1_address')) {
                $table->text('key_l1_address')->nullable()->after('entity_l1_address_bidx');
            }
            if (! Schema::hasColumn('users', 'key_l1_address_bidx')) {
                $table->string('key_l1_address_bidx', 64)->nullable()->index()->after('key_l1_address');
            }
            if (! Schema::hasColumn('users', 'identity_provider')) {
                $table->string('identity_provider')->nullable()->after('key_l1_address_bidx');
            }
        });

        $this->backfillUserWalletIdentities();
        $this->backfillCanonicalLegalEntityEmail();

        $this->dropIndexIfExists('users', 'users_email_unique');
        $this->dropIndexIfExists('users', 'users_email_bidx_unique');
        $this->dropIndexIfExists('users', 'users_email_bidx_index');
        $this->dropIndexIfExists('sellers', 'sellers_email_unique');
        $this->dropIndexIfExists('sellers', 'sellers_email_bidx_unique');
        $this->dropIndexIfExists('sellers', 'sellers_email_bidx_index');

        Schema::table('users', function (Blueprint $table) {
            $this->dropColumnsIfPresent($table, 'users', [
                'email',
                'email_bidx',
                'email_verified_at',
                'password',
                'password_login_enabled',
                'remember_token',
            ]);
        });

        Schema::table('sellers', function (Blueprint $table) {
            $this->dropColumnsIfPresent($table, 'sellers', [
                'email',
                'email_bidx',
                'email_verified_at',
                'password',
                'password_login_enabled',
                'remember_token',
            ]);
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email')) {
                $table->text('email')->nullable()->after('avatar');
            }
            if (! Schema::hasColumn('users', 'email_bidx')) {
                $table->string('email_bidx', 64)->nullable()->unique()->after('email');
            }
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email_bidx');
            }
            if (! Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'password_login_enabled')) {
                $table->boolean('password_login_enabled')->default(false)->after('password');
            }
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });

        Schema::table('sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('sellers', 'email')) {
                $table->text('email')->nullable()->after('middle_name');
            }
            if (! Schema::hasColumn('sellers', 'email_bidx')) {
                $table->string('email_bidx', 64)->nullable()->unique()->after('email');
            }
            if (! Schema::hasColumn('sellers', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email_bidx');
            }
            if (! Schema::hasColumn('sellers', 'password')) {
                $table->string('password')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('sellers', 'password_login_enabled')) {
                $table->boolean('password_login_enabled')->default(false)->after('password');
            }
            if (! Schema::hasColumn('sellers', 'remember_token')) {
                $table->rememberToken();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $this->dropColumnsIfPresent($table, 'users', [
                'entity_l1_address',
                'entity_l1_address_bidx',
                'key_l1_address',
                'key_l1_address_bidx',
                'identity_provider',
            ]);
        });
    }

    private function backfillUserWalletIdentities(): void
    {
        User::withoutEvents(function (): void {
            User::query()->select(['id', 'meta'])->orderBy('id')->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $entityAddress = data_get($user->meta, 'entity_l1_address')
                        ?: data_get($user->meta, 'l1_address');
                    $keyAddress = data_get($user->meta, 'key_l1_address');

                    if (! is_string($entityAddress) || preg_match('/^sl1e_[a-f0-9]{39}$/i', $entityAddress) !== 1) {
                        continue;
                    }

                    $user->forceFill([
                        'entity_l1_address' => strtolower($entityAddress),
                        'key_l1_address' => is_string($keyAddress) ? strtolower($keyAddress) : null,
                        'identity_provider' => data_get($user->meta, 'simple_l1.identity_provider', 'local'),
                    ])->saveQuietly();
                }
            });
        });
    }

    private function backfillCanonicalLegalEntityEmail(): void
    {
        $vault = app(VaultTransitService::class);

        LegalEntity::withoutEvents(function () use ($vault): void {
            LegalEntity::query()->with('seller')->orderBy('id')->chunkById(100, function ($entities) use ($vault): void {
                foreach ($entities as $entity) {
                    $metadata = $entity->agreement_metadata ?? [];
                    $businessEmail = data_get($metadata, 'business_email');

                    if (! is_string($businessEmail) || trim($businessEmail) === '') {
                        $sellerEmail = $entity->seller_id
                            ? DB::table('sellers')->where('id', $entity->seller_id)->value('email')
                            : null;
                        $businessEmail = is_string($sellerEmail) ? $vault->decrypt($sellerEmail) : null;
                    }

                    if (is_string($businessEmail) && trim($businessEmail) !== '' && blank($entity->email)) {
                        $entity->email = mb_strtolower(trim($businessEmail));
                    }

                    if (array_key_exists('business_email', $metadata)) {
                        unset($metadata['business_email']);
                        $entity->agreement_metadata = $metadata;
                    }

                    if ($entity->isDirty()) {
                        $entity->saveQuietly();
                    }
                }
            });
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        $indexes = collect(Schema::getIndexes($tableName));
        if (! $indexes->contains(fn (array $index) => ($index['name'] ?? null) === $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    /**
     * @param array<int, string> $columns
     */
    private function dropColumnsIfPresent(Blueprint $table, string $tableName, array $columns): void
    {
        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column),
        ));

        if ($existing !== []) {
            $table->dropColumn($existing);
        }
    }
};
