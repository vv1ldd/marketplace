<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ProviderBrandMapping;
use Illuminate\Support\Str;

class MappingService
{
    /**
     * Resolve Master Brand ID from provider data.
     */
    public static function resolveBrand(int $providerId, ?string $externalName, ?string $sku = null, ?string $title = null): ?int
    {
        if (empty($externalName) && empty($sku) && empty($title)) {
            return null;
        }

        // 1. Try Exact Mapping
        if ($externalName) {
            $mapping = ProviderBrandMapping::where('provider_id', $providerId)
                ->where('external_name', $externalName)
                ->first();

            if ($mapping && $mapping->brand_id) {
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
                    $groupId = self::guessGroupIdByName($cleanedName, $title);
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
                            $groupId = self::guessGroupIdByName($extracted, $title);
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

    /**
     * Угадываем ID группы на основе имени бренда или контекста
     * Catalog Groups: 1=Игры, 2=Подписки, 3=Пополнение счета, 4=Софт
     */
    public static function guessGroupIdByName(string $brandName, ?string $context = ''): ?int
    {
        $name = mb_strtolower($brandName);
        $ctx = mb_strtolower($context ?? '');

        // 1. Пополнение счета (Самая большая категория)
        $walletKeywords = ['apple', 'itunes', 'google', 'playstation', 'psn', 'xbox', 'nintendo', 'steam', 'amazon', 'razer', 'binance', 'visa', 'mastercard', 'roblox', 'robux'];
        foreach ($walletKeywords as $kw) {
            if (str_contains($name, $kw)) return 3;
        }

        // 2. Подписки
        $subKeywords = ['netflix', 'spotify', 'tinder', 'bumble', 'discord', 'nitro', 'crunchyroll', 'disney', 'hulu', 'paramount', 'youtube', 'premium', 'plus'];
        foreach ($subKeywords as $kw) {
            if (str_contains($name, $kw) || str_contains($ctx, 'subscription') || str_contains($ctx, 'membership')) return 2;
        }

        // 3. Игры (Ключи и т.д.)
        $gameKeywords = ['electronic arts', 'riot', 'blizzard', 'ubisoft', 'epic games', 'rockstar', 'minecraft', 'pubg', 'free fire', 'valorant', 'fortnite', 'ea sports', 'fifa', 'fc 24', 'fc 25'];
        foreach ($gameKeywords as $kw) {
            if (str_contains($name, $kw) || str_contains($ctx, 'key') || str_contains($ctx, 'game key')) return 1;
        }

        // 4. Софт
        $softKeywords = ['microsoft', 'office', 'adobe', 'kaspersky', 'mcafee', 'norton', 'vpn', 'windows'];
        foreach ($softKeywords as $kw) {
            if (str_contains($name, $kw) || str_contains($ctx, 'software') || str_contains($ctx, 'license')) return 4;
        }

        // Fallback по контексту (если в имени ничего нет)
        if (str_contains($ctx, 'topup') || str_contains($ctx, 'wallet') || str_contains($ctx, 'gift card')) return 3;
        if (str_contains($ctx, 'game') || str_contains($ctx, 'dlc')) return 1;

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
