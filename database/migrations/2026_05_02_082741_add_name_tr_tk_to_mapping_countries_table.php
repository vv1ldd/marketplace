<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->string('name_tr')->nullable()->after('name_es');
            $table->string('name_tk')->nullable()->after('name_tr');
        });

        // Turkish names
        $tr = [
            'GLOBAL' => 'Global',           'US' => 'ABD',
            'RU' => 'Rusya',                'TR' => 'Türkiye',
            'DE' => 'Almanya',              'FR' => 'Fransa',
            'GB' => 'İngiltere',            'IT' => 'İtalya',
            'ES' => 'İspanya',              'PL' => 'Polonya',
            'UA' => 'Ukrayna',              'IN' => 'Hindistan',
            'BR' => 'Brezilya',             'AR' => 'Arjantin',
            'MX' => 'Meksika',              'CA' => 'Kanada',
            'AU' => 'Avustralya',           'JP' => 'Japonya',
            'KR' => 'Güney Kore',           'CN' => 'Çin',
            'AE' => 'BAE',                  'SA' => 'Suudi Arabistan',
            'EG' => 'Mısır',                'ZA' => 'Güney Afrika',
            'NG' => 'Nijerya',              'PK' => 'Pakistan',
            'ID' => 'Endonezya',            'MY' => 'Malezya',
            'SG' => 'Singapur',             'TH' => 'Tayland',
            'VN' => 'Vietnam',              'PH' => 'Filipinler',
            'NL' => 'Hollanda',             'BE' => 'Belçika',
            'SE' => 'İsveç',                'NO' => 'Norveç',
            'DK' => 'Danimarka',            'FI' => 'Finlandiya',
            'CH' => 'İsviçre',              'AT' => 'Avusturya',
            'PT' => 'Portekiz',             'GR' => 'Yunanistan',
            'GE' => 'Gürcistan',            'AM' => 'Ermenistan',
            'AZ' => 'Azerbaycan',           'KZ' => 'Kazakistan',
            'UZ' => 'Özbekistan',           'TM' => 'Türkmenistan',
            'BY' => 'Belarus',              'IL' => 'İsrail',
            'NZ' => 'Yeni Zelanda',         'CL' => 'Şili',
            'CO' => 'Kolombiya',            'PE' => 'Peru',
            'HK' => 'Hong Kong',            'TW' => 'Tayvan',
            'QA' => 'Katar',                'KW' => 'Kuveyt',
        ];

        // Turkmen names
        $tk = [
            'GLOBAL' => 'Global',           'US' => 'ABŞ',
            'RU' => 'Russiýa',              'TR' => 'Türkiýe',
            'DE' => 'Germaniýa',            'FR' => 'Fransiýa',
            'GB' => 'Angliýa',              'IT' => 'Italiýa',
            'ES' => 'Ispaniýa',             'PL' => 'Polşa',
            'UA' => 'Ukraina',              'IN' => 'Hindistan',
            'BR' => 'Braziliýa',            'AR' => 'Argentina',
            'MX' => 'Meksika',              'CA' => 'Kanada',
            'AU' => 'Awstraliýa',           'JP' => 'Ýaponiýa',
            'KR' => 'Günorta Koreýa',       'CN' => 'Hytaý',
            'AE' => 'BAE',                  'SA' => 'Saud Arabystany',
            'EG' => 'Müsür',                'ZA' => 'Günorta Afrika',
            'ID' => 'Indoneziýa',           'MY' => 'Malaýziýa',
            'SG' => 'Singapur',             'TH' => 'Tailand',
            'NL' => 'Niderlandiýa',         'BE' => 'Belgiýa',
            'SE' => 'Şwesiýa',              'NO' => 'Norwegiýa',
            'DK' => 'Daniýa',               'FI' => 'Finlandiýa',
            'CH' => 'Şweýsariýa',           'AT' => 'Awstriýa',
            'PT' => 'Portugaliýa',          'GR' => 'Gresiýa',
            'GE' => 'Gruziýa',              'AM' => 'Ermeniýa',
            'AZ' => 'Azerbaýjan',           'KZ' => 'Gazagystan',
            'UZ' => 'Özbegistan',           'TM' => 'Türkmenistan',
            'BY' => 'Belorussiýa',          'IL' => 'Ysraýyl',
            'CL' => 'Çili',                 'CO' => 'Kolumbiýa',
            'HK' => 'Gonkong',              'QA' => 'Katar',
        ];

        foreach ($tr as $code => $name) {
            \DB::table('mapping_countries')->where('code', $code)->update(['name_tr' => $name]);
        }
        foreach ($tk as $code => $name) {
            \DB::table('mapping_countries')->where('code', $code)->update(['name_tk' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->dropColumn(['name_tr', 'name_tk']);
        });
    }
};
