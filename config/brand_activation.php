<?php

/**
 * Fallback URL официальной страницы погашения, если у позиции Wildflow пустые activation_url и redemption_instructions.
 * Порядок правил важен: более узкие условия выше общих.
 *
 * Условия (все указанные должны выполняться):
 * - brand_contains: подстрока в имени бренда (без учёта регистра)
 * - haystack_contains: подстрока в title + category (без учёта регистра)
 * - brand_any: достаточно совпадения с любым из перечисленных фрагментов бренда
 */
return [
    'rules' => [
        ['brand_contains' => 'GOOGLE PLAY', 'url' => 'https://play.google.com/redeem'],
        ['brand_contains' => 'GOOGLE', 'haystack_contains' => 'PLAY', 'url' => 'https://play.google.com/redeem'],

        ['brand_contains' => 'STEAM', 'url' => 'https://store.steampowered.com/account/redeemwalletcode'],

        ['brand_contains' => 'PLAYSTATION', 'url' => 'https://www.playstation.com/redeem/'],
        ['brand_contains' => 'PSN', 'url' => 'https://www.playstation.com/redeem/'],

        ['brand_contains' => 'XBOX', 'url' => 'https://redeem.microsoft.com/'],
        ['brand_contains' => 'MICROSOFT', 'haystack_contains' => 'XBOX', 'url' => 'https://redeem.microsoft.com/'],

        ['brand_contains' => 'NINTENDO', 'url' => 'https://ec.nintendo.com/redeem/'],

        ['brand_contains' => 'ROBLOX', 'url' => 'https://www.roblox.com/redeem'],

        ['brand_contains' => 'APPLE', 'url' => 'https://www.apple.com/redeem'],
        ['brand_contains' => 'ITUNES', 'url' => 'https://www.apple.com/redeem'],

        ['brand_contains' => 'MICROSOFT', 'url' => 'https://redeem.microsoft.com/'],

        ['brand_contains' => 'AMAZON', 'url' => 'https://www.amazon.com/gp/redeem/'],

        ['brand_contains' => 'NETFLIX', 'url' => 'https://www.netflix.com/redeem'],

        ['brand_contains' => 'SPOTIFY', 'url' => 'https://www.spotify.com/redeem/'],

        ['brand_contains' => 'RAZER', 'url' => 'https://gold.razer.com/gold/redeem'],

        ['brand_contains' => 'UBER', 'url' => 'https://wallet.uber.com/redeem'],

        ['brand_contains' => 'META QUEST', 'url' => 'https://www.meta.com/redeem/'],
        ['brand_contains' => 'OCULUS', 'url' => 'https://www.meta.com/redeem/'],

        ['brand_contains' => 'ELECTRONIC ARTS', 'url' => 'https://www.ea.com/redeem'],
        ['brand_contains' => 'EA SPORTS', 'url' => 'https://www.ea.com/redeem'],

        ['brand_contains' => 'BLIZZARD', 'url' => 'https://account.battle.net/codes/'],
        ['brand_contains' => 'BATTLE.NET', 'url' => 'https://account.battle.net/codes/'],

        ['brand_contains' => 'DISNEY', 'url' => 'https://www.disneyplus.com/redeem'],
        ['brand_contains' => 'DISNEY+', 'url' => 'https://www.disneyplus.com/redeem'],

        ['brand_contains' => 'LEAGUE OF LEGENDS', 'url' => 'https://redeem.riotgames.com/'],
        ['brand_contains' => 'RIOT', 'haystack_contains' => 'LEAGUE', 'url' => 'https://redeem.riotgames.com/'],
        ['brand_contains' => 'VALORANT', 'url' => 'https://redeem.riotgames.com/'],
    ],
];
