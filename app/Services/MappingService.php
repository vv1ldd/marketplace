<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ProviderBrandMapping;
use App\Models\ProviderCategoryMapping;
use Illuminate\Support\Str;

class MappingService
{
    /**
     * Resolve Master Brand ID from provider data.
     */
    public static function resolveBrand(int $providerId, ?string $externalName, ?string $sku = null, ?string $title = null, ?string $providerCategoryName = null): ?int
    {
        if (empty($externalName) && empty($sku) && empty($title)) {
            return null;
        }

        // 1. Try Exact Mapping
        if ($externalName) {
            $mapping = ProviderBrandMapping::with('brand')
                ->where('provider_id', $providerId)
                ->where('external_name', $externalName)
                ->first();

            if ($mapping && $mapping->brand_id) {
                if ($mapping->brand) {
                    self::ensureBrandCatalogGroup($mapping->brand, $providerId, $externalName, $title, $providerCategoryName);
                }

                return $mapping->brand_id;
            }
        }

        // 2. Try Fuzzy Keyword Guessing
        $searchString = mb_strtolower(($externalName ?? '').' '.($sku ?? '').' '.($title ?? ''));

        $brandId = self::guessBrandByKeywords($searchString);

        // 3. Fallback to cleaned external name if not generic
        if (! $brandId && $externalName) {
            $genericNames = [
                'unknown', 'wildflow gifts', 'n/a', 'none', 'null', 'test products', 
                'global catalog', 'retailer catalog', 'general', 'other', 'default'
            ];
            $cleanExternal = mb_strtolower(trim($externalName));
            
            if (! in_array($cleanExternal, $genericNames)) {
                $cleanedName = self::cleanBrandName($externalName);
                $brand = Brand::firstOrCreate(['name' => $cleanedName]);
                
                // 🔑 Попытаемся угадать группу при создании
                if (!$brand->catalog_group_id) {
                    $groupId = self::resolveCatalogGroupId($providerId, $providerCategoryName ?? $externalName, $cleanedName, $title);
                    if ($groupId) {
                        $brand->update(['catalog_group_id' => $groupId]);
                    }
                }
                
                $brandId = $brand->id;
            }
        }

        // 4. Last resort: Try to extract from title if external is unknown or generic
        if (! $brandId && $title) {
            // Clean common prefixes from provider title
            $cleanTitle = preg_replace('/^(Gift Card |E-Gift Card |Voucher |Digital Code )/i', '', $title);

            // Try to find region markers in title to separate brand
            if (preg_match('/^(.*?)\b(US|AE|FR|SA|UK|GB|EU|GLOBAL|MENA|LATAM|UAE|USA|CA|AU)\b/i', $cleanTitle, $matches)) {
                $extracted = self::cleanBrandName($matches[1]);
                if (strlen($extracted) >= 2) {
                    $brand = Brand::firstOrCreate(['name' => $extracted]);
                    self::ensureBrandCatalogGroup($brand, $providerId, $externalName, $title, $providerCategoryName);
                    $brandId = $brand->id;
                }
            } else {
                // Just use first two words if no region marker found
                $words = explode(' ', $cleanTitle);
                if (count($words) >= 1) {
                    $extracted = self::cleanBrandName($words[0].(isset($words[1]) ? ' '.$words[1] : ''));
                    if (strlen($extracted) >= 2) {
                        $brand = Brand::firstOrCreate(['name' => $extracted]);
                        
                        // 🔑 Попытаемся угадать группу при создании
                        if (!$brand->catalog_group_id) {
                            $groupId = self::resolveCatalogGroupId($providerId, $providerCategoryName ?? $externalName, $extracted, $title);
                            if ($groupId) {
                                $brand->update(['catalog_group_id' => $groupId]);
                            }
                        }
                        
                        $brandId = $brand->id;
                    }
                }
            }
        }

        // 5. If guessed/resolved, create/update mapping for future use
        if ($brandId && $externalName && ! in_array(mb_strtolower($externalName), ['unknown', 'wildflow gifts'])) {
            ProviderBrandMapping::updateOrCreate(
                ['provider_id' => $providerId, 'external_name' => $externalName],
                ['brand_id' => $brandId]
            );
        }

        return $brandId;
    }

    public static function resolveCatalogGroupId(int $providerId, ?string $providerCategoryName, ?string $brandName = null, ?string $context = null): ?int
    {
        $providerCategoryName = trim((string) $providerCategoryName);
        if ($providerCategoryName !== '') {
            $mappedGroupId = ProviderCategoryMapping::query()
                ->where('provider_id', $providerId)
                ->where('provider_category_name', $providerCategoryName)
                ->value('catalog_group_id');

            if ($mappedGroupId) {
                return (int) $mappedGroupId;
            }
        }

        $brandName = trim((string) $brandName);
        $context = trim(($providerCategoryName !== '' ? $providerCategoryName.' ' : '').(string) $context);

        return self::guessGroupIdByName($brandName, $context);
    }

    protected static function ensureBrandCatalogGroup(Brand $brand, int $providerId, ?string $externalName = null, ?string $title = null, ?string $providerCategoryName = null): void
    {
        if ($brand->catalog_group_id) {
            return;
        }

        $groupId = self::resolveCatalogGroupId($providerId, $providerCategoryName ?? $externalName, $brand->name, $title);
        if ($groupId) {
            $brand->update(['catalog_group_id' => $groupId]);
        }
    }

    protected static function guessBrandByKeywords(string $searchString): ?int
    {
        $masterName = self::normalizeBrandName($searchString);

        if ($masterName) {
            $brand = Brand::firstOrCreate(['name' => $masterName]);
            
            // 🔑 Если группа еще не задана, попробуем её определить
            if (!$brand->catalog_group_id) {
                $groupId = self::guessGroupIdByName($masterName, $searchString);
                if ($groupId) {
                    $brand->update(['catalog_group_id' => $groupId]);
                }
            }

            return $brand->id;
        }

        return null;
    }

    private static function catalogGroupId(string $slug, string $name, int $fallbackId): int
    {
        static $cache = [];

        $key = $slug.'|'.$name;
        if (array_key_exists($key, $cache)
            && ! \App\Models\CatalogGroup::query()->whereKey($cache[$key])->exists()) {
            unset($cache[$key]);
        }

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = (int) \App\Models\CatalogGroup::query()
                ->where('slug', $slug)
                ->orWhere('name', $name)
                ->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'sort_order' => $fallbackId,
                        'is_active' => true,
                    ],
                )
                ->id;
        }

        return $cache[$key];
    }

    /**
     * Угадываем main category по бренду и контексту провайдера.
     */
    public static function guessGroupIdByName(string $brandName, ?string $context = ''): ?int
    {
        $name = mb_strtolower($brandName);
        $ctx = mb_strtolower($context ?? '');
        $haystack = trim($name.' '.$ctx);

        $gamesId = self::catalogGroupId('igry', 'Игры', 1);
        $subscriptionsId = self::catalogGroupId('podpiski', 'Подписки', 2);
        $topupId = self::catalogGroupId('popolnenie-sceta', 'Пополнение счета', 3);
        $financeId = self::catalogGroupId('finansy', 'Финансы', 6);
        $softId = self::catalogGroupId('soft', 'Софт', 4);
        $retailId = self::catalogGroupId('riteil', 'Ритейл', 5);

        $matches = static function (array $keywords) use ($haystack): bool {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return true;
                }
            }

            return false;
        };

        $gameKeywords = [
            'playstation', 'psn', 'xbox', 'nintendo', 'steam', 'razer gold', 'roblox', 'robux',
            'riot', 'valorant', 'league of legends', 'blizzard', 'battle.net', 'battlenet',
            'ubisoft', 'epic games', 'rockstar', 'minecraft', 'pubg', 'free fire', 'fortnite',
            'electronic arts', 'ea sports', 'ea play', 'fifa', 'fc 24', 'fc 25', 'final fantasy',
            'mobile legends', 'genshin', 'gamestop', 'game over', 'lego', 'xsolla',
            'gocash game card', 'game key', 'gaming',
        ];
        if ($matches($gameKeywords) || str_contains($ctx, 'game key') || str_contains($ctx, 'dlc')) {
            return $gamesId;
        }

        $softKeywords = [
            'microsoft', 'office', 'windows', 'adobe', 'kaspersky', 'mcafee', 'norton',
            'bitdefender', 'antivirus', 'vpn', 'zoom', 'software', 'license', 'licence',
        ];
        if ($matches($softKeywords)) {
            return $softId;
        }

        $subscriptionKeywords = [
            'netflix', 'spotify', 'tinder', 'bumble', 'discord', 'nitro', 'crunchyroll',
            'disney', 'hulu', 'paramount', 'youtube premium', 'apple music', 'deezer',
            'tidal', 'anghami', 'indieflix', 'britbox', 'starzplay', 'dazn',
            'abbonamenti', 'kobo', 'subscription', 'membership',
        ];
        if ($matches($subscriptionKeywords)) {
            return $subscriptionsId;
        }

        $financeKeywords = [
            'cryptovoucher', 'crypto voucher', 'rewarble crypto', 'binance',
            'visa', 'mastercard', 'american express', 'amex', 'flexepin',
            'gcodes', 'gate giftcard', 'skrill', 'payoneer', 'bitsa', 'bitnovo',
            'neosurf', 'cashtocode', 'icash', 'cashu', 'phonepe', 'payment',
            'finance', 'financial', 'crypto', 'voucher money',
        ];
        if ($matches($financeKeywords)) {
            return $financeId;
        }

        $topupKeywords = [
            'itunes', 'google play', 'app store', 'etisalat', 'du ', 'o2 ',
            'lycamobile', 'lebara', 'salik', 'cafu', 'alosim', 'esimchoice',
            'airalo', 'wallet', 'topup', 'top-up', 'airtime',
        ];
        if ($matches($topupKeywords)) {
            return $topupId;
        }

        $retailKeywords = [
            'amazon', 'ebay', 'walmart', 'target', 'best buy', 'apple', 'google', 'huawei',
            'ikea', 'carrefour', 'bol.com', 'coolblue', 'mediamarkt', 'decathlon',
            'maisons du monde', 'lowe', "lowe's", 'toom', 'croma', 'tmall',
            'fnac', 'otto', 'cyberport', 'monoprix', 'groupon', 'instacart',
            'trony', 'saturn', 'mediaworld', 'conrad', 'obi', 'iper', 'rossmann',
            'macy', "macy's", 'morrisons', 'your gift choice', 'tj maxx', 'tk maxx',
            'marshalls', 'homegoods',
            'mango', 'zara', 'h&m', 'hm ', 'shein', 'primark', 'calzedonia', 'foot locker',
            'nike', 'adidas', 'columbia', 'bata', 'allbirds', 'intersport', 'sephora', 'swarovski',
            'nykaa', 'forzieri', 'nelly.com', 'zappos', 'bonobo', 'pantaloons',
            'ernsting', 'bijou brigitte', 'apparel group', 'asos', 'bréal', 'breal',
            'tommy john',
            'home centre', 'homecenter', 'lifestyle', 'tata cliq', 'saks', 'zalando',
            'nordstrom', 'frasers', 'myntra', 'old navy', 'joyalukkas', 'nahdi',
            'huygens', 'naturasi', 'but ', 'luxe', 'regal', 'inno', 'chubbies',
            'thirdlove', 'crutchfield', 'thredup', 'mister spex', 'booksvenue',
            'brandat international', 'douglas',
            'hobbycraft', 'pet corner', 'solo stove', 'fashion',
            'beauty', 'retail', 'grocery', 'market', 'store', 'shop', 'book',
            'starbucks', 'mcdonald', 'burger king', 'kfc', 'subway', 'pizza hut',
            'outback', 'hellofresh', 'hello fresh', 'deliveroo', 'uber eats', 'swiggy',
            'zomato', 'talabat', 'careem', 'ole & steen', 'panera bread',
            'coffee bean', 'cold stone', 'red robin', 'not just desserts',
            'laithwaite', 'zafran', 'cofe', 'just eat', 'eataly', 'joe & seph',
            'iceland', 'food', 'restaurant',
            'bloom & wild', 'flowers',
            'airbnb', 'hotels.com', 'expedia', 'stubhub', 'tripgift', 'almosafer',
            'taj hotels', 'fairmont', 'global hotel', 'hotelsgift', 'swissôtel',
            'swissotel', 'dreamdays', 'mydays', 'jochen schweizer', 'inspire travel',
            'sanctifly', 'lastminute', 'celebrity cruises', 'esso', 'fuel', 'jet ski',
            'water sports', 'apnea zone', 'heli dubai', 'love boats', 'elite pearl yachts',
            'zofeur', 'cap adrénaline', 'cap adrenaline', 'experience', 'cinema',
            'reel cinemas', 'book my show', 'nationwidetickets', 'ownersbox',
            'wellbeing', 'wellness', 'beutics', 'rayya wellness', 'champneys',
            'la feltrinelli', 'feltrinelli',
        ];
        if ($matches($retailKeywords)) {
            return $retailId;
        }

        if (str_contains($ctx, 'gift card') || str_contains($ctx, 'giftcard') || str_contains($ctx, 'gift-card') || str_contains($ctx, 'voucher')) {
            return $retailId;
        }

        return null;
    }

    /**
     * Get consolidated regional aliases.
     */
    protected static function getRegionAliases(): array
    {
        return [
            'GLOBAL' => 'GLB', 'WORLDWIDE' => 'GLB', 'WW' => 'GLB',
            'UK' => 'GB', 'GBR' => 'GB',
            'USA' => 'US', 'US' => 'US',
            'LATAM' => 'LC',
            'QAT' => 'QA', 'UAE' => 'AE', 'ARE' => 'AE',
            'URY' => 'UY',
            'REU' => 'RE',
            'NGA' => 'NG',
            'ZAF' => 'ZA',
            'AUS' => 'AU',
            'CAN' => 'CA',
            'ARE' => 'AE',
            'SAU' => 'SA',
            'EGY' => 'EG',
            'TUR' => 'TR',
            'IND' => 'IN',
            'FRA' => 'FR',
            'DEU' => 'DE',
            'ESP' => 'ES',
            'ITA' => 'IT',
            'RUS' => 'RU',
            'CIS' => 'CIS',
        ];
    }

    /**
     * Get currency to region mapping.
     */
    protected static function getCurrencyMap(): array
    {
        return [
            'AED' => 'AE', 'SAR' => 'SA', 'USD' => 'US', 'EUR' => 'EU', 'GBP' => 'GB',
            'AUD' => 'AU', 'CAD' => 'CA', 'INR' => 'IN', 'EGP' => 'EG', 'BHD' => 'BH',
            'KWD' => 'KW', 'QAR' => 'QA', 'OMR' => 'OM', 'PLN' => 'PL', 'ZAR' => 'ZA',
            'TRY' => 'TR', 'BRL' => 'BR', 'MXN' => 'MX', 'CLP' => 'CL', 'COP' => 'CO',
            'PEN' => 'PE', 'MYR' => 'MY', 'THB' => 'TH', 'IDR' => 'ID', 'PHP' => 'PH',
            'SGD' => 'SG', 'HKD' => 'HK', 'CNY' => 'CN', 'NZD' => 'NZ', 'EZD' => 'GLB',
        ];
    }

    /**
     * Get country name to code mapping.
     */
    protected static function getNameMap(): array
    {
        return [
            'USA' => 'US', 'UNITED STATES' => 'US', 'EUROPE' => 'EU', 'WORLDWIDE' => 'GLB',
            'GLOBAL' => 'GLB', 'MIDDLE EAST' => 'MENA', 'AUSTRIA' => 'AT', 'GERMANY' => 'DE',
            'FRANCE' => 'FR', 'SPAIN' => 'ES', 'ITALY' => 'IT', 'TURKEY' => 'TR',
            'INDIA' => 'IN', 'CANADA' => 'CA', 'POLAND' => 'PL', 'SOUTH AFRICA' => 'ZA',
            'AUSTRALIA' => 'AU', 'CROATIA' => 'HR', 'LATIN AMERICA' => 'LC', 'LATAM' => 'LC',
            'BRAZIL' => 'BR', 'SINGAPORE' => 'SG', 'SWITZERLAND' => 'CH', 'SWEDEN' => 'SE',
            'DENMARK' => 'DK', 'NORWAY' => 'NO', 'FINLAND' => 'FI', 'NETHERLANDS' => 'NL',
            'BELGIUM' => 'BE', 'PORTUGAL' => 'PT', 'CZECHIA' => 'CZ', 'ROMANIA' => 'RO',
            'HUNGARY' => 'HU', 'GREECE' => 'GR', 'ALGERIA' => 'DZ', 'ARGENTINA' => 'AR',
            'NEW ZEALAND' => 'NZ', 'BAHRAIN' => 'BH', 'BAHRIAN' => 'BH', 'EGYPT' => 'EG',
            'THAILAND' => 'TH', 'MALAYSIA' => 'MY', 'PHILIPPINES' => 'PH', 'VIETNAM' => 'VN',
            'INDONESIA' => 'ID', 'MEXICO' => 'MX', 'COLOMBIA' => 'CO', 'CHILE' => 'CL',
            'PERU' => 'PE', 'MALDIVES' => 'MV', 'HONDURAS' => 'HN', 'GUATEMALA' => 'GT',
            'BANGLADESH' => 'BD',
        ];
    }

    /**
     * Resolve a region ID from a country code or product title.
     */
    public static function resolveRegion(?string $code, ?string $title = null): ?int
    {
        $resolvedCode = self::extractCodeFromTitle($title);

        // If title resolution failed or returned generic code, use provider code
        if (! $resolvedCode || $resolvedCode === 'GLB') {
            $providerCode = self::normalizeRegionCode($code);
            // Only override if we found nothing or if provider code is more specific than GLB
            if ($providerCode && (! $resolvedCode || ($providerCode !== 'GLB' && $resolvedCode === 'GLB'))) {
                $resolvedCode = $providerCode;
            }
        }

        if (! $resolvedCode) {
            return null;
        }

        $country = \App\Models\MappingCountry::where('code', $resolvedCode)->first();

        return $country ? $country->id : null;
    }

    protected static function normalizeRegionCode(?string $code): ?string
    {
        if (! $code) {
            return null;
        }
        $normalized = strtoupper(trim($code));

        return self::getRegionAliases()[$normalized] ?? $normalized;
    }

    protected static function extractCodeFromTitle(?string $title): ?string
    {
        if (! $title) {
            return null;
        }

        $aliases = self::getRegionAliases();
        $parts = explode('-', $title);

        // 1. Try potential codes in title parts
        foreach ($parts as $part) {
            $potential = strtoupper(trim($part));
            if (strlen($potential) < 2 || strlen($potential) > 5) {
                continue;
            }

            $normalized = $aliases[$potential] ?? $potential;
            if (\App\Models\MappingCountry::where('code', $normalized)->exists()) {
                return $normalized;
            }
        }

        // 2. Try codes as standalone words
        $regionRegex = '/\b(US|AE|UK|GB|FR|DE|ES|IT|CA|AU|SA|IN|UAE|USA|GLB|EU|MENA|LATAM|LC|QA|QAT|RU|RUS|SE|NO|DK|FI|CH|PL|ZA|AU|TR|BR|SG|ID|MY|TH|PH|VN|HK|KR|JP|TW|CIS)\b/i';
        if (preg_match($regionRegex, $title, $matches)) {
            $potential = strtoupper($matches[1]);

            return $aliases[$potential] ?? $potential;
        }

        // 3. Try Currency markers
        $currencyMap = self::getCurrencyMap();
        foreach ($parts as $part) {
            $uPart = strtoupper(trim($part));
            foreach ($currencyMap as $curr => $reg) {
                if (str_ends_with($uPart, $curr)) {
                    return $reg;
                }
            }
        }

        // 4. Try Full Name markers
        $nameMap = self::getNameMap();
        $nameRegex = '/\b('.implode('|', array_keys($nameMap)).')\b/i';
        if (preg_match($nameRegex, $title, $matches)) {
            return $nameMap[strtoupper($matches[1])] ?? null;
        }

        return null;
    }

    public static function normalizeBrandName(string $searchString): ?string
    {
        $searchString = mb_strtolower($searchString);
        $keywords = self::getBrandKeywords();

        foreach ($keywords as $masterName => $list) {
            foreach ($list as $kw) {
                $pattern = '/\b'.preg_quote(mb_strtolower($kw), '/').'\b/i';
                if (preg_match($pattern, $searchString)) {
                    return $masterName;
                }
            }
        }

        return null;
    }

    protected static function getBrandKeywords(): array
    {
        return [
            'Apple' => ['apple', 'itunes', 'app store', 'icloud'],
            'Google Play' => ['google', 'play store', 'gplay', 'googleplay'],
            'Steam' => ['steam', 'valve'],
            'PlayStation' => ['playstation', 'psn', 'play station', 'sony', 'dualshock', 'dual sense', 'dualsense'],
            'Xbox' => ['xbox', 'microsoft xbox', 'game pass', 'live gold'],
            'Nintendo' => ['nintendo', 'eshop', 'switch', 'wii'],
            'Roblox' => ['roblox', 'robux'],
            'Minecraft' => ['minecraft', 'mojang', 'minecoin'],
            'Netflix' => ['netflix'],
            'Spotify' => ['spotify'],
            'Amazon' => ['amazon', 'amzn', 'prime video'],
            'Twitch' => ['twitch'],
            'Razer' => ['razer', 'gold pin'],
            'Riot Games' => ['riot', 'valorant', 'league of legends', 'lol', 'wild rift'],
            'Electronic Arts' => ['ea sports', 'fifa', 'fc 24', 'fc 25', 'apex legends', 'battlefield', 'origin', 'sims'],
            'Ubisoft' => ['ubisoft', 'uplay', 'rainbow six', 'assassin\'s creed'],
            'Blizzard' => ['blizzard', 'battle.net', 'battlenet', 'overwatch', 'diablo', 'wow', 'world of warcraft'],
            'Fortnite' => ['fortnite', 'v-bucks', 'vbucks'],
            'PUBG' => ['pubg', 'uc'],
            'Free Fire' => ['free fire', 'freefire', 'diamonds'],
            'Mobile Legends' => ['mobile legends', 'mlbb', 'diamonds'],
            'Genshin Impact' => ['genshin', 'hoyoverse', 'primogems'],
            'Tinder' => ['tinder', 'gold', 'platinum'],
            'Bumble' => ['bumble'],
            'BIGO' => ['bigo'],
            'Discord' => ['discord', 'nitro'],
            'Telegram' => ['telegram', 'premium', 'stars'],
            'Sephora' => ['sephora'],
            'Airbnb' => ['airbnb'],
            'Uber' => ['uber', 'uber eats'],
            'Starbucks' => ['starbucks'],
            'Nike' => ['nike'],
            'Adidas' => ['adidas'],
            'IKEA' => ['ikea'],
            'H&M' => ['h&m', 'h & m'],
            'Zara' => ['zara'],
            'SHEIN' => ['shein'],
            'Walmart' => ['walmart', 'cashi'],
            'Target' => ['target'],
            'Best Buy' => ['best buy', 'bestbuy'],
            'eBay' => ['ebay'],
            'Visa' => ['visa'],
            'Mastercard' => ['mastercard'],
            'American Express' => ['amex', 'american express'],
            'Binance' => ['binance'],
            'Crypto.com' => ['crypto.com', 'cro'],
            'Paramount+' => ['paramount'],
            'Disney+' => ['disney'],
            'Hulu' => ['hulu'],
            'Crunchyroll' => ['crunchyroll'],
            'TJ Maxx' => ['tj maxx'],
            'TK Maxx' => ['tk maxx'],
            'Zalando' => ['zalando'],
            'Subway' => ['subway'],
            'Taco Bell' => ['taco bell'],
            'Swarovski' => ['swarovski'],
            'Shell' => ['shell'],
            'Skype' => ['skype'],
            'Sling' => ['sling'],
            'Sobeys' => ['sobeys'],
            'Southwest Airlines' => ['southwest airlines'],
            'Staples' => ['staples'],
            'DoorDash' => ['doordash'],
            'Instacart' => ['instacart'],
            'Grubhub' => ['grubhub'],
            'Ticketmaster' => ['ticketmaster'],
            'Foot Locker' => ['foot locker', 'footlocker'],
            'Decathlon' => ['decathlon'],
            'MediaMarkt' => ['mediamarkt'],
            'Saturn' => ['saturn'],
            'Carrefour' => ['carrefour'],
            'Deliveroo' => ['deliveroo'],
            'Talabat' => ['talabat'],
            'Careem' => ['careem'],
            'Noon' => ['noon'],
            'Zomato' => ['zomato'],
            'Mango' => ['mango'],
            'Huawei' => ['huawei'],
            'HelloFresh' => ['hellofresh', 'hello fresh'],
            'Deezer' => ['deezer'],
            'LEGO' => ['lego'],
            'Domino\'s' => ['dominos', 'domino\'s'],
            'Abercrombie & Fitch' => ['abercrombie'],
            'Aeropostale' => ['aeropostale'],
            'Albertson\'s' => ['albertsons', 'albertson\'s'],
            'Aldi' => ['aldi'],
            'Barnes & Noble' => ['barnes & noble', 'barnes and noble'],
            'GameStop' => ['gamestop'],
            'Lowe\'s' => ['lowe\'s', 'lowes'],
            'Old Navy' => ['old navy'],
            'Panera Bread' => ['panera'],
            'Red Lobster' => ['red lobster'],
            'Nordstrom' => ['nordstrom'],
            'Groupon' => ['groupon'],
            'Columbia' => ['columbia'],
            'Norton' => ['norton', 'antivirus'],
            'Webmoney' => ['webmoney'],
            'Jawaker' => ['jawaker'],
            'Almosafer' => ['almosafer'],
            'Croma' => ['croma'],
            'Tata Cliq' => ['tata cliq'],
            'Nykaa' => ['nykaa'],
            'Lifestyle' => ['lifestyle'],
            'Kinguin' => ['kinguin'],
            'Xsolla' => ['xsolla'],
            'Epic Games' => ['epic games', 'epic store'],
            'Meta' => ['meta', 'oculus', 'facebook'],
            'Anghami' => ['anghami'],
            'Primark' => ['primark'],
            'Etisalat' => ['etisalat'],
            'Nahdi' => ['nahdi'],
            'Allbirds' => ['allbirds'],
            'Bitdefender' => ['bitdefender'],
            'McAfee' => ['mcafee'],
            'Kaspersky' => ['kaspersky'],
            'Skrill' => ['skrill'],
            'Neteller' => ['neteller'],
            'Paysafecard' => ['paysafecard'],
            'Neosurf' => ['neosurf'],
            'Flexepin' => ['flexepin'],
            'Rewarble' => ['rewarble'],
            'Calzedonia' => ['calzedonia'],
            'Intimissimi' => ['intimissimi'],
            'Tezenis' => ['tezenis'],
            'Maisons du Monde' => ['maisons du monde'],
            'Zalando' => ['zalando'],
            'Subway' => ['subway'],
            'Taco Bell' => ['taco bell'],
            'Swarovski' => ['swarovski'],
            'Shell' => ['shell'],
            'Skype' => ['skype'],
            'Sling' => ['sling'],
            'Sobeys' => ['sobeys'],
            'Southwest Airlines' => ['southwest airlines'],
            'Staples' => ['staples'],
            'DoorDash' => ['doordash'],
            'Instacart' => ['instacart'],
            'Grubhub' => ['grubhub'],
            'Ticketmaster' => ['ticketmaster'],
            'Foot Locker' => ['foot locker'],
            'Decathlon' => ['decathlon'],
            'MediaMarkt' => ['mediamarkt'],
            'Saturn' => ['saturn'],
            'Carrefour' => ['carrefour'],
            'Deliveroo' => ['deliveroo'],
            'Talabat' => ['talabat'],
            'Careem' => ['careem'],
            'Noon' => ['noon'],
            'Zomato' => ['zomato'],
            'GCodes' => ['gcodes'],
            'Yalla Ludo' => ['yalla ludo', 'ludo'],
            'Likee' => ['likee'],
            'Swiggy' => ['swiggy'],
            'Outback Steakhouse' => ['outback steakhouse'],
            'Saks Fifth Avenue' => ['saks fifth avenue'],
            'Surfshark' => ['surfshark'],
            'Skype' => ['skype'],
        ];
    }

    public static function cleanBrandName(string $name): string
    {
        $name = trim($name);
        $name = Str::title(str_replace(['_', '-'], ' ', $name));

        // Remove common prefixes/suffixes
        $name = preg_replace('/^Voucher Gc /i', '', $name);
        $name = preg_replace('/ Gift Card.*$/i', '', $name);
        $name = preg_replace('/ E Gift.*$/i', '', $name);

        return trim($name);
    }

    /**
     * Get the list of master categories.
     */
    protected static function getMasterCategories(): array
    {
        return [
            'Gaming & Streaming',
            'Software',
            'Fashion & Accessories',
            'Retail',
            'Finance',
            'Food & Drink',
            'Books, Movies & Music',
            'Travel & Entertainment',
            'Home & Garden',
            'Sport & Fitness',
            'Electronics',
            'Health & Beauty',
            'Beauty & Health',
            'Gift Cards',
        ];
    }

    /**
     * Resolve a clean category name from provider metadata.
     */
    /**
     * Resolve a clean category name from provider metadata.
     */
    public static function resolveCategory(array $categories): ?string
    {
        $masterCategories = self::getMasterCategories();
        $masterFound = null;
        $specificFound = null;

        foreach ($categories as $cat) {
            $name = $cat['name'] ?? null;
            if (! $name) {
                continue;
            }

            foreach ($masterCategories as $master) {
                if (stripos($name, $master) !== false) {
                    $masterFound = $master;
                    // Canonicalize Health & Beauty
                    if (in_array($masterFound, ['Health & Beauty', 'Beauty & Health'])) {
                        $masterFound = 'Health & Beauty';
                    }
                    break;
                }
            }
            if ($masterFound) {
                break;
            }
        }

        // If we have a more specific category at the beginning of the list, use it as a prefix
        if (! empty($categories)) {
            $first = trim($categories[0]['name'] ?? '');
            if ($first && $first !== $masterFound && strlen($first) < 40 && ! preg_match('/\d/', $first)) {
                $specificFound = Str::title($first);
            }
        }

        if ($masterFound && $specificFound && $masterFound !== $specificFound) {
            return "{$masterFound} › {$specificFound}";
        }

        return $masterFound ?: ($specificFound ?: 'Other');
    }

    /**
     * Extract structured metadata from raw provider JSON.
     */
    public static function extractRedemptionMetadata(array $itemData): array
    {
        $productData = data_get($itemData, 'data.product') ?? data_get($itemData, 'product') ?? $itemData;
        $descRaw = data_get($productData, 'description');

        $metadata = [
            'redemption_instructions' => null,
            'activation_url' => null,
            'reward_type' => data_get($productData, 'reward_type_text'),
            'upc' => data_get($productData, 'upc'),
        ];

        if ($descRaw) {
            $descJson = is_array($descRaw) ? $descRaw : json_decode($descRaw, true);
            if ($descJson) {
                $contents = $descJson['content'] ?? [];
                foreach ($contents as $section) {
                    $type = $section['type'] ?? '';
                    $title = $section['title'] ?? '';
                    if ($type === 'redeem' || stripos($title, 'Redemption') !== false) {
                        $text = html_entity_decode($section['description'] ?? '', ENT_QUOTES | ENT_HTML5);
                        $text = strip_tags($text);
                        $metadata['redemption_instructions'] = trim($text);
                        break;
                    }
                }
            }
        }

        if ($metadata['redemption_instructions']) {
            $metadata['activation_url'] = self::extractActivationUrlFromText($metadata['redemption_instructions']);
        }

        return $metadata;
    }

    /**
     * Первый подходящий URL из текста инструкции провайдера (официальный redeem / gift card и т.п.).
     */
    public static function extractActivationUrlFromText(string $text): ?string
    {
        // Удаляем неразрывные пробелы и другие странные символы перед парсингом
        $text = str_replace(["\xc2\xa0", "\xa0", "\t"], ' ', $text);

        $patterns = [
            '/(https?:\/\/[a-z0-9\.\/\-\_\?\&\=\#\%]+)/i',
            '/\b([a-z0-9]+\.[a-z0-9]+\/(?:redeem|setup|activate|account|entry|gc\/redeem)[a-z0-9\.\/\-\_\?\&\=\#\%]*)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $url = $matches[1];

                // Чистим от пунктуации в конце
                return rtrim($url, '., ');
            }
        }

        return null;
    }
}
