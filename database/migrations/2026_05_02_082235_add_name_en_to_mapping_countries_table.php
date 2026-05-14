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
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name_ru');
        });

        // Seed English names from ISO 3166-1 code map
        $names = [
            'GLOBAL' => 'Global',  'US' => 'United States', 'RU' => 'Russia',
            'TR' => 'Turkey',      'DE' => 'Germany',        'FR' => 'France',
            'GB' => 'United Kingdom', 'IT' => 'Italy',      'ES' => 'Spain',
            'PL' => 'Poland',      'UA' => 'Ukraine',       'IN' => 'India',
            'BR' => 'Brazil',      'AR' => 'Argentina',     'MX' => 'Mexico',
            'CA' => 'Canada',      'AU' => 'Australia',     'JP' => 'Japan',
            'KR' => 'South Korea', 'CN' => 'China',         'AE' => 'UAE',
            'SA' => 'Saudi Arabia','EG' => 'Egypt',         'ZA' => 'South Africa',
            'NG' => 'Nigeria',     'PK' => 'Pakistan',      'ID' => 'Indonesia',
            'MY' => 'Malaysia',    'SG' => 'Singapore',     'TH' => 'Thailand',
            'VN' => 'Vietnam',     'PH' => 'Philippines',   'NL' => 'Netherlands',
            'BE' => 'Belgium',     'SE' => 'Sweden',        'NO' => 'Norway',
            'DK' => 'Denmark',     'FI' => 'Finland',       'CH' => 'Switzerland',
            'AT' => 'Austria',     'PT' => 'Portugal',      'GR' => 'Greece',
            'CZ' => 'Czech Republic', 'HU' => 'Hungary',   'RO' => 'Romania',
            'GE' => 'Georgia',     'AM' => 'Armenia',       'AZ' => 'Azerbaijan',
            'KZ' => 'Kazakhstan',  'UZ' => 'Uzbekistan',    'BY' => 'Belarus',
            'IL' => 'Israel',      'NZ' => 'New Zealand',   'CL' => 'Chile',
            'CO' => 'Colombia',    'PE' => 'Peru',          'HK' => 'Hong Kong',
            'TW' => 'Taiwan',      'QA' => 'Qatar',         'KW' => 'Kuwait',
        ];

        foreach ($names as $code => $nameEn) {
            \DB::table('mapping_countries')->where('code', $code)->update(['name_en' => $nameEn]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });
    }
};
