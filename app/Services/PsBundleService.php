<?php

namespace App\Services;

use App\Models\PlayStation\PlayStationAlt;
use App\Models\Product;

class PsBundleService
{
    public function syncGameBundle(string $sku, string $regionId)
    {
        $item = PlayStationAlt::where('sku', $sku)->where('region_id', $regionId)->first();
        if (!$item || $item->price_with_discount <= 0) return;

        // Fetch available USD Gift Cards
        $giftCards = Product::where('category', 'gift-card')
            ->where('purchase_currency', 'USD')
            ->where('is_active', true)
            ->get();

        if ($giftCards->isEmpty()) return;

        $availableCards = [];
        foreach ($giftCards as $card) {
            $data = json_decode($card->data, true);
            $faceValue = (float)($data['data']['price'] ?? 0);
            if ($faceValue > 0) {
                $availableCards[] = [
                    'product' => $card,
                    'face_value' => $faceValue,
                    'price_rub' => $card->price_rub,
                ];
            }
        }

        usort($availableCards, fn($a, $b) => $b['face_value'] <=> $a['face_value']);

        $gamePrice = $item->price_with_discount / 100;
        $bundle = $this->calculateBundle($gamePrice, $availableCards);
        if (!$bundle) return;

        $psData = json_decode($item->data, true);
        $description = '';
        if (!empty($psData['descriptions'])) {
            foreach ($psData['descriptions'] as $desc) {
                if ($desc['type'] === 'LONG') {
                    $description = $desc['value'];
                    break;
                }
            }
        }

        Product::updateOrCreate(
            ['sku' => 'PS-US-BUNDLE-' . $item->sku],
            [
                'name' => "[US BUNDLE] " . ($item->name ?? $sku) . " ($" . $gamePrice . ")",
                'description' => "Данный товар является набором подарочных карт (USD) для покупки игры " . ($item->name ?? $sku) . " в американском регионе PlayStation Store.\n\n" . mb_substr(strip_tags($description), 0, 2500),
                'type' => 'playstation',
                'category' => 'game',
                'price_rub' => $bundle['total_rub'],
                'purchase_price' => $bundle['total_face_value'] * 100,
                'purchase_currency' => 'USD',
                'base_price' => $gamePrice * 100,
                'data' => json_encode([
                    'is_bundle' => true,
                    'original_sku' => $item->sku,
                    'original_price' => $gamePrice,
                    'cards' => $bundle['card_skus'],
                ]),
                'is_active' => true,
                'updated_at' => now(),
            ]
        );
    }

    private function calculateBundle(float $target, array $cards): ?array
    {
        $tempRemaining = $target;
        $chosenCards = [];
        $totalRub = 0;
        $totalFace = 0;

        foreach ($cards as $card) {
            while ($tempRemaining >= $card['face_value']) {
                $chosenCards[] = $card['product']->sku;
                $totalRub += $card['price_rub'];
                $totalFace += $card['face_value'];
                $tempRemaining -= $card['face_value'];
            }
        }

        if ($tempRemaining > 0) {
            $overflowCard = null;
            foreach (array_reverse($cards) as $card) {
                if ($card['face_value'] >= $tempRemaining) {
                    $overflowCard = $card;
                    break;
                }
            }
            if ($overflowCard) {
                $chosenCards[] = $overflowCard['product']->sku;
                $totalRub += $overflowCard['price_rub'];
                $totalFace += $overflowCard['face_value'];
            } else {
                return null;
            }
        }

        return [
            'card_skus' => $chosenCards,
            'total_rub' => $totalRub,
            'total_face_value' => $totalFace,
        ];
    }
}
