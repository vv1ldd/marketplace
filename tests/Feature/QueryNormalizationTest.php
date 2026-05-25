<?php

namespace Tests\Feature;

use App\Models\QueryNormalizationRule;
use App\Models\QueryNormalizationSuggestion;
use App\Models\CatalogSearchLog;
use App\Services\QueryNormalizationService;
use App\Services\CatalogQueryUnderstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_normalize_slang_and_abbreviations_using_word_boundaries(): void
    {
        /** @var QueryNormalizationService $service */
        $service = app(QueryNormalizationService::class);

        // Seed some rules
        QueryNormalizationRule::create([
            'match_type' => 'slang',
            'source' => 'стим',
            'target' => 'steam',
            'priority' => 10,
        ]);

        QueryNormalizationRule::create([
            'match_type' => 'abbreviation',
            'source' => 'псн',
            'target' => 'psn',
            'priority' => 10,
        ]);

        QueryNormalizationRule::create([
            'match_type' => 'alias',
            'source' => 'плойка',
            'target' => 'playstation',
            'priority' => 20, // higher priority
        ]);

        QueryNormalizationRule::create([
            'match_type' => 'alias',
            'source' => 'турция',
            'target' => 'turkey',
            'priority' => 10,
        ]);

        // Test basic replacement
        $this->assertEquals('steam', $service->normalize('стим'));
        $this->assertEquals('steam turkey', $service->normalize('стим турция'));
        
        // Test unicode-safe boundary matches: shouldn't replace substrings
        $this->assertEquals('стимуляция', $service->normalize('стимуляция'));
        $this->assertEquals('steam и psn', $service->normalize('стим и псн'));

        // Test priority ordering
        QueryNormalizationRule::create([
            'match_type' => 'synonym',
            'source' => 'псн турция',
            'target' => 'playstation turkey gold',
            'priority' => 30, // even higher priority
        ]);

        $this->assertEquals('playstation turkey gold', $service->normalize('псн турция'));
    }

    public function test_suggestion_engine_automatically_generates_candidates(): void
    {
        /** @var QueryNormalizationService $service */
        $service = app(QueryNormalizationService::class);

        $this->assertEquals(0, QueryNormalizationSuggestion::count());

        // Trigger suggestion scanning
        $service->generateSuggestion('стим');

        // Check suggestion created for steam
        $this->assertDatabaseHas('query_normalization_suggestions', [
            'source' => 'стим',
            'target' => 'Steam',
            'status' => 'pending',
        ]);

        $this->assertEquals(1, QueryNormalizationSuggestion::count());
    }

    public function test_query_understanding_integrates_canonical_layer(): void
    {
        // Seed translation rules
        QueryNormalizationRule::create([
            'match_type' => 'slang',
            'source' => 'стим',
            'target' => 'steam',
            'priority' => 10,
        ]);

        QueryNormalizationRule::create([
            'match_type' => 'alias',
            'source' => 'турция',
            'target' => 'turkey',
            'priority' => 10,
        ]);

        /** @var CatalogQueryUnderstandingService $understandingService */
        $understandingService = app(CatalogQueryUnderstandingService::class);

        // Understand a slang query: "стим турция 50"
        $result = $understandingService->understand('стим турция 50');

        $this->assertEquals('стим турция 50', $result['original_query']);
        $this->assertEquals('steam turkey 50', $result['canonical_query']);
        
        // Assert that downstream detectors parsed filters successfully based on the canonicalized query!
        $filters = (array) $result['filters'];
        $this->assertEquals('Steam', $filters['brand'] ?? null);
        $this->assertEquals('turkey', $filters['region'] ?? null);
    }
}
