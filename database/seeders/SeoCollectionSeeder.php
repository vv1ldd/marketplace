<?php

namespace Database\Seeders;

use App\Models\SeoCollection;
use Illuminate\Database\Seeder;

class SeoCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $collections = [
            [
                'slug' => 'steam-turkey',
                'title' => 'Steam Turkey Gift Cards (TRY)',
                'search_query' => 'Steam Turkey',
                'meta_title' => 'Купить карты пополнения Steam Турция (TRY) | Meanly',
                'meta_description' => 'Карты пополнения кошелька Steam для турецких аккаунтов (TRY). Быстрая доставка кодов активации, лучшие цены и поддержка 24/7 на Meanly.',
                'h1' => 'Steam Турция (TRY) — Карты пополнения',
                'is_active' => true,
            ],
            [
                'slug' => 'playstation-us',
                'title' => 'PlayStation Store USA Gift Cards (USD)',
                'search_query' => 'PlayStation US',
                'meta_title' => 'Купить карты оплаты PlayStation Network (PSN) США | Meanly',
                'meta_description' => 'Подарочные карты PSN для американских аккаунтов PlayStation Store (USD). Коды активации для игр и подписок PS Plus по доступным ценам.',
                'h1' => 'PlayStation Store США (USD)',
                'is_active' => true,
            ],
            [
                'slug' => 'spotify-subscription',
                'title' => 'Spotify Premium Gift Cards & Subscriptions',
                'search_query' => 'Spotify',
                'meta_title' => 'Купить подписку Spotify Premium — Подарочные карты | Meanly',
                'meta_description' => 'Подарочные карты для продления и активации Spotify Premium. Слушайте музыку без рекламы с кодами мгновенной доставки от Meanly.',
                'h1' => 'Spotify Premium — Подписка и карты',
                'is_active' => true,
            ],
            [
                'slug' => 'xbox-gift-card',
                'title' => 'Xbox Gift Cards & Subscriptions',
                'search_query' => 'Xbox',
                'meta_title' => 'Купить карты оплаты Xbox Live и Game Pass | Meanly',
                'meta_description' => 'Подарочные карты Xbox Live Gift Cards и коды подписки Xbox Game Pass. Быстрая доставка, надежная активация и огромный выбор номиналов.',
                'h1' => 'Xbox — Подарочные карты и подписки',
                'is_active' => true,
            ],
            [
                'slug' => 'nintendo-eshop',
                'title' => 'Nintendo eShop Card',
                'search_query' => 'Nintendo',
                'meta_title' => 'Купить карты оплаты Nintendo eShop | Meanly',
                'meta_description' => 'Карты оплаты Nintendo eShop Card для покупки игр на Nintendo Switch. Моментальная доставка цифровых кодов на Meanly.',
                'h1' => 'Nintendo eShop — Карты оплаты',
                'is_active' => true,
            ],
            [
                'slug' => 'blizzard-gift-card',
                'title' => 'Blizzard Entertainment Gift Cards',
                'search_query' => 'Blizzard',
                'meta_title' => 'Купить карты оплаты Battle.net (Blizzard) | Meanly',
                'meta_description' => 'Подарочные карты Battle.net Gift Cards для пополнения кошелька Blizzard. Покупайте игры, внутриигровые товары и подписки мгновенно.',
                'h1' => 'Battle.net Blizzard — Карты пополнения',
                'is_active' => true,
            ],
        ];

        foreach ($collections as $collection) {
            SeoCollection::updateOrCreate(
                ['slug' => $collection['slug']],
                $collection
            );
        }
    }
}
