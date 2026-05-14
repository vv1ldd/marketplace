<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->string('name_es')->nullable()->after('name_en');
        });

        $names = [
            'GLOBAL' => 'Global',        'US' => 'Estados Unidos',  'RU' => 'Rusia',
            'TR' => 'Turquía',           'DE' => 'Alemania',        'FR' => 'Francia',
            'GB' => 'Reino Unido',       'IT' => 'Italia',          'ES' => 'España',
            'PL' => 'Polonia',           'UA' => 'Ucrania',         'IN' => 'India',
            'BR' => 'Brasil',            'AR' => 'Argentina',       'MX' => 'México',
            'CA' => 'Canadá',            'AU' => 'Australia',       'JP' => 'Japón',
            'KR' => 'Corea del Sur',     'CN' => 'China',           'AE' => 'Emiratos Árabes',
            'SA' => 'Arabia Saudita',    'EG' => 'Egipto',          'ZA' => 'Sudáfrica',
            'NG' => 'Nigeria',           'PK' => 'Pakistán',        'ID' => 'Indonesia',
            'MY' => 'Malasia',           'SG' => 'Singapur',        'TH' => 'Tailandia',
            'VN' => 'Vietnam',           'PH' => 'Filipinas',       'NL' => 'Países Bajos',
            'BE' => 'Bélgica',           'SE' => 'Suecia',          'NO' => 'Noruega',
            'DK' => 'Dinamarca',         'FI' => 'Finlandia',       'CH' => 'Suiza',
            'AT' => 'Austria',           'PT' => 'Portugal',        'GR' => 'Grecia',
            'CZ' => 'República Checa',   'HU' => 'Hungría',         'RO' => 'Rumanía',
            'GE' => 'Georgia',           'AM' => 'Armenia',         'AZ' => 'Azerbaiyán',
            'KZ' => 'Kazajistán',        'UZ' => 'Uzbekistán',      'BY' => 'Bielorrusia',
            'IL' => 'Israel',            'NZ' => 'Nueva Zelanda',   'CL' => 'Chile',
            'CO' => 'Colombia',          'PE' => 'Perú',            'HK' => 'Hong Kong',
            'TW' => 'Taiwán',            'QA' => 'Catar',           'KW' => 'Kuwait',
            'VE' => 'Venezuela',         'EC' => 'Ecuador',         'BO' => 'Bolivia',
            'PY' => 'Paraguay',          'UY' => 'Uruguay',         'CR' => 'Costa Rica',
            'PA' => 'Panamá',            'DO' => 'Rep. Dominicana', 'GT' => 'Guatemala',
            'HN' => 'Honduras',          'SV' => 'El Salvador',     'NI' => 'Nicaragua',
        ];

        foreach ($names as $code => $nameEs) {
            \DB::table('mapping_countries')->where('code', $code)->update(['name_es' => $nameEs]);
        }
    }

    public function down(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->dropColumn('name_es');
        });
    }
};
