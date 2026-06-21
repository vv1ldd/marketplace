<?php

namespace Tests\Unit;

use App\Models\MappingCountry;
use App\Services\MappingCountryLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MappingCountryLabelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_localized_label_uses_english_name_on_global_locale(): void
    {
        MappingCountry::query()->updateOrCreate(
            ['code' => 'ZZ'],
            ['name_en' => 'Testland', 'name_ru' => 'Тестланд'],
        );

        Cache::forget('mapping_country_label_index_v3');
        app()->setLocale('en');

        $this->assertSame(
            'SAUDI ARABIA',
            app(MappingCountryLabelService::class)->localizedLabel('SA'),
        );
        $this->assertSame(
            'UAE',
            app(MappingCountryLabelService::class)->localizedLabel('AE'),
        );
        $this->assertSame(
            'IRAQ',
            app(MappingCountryLabelService::class)->localizedLabel('Ирак'),
        );
        $this->assertSame(
            'ALGERIA',
            app(MappingCountryLabelService::class)->localizedLabel('Алжир'),
        );
        $this->assertSame(
            'IRELAND',
            app(MappingCountryLabelService::class)->localizedLabel('Ирландия'),
        );
        $this->assertSame(
            'OMAN',
            app(MappingCountryLabelService::class)->localizedLabel('Оман'),
        );
        $this->assertSame(
            'SLOVAKIA',
            app(MappingCountryLabelService::class)->localizedLabel('Словакия'),
        );
    }

    public function test_localized_label_uses_russian_name_on_ru_locale(): void
    {
        MappingCountry::query()->updateOrCreate(
            ['code' => 'ZZ'],
            ['name_en' => 'Testland', 'name_ru' => 'Тестланд'],
        );

        Cache::forget('mapping_country_label_index_v3');
        app()->setLocale('ru');

        $this->assertSame(
            'ТЕСТЛАНД',
            app(MappingCountryLabelService::class)->localizedLabel('Testland'),
        );
    }
}
