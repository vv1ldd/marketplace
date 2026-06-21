<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AppStoreLookupService
{
    private const CACHE_SECONDS = 21600;

    /**
     * @return array{app_query: string, region: string|null, country: string, gift_card_query: string}|null
     */
    public function intentFromMessage(string $message): ?array
    {
        $normalized = $this->normalize($message);

        if (! $this->looksLikeAppStorePriceIntent($normalized)) {
            return null;
        }

        $region = $this->regionFromText($normalized);
        $country = $this->countryForRegion($region);
        $appQuery = $this->appQueryFromText($normalized);

        if ($appQuery === '') {
            return null;
        }

        return [
            'app_query' => $appQuery,
            'region' => $region,
            'country' => $country,
            'gift_card_query' => trim('apple app store itunes '.($region ?? '')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term, ?string $region = null, int $limit = 3): array
    {
        $term = trim($term);
        $country = $this->countryForRegion($region);
        $limit = max(1, min($limit, 10));

        if ($term === '') {
            return [];
        }

        return Cache::remember(
            'app-store.lookup.'.sha1(mb_strtolower($term).'|'.$country.'|'.$limit),
            self::CACHE_SECONDS,
            function () use ($term, $country, $region, $limit): array {
                try {
                    $response = Http::timeout(10)
                        ->acceptJson()
                        ->get('https://itunes.apple.com/search', [
                            'term' => $term,
                            'entity' => 'software',
                            'country' => $country,
                            'limit' => $limit,
                        ]);
                } catch (\Throwable) {
                    return [];
                }

                if (! $response->successful()) {
                    return [];
                }

                return collect((array) $response->json('results', []))
                    ->map(fn (array $item): array => $this->formatResult($item, $country, $region))
                    ->filter(fn (array $item): bool => $item['name'] !== '')
                    ->values()
                    ->all();
            },
        );
    }

    private function looksLikeAppStorePriceIntent(string $normalized): bool
    {
        $hasApplePlatform = Str::contains($normalized, [
            'iphone',
            'ios',
            'app store',
            'appstore',
            'itunes',
            'apple id',
            'айфон',
            'айфона',
            'айос',
            'эпл стор',
            'апп стор',
            'эпл id',
            'apple account',
        ]);
        $hasAppLanguage = Str::contains($normalized, [
            'app',
            'application',
            'subscription',
            'приложение',
            'приложения',
            'подписка',
            'подписку',
        ]);
        $hasPriceLanguage = Str::contains($normalized, [
            'price',
            'cost',
            'region',
            'storefront',
            'сколько стоит',
            'цена',
            'стоит',
            'регион',
            'страна',
            'где дешевле',
            'пополнить',
        ]);

        return $hasApplePlatform || ($hasAppLanguage && $hasPriceLanguage);
    }

    private function appQueryFromText(string $normalized): string
    {
        $phrases = [
            'сколько стоит',
            'где дешевле',
            'app store',
            'apple store',
            'эпл стор',
            'апп стор',
            'на айфон',
            'для айфон',
            'на iphone',
            'для iphone',
            'на ios',
            'для ios',
        ];
        $text = str_replace($phrases, ' ', $normalized);
        $tokens = preg_split('/\s+/', $text) ?: [];
        $stopWords = [
            'a', 'an', 'and', 'app', 'application', 'available', 'buy', 'card', 'cost', 'for', 'gift',
            'in', 'ios', 'iphone', 'link', 'me', 'on', 'price', 'region', 'storefront', 'subscription',
            'the', 'to', 'top', 'up', 'with',
            'а', 'в', 'где', 'дай', 'для', 'и', 'как', 'каком', 'какой', 'карту', 'купить', 'мне',
            'на', 'нужна', 'нужно', 'подарочную', 'подписка', 'подписку', 'пополнить', 'приложение',
            'приложения', 'регион', 'регионе', 'ссылку', 'страна', 'стране', 'цена', 'хочу',
            'айфон', 'айфона', 'айос',
        ];
        $regionWords = ['turkey', 'turkiye', 'tr', 'us', 'usa', 'united', 'states', 'uae', 'ae', 'uk', 'gb', 'kazakhstan', 'kz', 'турции', 'турция', 'сша', 'оаэ', 'казахстан'];

        return collect($tokens)
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => mb_strlen($token) > 1)
            ->reject(fn (string $token): bool => in_array($token, $stopWords, true) || in_array($token, $regionWords, true))
            ->take(5)
            ->implode(' ');
    }

    private function regionFromText(string $normalized): ?string
    {
        return match (true) {
            Str::contains($normalized, ['turkey', 'turkiye', 'türkiye', 'турц']) || preg_match('/\btr\b/', $normalized) === 1 => 'turkey',
            Str::contains($normalized, ['united states', 'usa', 'сша']) || preg_match('/\bus\b/', $normalized) === 1 => 'united states',
            Str::contains($normalized, ['uae', 'оаэ']) || preg_match('/\bae\b/', $normalized) === 1 => 'uae',
            Str::contains($normalized, ['united kingdom', 'great britain', 'uk', 'брит']) || preg_match('/\bgb\b/', $normalized) === 1 => 'united kingdom',
            Str::contains($normalized, ['kazakhstan', 'казахстан']) || preg_match('/\bkz\b/', $normalized) === 1 => 'kazakhstan',
            Str::contains($normalized, ['argentina', 'аргентин']) || preg_match('/\bar\b/', $normalized) === 1 => 'argentina',
            default => null,
        };
    }

    private function countryForRegion(?string $region): string
    {
        return match ($region) {
            'turkey' => 'TR',
            'united states' => 'US',
            'uae' => 'AE',
            'united kingdom' => 'GB',
            'kazakhstan' => 'KZ',
            'argentina' => 'AR',
            default => 'US',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResult(array $item, string $country, ?string $region): array
    {
        $price = $item['formattedPrice'] ?? null;
        if ($price === null && array_key_exists('price', $item)) {
            $price = ((float) $item['price']) <= 0.0 ? 'Free' : (string) $item['price'];
        }
        $name = (string) ($item['trackName'] ?? '');
        $genre = (string) ($item['primaryGenreName'] ?? '');
        $isFreeInstall = $this->isFreeInstallPrice($price);
        $monetizationNote = $this->monetizationNote($name, $genre, $isFreeInstall);

        return [
            'source' => 'apple_app_store',
            'name' => $name,
            'developer' => (string) ($item['sellerName'] ?? $item['artistName'] ?? ''),
            'bundle_id' => (string) ($item['bundleId'] ?? ''),
            'app_id' => (int) ($item['trackId'] ?? 0),
            'region' => $region ?? Str::lower($country),
            'country' => $country,
            'price' => (string) ($price ?? 'Unknown'),
            'install_price' => (string) ($price ?? 'Unknown'),
            'install_price_label' => 'Установка: '.(string) ($price ?? 'Unknown'),
            'commerce_price_scope' => 'app_download',
            'monetization_model' => $isFreeInstall ? 'free_install_may_have_subscription_or_iap' : 'paid_install_or_purchase',
            'monetization_note' => $monetizationNote,
            'currency' => (string) ($item['currency'] ?? ''),
            'rating' => isset($item['averageUserRating']) ? round((float) $item['averageUserRating'], 2) : null,
            'genre' => $genre,
            'url' => (string) ($item['trackViewUrl'] ?? ''),
            'artwork_url' => (string) ($item['artworkUrl100'] ?? ''),
        ];
    }

    private function isFreeInstallPrice(mixed $price): bool
    {
        $value = mb_strtolower(trim((string) $price));

        return in_array($value, ['free', 'бесплатно', '0', '0.0', '0.00'], true);
    }

    private function monetizationNote(string $name, string $genre, bool $isFreeInstall): string
    {
        $haystack = $this->normalize($name.' '.$genre);
        $subscriptionLikely = Str::contains($haystack, [
            'apple music',
            'spotify',
            'youtube',
            'netflix',
            'music',
            'podcast',
            'streaming',
            'subscription',
        ]);

        if ($isFreeInstall && $subscriptionLikely) {
            return 'Приложение бесплатно скачать, но подписка или покупки внутри приложения оплачиваются отдельно в App Store этого региона.';
        }

        if ($isFreeInstall) {
            return 'Цена Free означает бесплатную установку приложения; покупки внутри приложения или подписки могут оплачиваться отдельно.';
        }

        return 'Это цена установки/покупки приложения в App Store; подписки и покупки внутри приложения могут отличаться.';
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', 'ı', 'İ'], ['е', 'i', 'i'], $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }
}
