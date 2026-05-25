<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\ProviderProduct;

class ProductIndexingPolicyService
{
    private const PUBLIC_INDEX = 'public_index';
    private const LLM_ONLY = 'llm_only';
    private const INTERNAL_REVIEW = 'internal_review';

    /**
     * @param  array<string, mixed>  $canonicalIdentity
     * @param  array<string, mixed>|null  $selectedOffer
     * @param  array<string, mixed>  $pageFacts
     * @return array<string, mixed>
     */
    public function forCanonicalProduct(
        array $canonicalIdentity,
        ?array $selectedOffer = null,
        array $pageFacts = [],
        ?CanonicalProductIdentity $identityModel = null,
    ): array {
        return [
            'indexable' => true,
            'robots' => 'index,follow',
            'surface' => self::PUBLIC_INDEX,
            'visibility' => self::PUBLIC_INDEX,
            'reasons' => ['force_all_canonical_indexable_for_seo'],
            'reason' => 'force_all_canonical_indexable_for_seo',
        ];
    }

    /**
     * @param  array<string, mixed>  $canonicalIdentity
     * @param  array<string, mixed>|null  $selectedOffer
     * @param  array<string, mixed>  $candidateFacts
     * @return array<string, mixed>
     */
    public function forProviderNetworkCandidate(
        array $canonicalIdentity,
        ?string $seoQuality,
        ?array $selectedOffer = null,
        array $candidateFacts = [],
        ?ProviderProduct $product = null,
    ): array {
        $facts = array_merge($candidateFacts, [
            'provider_seo_quality' => $seoQuality,
            'provider_product_id' => $product?->id,
        ]);

        return $this->evaluate(
            canonicalIdentity: $canonicalIdentity,
            selectedOffer: $selectedOffer,
            facts: $facts,
            allowUnknownProviderQuality: false,
        );
    }

    /**
     * @param  array<string, mixed>  $canonicalIdentity
     * @param  array<string, mixed>|null  $selectedOffer
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function evaluate(
        array $canonicalIdentity,
        ?array $selectedOffer,
        array $facts,
        bool $allowUnknownProviderQuality,
    ): array {
        $confidence = $this->confidence($canonicalIdentity);
        $signals = $this->signals($canonicalIdentity);
        $providerQuality = $this->providerQuality($facts);
        $qualityAllowsIndexing = $this->providerQualityAllowsIndexing($providerQuality)
            || ($allowUnknownProviderQuality && $providerQuality === null);
        $hasSelectedOffer = $selectedOffer !== null;
        $hasStrongSelectedOffer = $this->hasStrongSelectedOffer($selectedOffer);
        $blockingReasons = [];

        if ($confidence === 'low') {
            $blockingReasons[] = 'low_confidence_identity';
        }

        if ($providerQuality === 'noindex_candidate') {
            $blockingReasons[] = 'provider_quality_noindex_candidate';
        }

        foreach ($this->matchingSignals($signals, ['multiple_brand_tokens', 'brand_family_mismatch']) as $signal) {
            $blockingReasons[] = 'suspicious_identity_signal:'.$signal;
        }

        if (
            $this->hasSignal($signals, 'brand_not_in_title')
            && ! ($confidence === 'high' && $hasStrongSelectedOffer)
        ) {
            $blockingReasons[] = 'suspicious_identity_signal:brand_not_in_title';
        }

        if ($blockingReasons !== []) {
            return $this->policy(false, self::INTERNAL_REVIEW, $blockingReasons);
        }

        if ($confidence === 'high' && $hasSelectedOffer) {
            return $this->policy(true, self::PUBLIC_INDEX, [
                'high_confidence_identity',
                'selected_offer_available',
            ]);
        }

        if ($confidence === 'medium' && $hasStrongSelectedOffer && $qualityAllowsIndexing) {
            return $this->policy(true, self::PUBLIC_INDEX, [
                'medium_confidence_identity',
                'strong_selected_offer_available',
            ]);
        }

        if ($confidence === 'high' && $qualityAllowsIndexing) {
            return $this->policy(true, self::PUBLIC_INDEX, [
                'high_confidence_identity',
                $providerQuality === null ? 'provider_quality_not_recorded' : 'provider_quality_'.$providerQuality,
            ]);
        }

        if ($confidence === 'medium') {
            return $this->policy(false, self::LLM_ONLY, [
                'medium_confidence_identity',
                'awaiting_stronger_public_indexing_signal',
            ]);
        }

        return $this->policy(false, self::INTERNAL_REVIEW, [
            'insufficient_public_indexing_signal',
        ]);
    }

    /**
     * @param  array<int, string>  $reasons
     * @return array<string, mixed>
     */
    private function policy(bool $indexable, string $surface, array $reasons): array
    {
        $reasons = array_values(array_unique(array_filter($reasons)));

        return [
            'indexable' => $indexable,
            'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            'surface' => $surface,
            'visibility' => $surface,
            'reasons' => $reasons,
            'reason' => $reasons[0] ?? ($indexable ? 'indexable' : 'noindex'),
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function identityArray(array $identity, ?CanonicalProductIdentity $identityModel): array
    {
        if ($identityModel === null) {
            return $identity;
        }

        return array_merge([
            'database_id' => $identityModel->id,
            'confidence' => $identityModel->confidence ?: ($identity['confidence'] ?? null),
            'signals' => $identityModel->signals ?: ($identity['signals'] ?? []),
            'provider_candidates_count' => $identityModel->provider_candidates_count,
            'seller_offers_count' => $identityModel->seller_offers_count,
            'best_offer_product_id' => $identityModel->best_offer_product_id,
        ], $identity);
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function confidence(array $identity): string
    {
        $confidence = (string) ($identity['confidence'] ?? 'low');

        return in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<int, string>
     */
    private function signals(array $identity): array
    {
        return collect((array) ($identity['signals'] ?? []))
            ->flatten()
            ->map(fn ($signal) => trim((string) $signal))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function providerQuality(array $facts): ?string
    {
        $quality = data_get($facts, 'provider_seo_quality', data_get($facts, 'status.seo_quality'));
        $quality = trim((string) $quality);

        return $quality === '' ? null : $quality;
    }

    private function providerQualityAllowsIndexing(?string $quality): bool
    {
        return in_array($quality, ['ready', 'thin'], true);
    }

    /**
     * @param  array<string, mixed>|null  $selectedOffer
     */
    private function hasStrongSelectedOffer(?array $selectedOffer): bool
    {
        if ($selectedOffer === null) {
            return false;
        }

        return (bool) data_get($selectedOffer, 'indexing.indexable')
            || in_array((string) data_get($selectedOffer, 'availability'), ['in_stock', 'auto_purchase'], true)
            || (int) data_get($selectedOffer, 'ranking.score', 0) >= 35;
    }

    /**
     * @param  array<int, string>  $signals
     * @param  array<int, string>  $prefixes
     * @return array<int, string>
     */
    private function matchingSignals(array $signals, array $prefixes): array
    {
        return collect($signals)
            ->filter(fn (string $signal) => collect($prefixes)->contains(
                fn (string $prefix) => $signal === $prefix || str_starts_with($signal, $prefix.':')
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $signals
     */
    private function hasSignal(array $signals, string $prefix): bool
    {
        foreach ($signals as $signal) {
            if ($signal === $prefix || str_starts_with($signal, $prefix.':')) {
                return true;
            }
        }

        return false;
    }
}
