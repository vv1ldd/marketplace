<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seller Terminals — каждый продавец (LegalEntity) получает
     * собственный terminal_id и PIN для аутентификации на marketplace API.
     *
     * Архитектура:
     *   Seller ──[terminal_id + pin]──→ Marketplace API
     *                                        │
     *                          Marketplace master token
     *                                        ↓
     *                               api-wildflow-dev
     *                                        │
     *                             EzPin / Fazercards / ...
     */
    public function up(): void
    {
        Schema::create('seller_terminals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('legal_entity_id')
                ->constrained('legal_entities')
                ->cascadeOnDelete();

            // Публичный идентификатор терминала, например SL-202506-A3F9K1
            $table->string('terminal_id', 64)->unique();

            // Зашифрованный PIN (Laravel encrypted cast)
            $table->text('terminal_pin');

            $table->boolean('is_active')->default(true)->index();

            // Дневной лимит расходов в рублях (0 = без лимита)
            $table->decimal('daily_limit', 16, 4)->default(0);

            // Опциональный срок действия терминала
            $table->timestamp('expires_at')->nullable();

            // Аудит
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();

            $table->timestamps();

            // Быстрый поиск по terminal_id при аутентификации
            $table->index(['terminal_id', 'is_active']);
            $table->index('legal_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_terminals');
    }
};
