<?php

namespace App\Actions\Yandex;

use App\Models\Shop;
use App\Services\LedgerService;

class RecordYandexLegalTrustSignalAction
{
    public function execute(Shop $shop, string $tier, array $context = []): void
    {
        $eventType = match ($tier) {
            'attention' => 'YANDEX_MARKET_LEGAL_ATTENTION',
            'rejected' => 'YANDEX_MARKET_LEGAL_REJECTED',
            default => null,
        };

        if ($eventType === null) {
            return;
        }

        $attentionScoreDelta = match ($tier) {
            'attention' => 5,
            'rejected' => 15,
            default => 0,
        };

        try {
            app(LedgerService::class)->record(
                $shop,
                $eventType,
                $shop,
                [
                    'attention_score_delta' => $attentionScoreDelta,
                    'verification_tier' => $tier,
                    'shop_name' => $shop->name,
                    'business_id' => $shop->business_id,
                    'campaign_id' => $shop->campaign_id,
                    ...$context,
                ],
                $shop->legalEntity
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
