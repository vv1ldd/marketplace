<?php

namespace App\Console\Commands\PlayStation;

use App\Models\PlayStation\PlayStationAlt;
use App\Models\PlayStation\PlayStationRegion;
use App\Models\Product;
use Illuminate\Console\Command;

class SyncPsUsBundles extends Command
{
    protected $signature = 'ps:sync-us-bundles';

    protected $description = 'Create product bundles for PlayStation US using available USD Gift Cards';

    public function handle()
    {
        $region = PlayStationRegion::where('slug', 'US')->first();
        if (!$region) {
            $this->error("Region US not found in play_station_regions table.");
            return;
        }

        $this->info("Fetching US PlayStation products...");

        $items = PlayStationAlt::where('region_id', $region->id)
            ->where('price_with_discount', '>', 0)
            ->get();

        if ($items->isEmpty()) {
            $this->warn("No items found for US region.");
            return;
        }

        $this->info("Fetching available USD Gift Cards...");
        $giftCards = Product::where('category', 'gift-card')
            ->where('purchase_currency', 'USD')
            ->where('is_active', true)
            ->get();

        if ($giftCards->isEmpty()) {
            $this->error("No USD Gift Cards found in products table. Sync Wildflow first!");
            return;
        }

        // Sort gift cards by face value (extracted from data or name)
        // For Wildflow, we can try to extract from name or data.
        $availableCards = [];
        foreach ($giftCards as $card) {
            $data = json_decode($card->data, true);
            // Wildflow 'data.price' is usually the face value in dollars
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

        $products = [];
        foreach ($items as $item) {
            $gamePrice = $item->price_with_discount / 100; // Assuming stored in cents
            if ($gamePrice <= 0) continue;

            $bundle = $this->calculateBundle($gamePrice, $availableCards);
            
            if (!$bundle) {
                $this->warn("Could not find a card combination for {$item->name} ($$gamePrice)");
                continue;
            }

            // Extract description
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

            $products[] = [
                'sku' => 'PS-US-BUNDLE-' . $item->sku,
                'name' => "[US BUNDLE] " . $item->name . " ($" . $gamePrice . ")",
                'description' => "Данный товар является набором подарочных карт (USD) для покупки игры {$item->name} в американском регионе PlayStation Store.\n\n" . mb_substr(strip_tags($description), 0, 2500),
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
                'created_at' => now(),
            ];
        }

        $this->info("Upserting " . count($products) . " bundles...");
        Product::upsert(
            $products,
            ['sku'],
            ['name', 'description', 'price_rub', 'purchase_price', 'purchase_currency', 'base_price', 'data', 'is_active', 'updated_at']
        );

        $this->info("Successfully synced US PlayStation bundles!");
    }

    private function calculateBundle(float $target, array $cards): ?array
    {
        $remaining = $target;
        $chosenCards = [];
        $totalRub = 0;
        $totalFace = 0;

        // Simple greedy approach: take largest possible cards first
        // But we want to cover the WHOLE amount.
        // If remaining > 0 after loop, we take one more of the smallest card that covers it?
        // Better: use a small search or just greedy + final overflow
        
        $tempRemaining = $target;
        foreach ($cards as $card) {
            while ($tempRemaining >= $card['face_value']) {
                $chosenCards[] = $card['product']->sku;
                $totalRub += $card['price_rub'];
                $totalFace += $card['face_value'];
                $tempRemaining -= $card['face_value'];
            }
        }

        if ($tempRemaining > 0) {
            // Take the smallest card that is >= tempRemaining
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
                $tempRemaining = 0;
            } else {
                // If even the largest card is smaller than tempRemaining (shouldn't happen with 100s)
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
