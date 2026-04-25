<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Primary owner/manager
            
            $table->string('name')->comment('Полное наименование');
            $table->string('short_name')->nullable()->comment('Краткое наименование');
            $table->string('inn', 12)->unique()->comment('ИНН');
            $table->string('kpp', 9)->nullable()->comment('КПП');
            $table->string('ogrn', 15)->nullable()->comment('ОГРН/ОГРНИП');
            
            $table->text('legal_address')->nullable()->comment('Юридический адрес');
            $table->text('postal_address')->nullable()->comment('Почтовый адрес');
            
            // Bank details
            $table->string('bank_name')->nullable();
            $table->string('bank_bic', 9)->nullable();
            $table->string('bank_account', 20)->nullable();
            $table->string('bank_correspondent_account', 20)->nullable();
            
            $table->string('director_name')->nullable()->comment('ФИО руководителя');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Link Shops to Legal Entities
        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('legal_entity_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_entity_id');
        });
        Schema::dropIfExists('legal_entities');
    }
};
