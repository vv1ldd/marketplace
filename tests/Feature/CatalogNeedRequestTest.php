<?php

namespace Tests\Feature;

use App\Models\ExternalSearchQuerySignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogNeedRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_need_request_records_description_and_screenshot_signal(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/catalog/need-requests', [
            'need_key' => 'travel',
            'need_title' => 'Travel',
            'description' => 'Need hotel booking in Istanbul with prepaid balance.',
            'contact' => '@personal',
            'screenshot' => UploadedFile::fake()->image('hotel-reference.png', 800, 600),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('contract.name', 'catalog-need-request')
            ->assertJsonPath('success', true);

        $signal = ExternalSearchQuerySignal::query()->firstOrFail();
        $this->assertSame('catalog_need_request', $signal->source);
        $this->assertSame('travel', data_get($signal->metadata, 'need_key'));
        $this->assertSame('@personal', data_get($signal->metadata, 'contact'));
        $this->assertStringContainsString('hotel booking', $signal->query);
        Storage::disk('public')->assertExists(data_get($signal->metadata, 'screenshot_path'));
    }
}
