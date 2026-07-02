<?php

namespace Tests\Unit;

use App\Services\CatalogQueryLexiconService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogSearchLexiconTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_expand_search_token_includes_russian_playstation_synonyms(): void
    {
        /** @var CatalogQueryLexiconService $lexicon */
        $lexicon = app(CatalogQueryLexiconService::class);

        $expanded = $lexicon->expandSearchToken('плейстейшн');

        $this->assertContains('playstation', $expanded);
        $this->assertContains('psn', $expanded);
    }

    public function test_intents_matching_token_detects_stream_from_russian_subscription(): void
    {
        /** @var CatalogQueryLexiconService $lexicon */
        $lexicon = app(CatalogQueryLexiconService::class);

        $this->assertContains('stream', $lexicon->intentsMatchingToken('подписка'));
        $this->assertContains('stream', $lexicon->intentsMatchingToken('spotify'));
    }

    public function test_categories_matching_token_detects_gift_card_keywords(): void
    {
        /** @var CatalogQueryLexiconService $lexicon */
        $lexicon = app(CatalogQueryLexiconService::class);

        $this->assertContains('gift_cards', $lexicon->categoriesMatchingToken('giftcard'));
        $this->assertContains('gift_cards', $lexicon->categoriesMatchingToken('подарочная'));
    }

    public function test_brands_matching_token_merges_intent_corridor_overrides(): void
    {
        /** @var CatalogQueryLexiconService $lexicon */
        $lexicon = app(CatalogQueryLexiconService::class);

        $this->assertContains('Netflix', $lexicon->brandsMatchingToken('netflix'));
        $this->assertContains('PlayStation', $lexicon->brandsMatchingToken('psn'));
        $this->assertContains('Amazon', $lexicon->brandsMatchingToken('amazon'));
    }
}
