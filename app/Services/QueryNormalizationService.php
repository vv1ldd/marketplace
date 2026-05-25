<?php

namespace App\Services;

use App\Models\QueryNormalizationRule;
use App\Models\QueryNormalizationSuggestion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueryNormalizationService
{
    /**
     * Normalize a raw query string using active replacement rules.
     *
     * @param string $query Raw query string
     * @return string Canonical query string
     */
    public function normalize(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        // Retrieve active rules from Cache (or database if not cached)
        $rules = Cache::rememberForever('query_normalization_rules_active', function () {
            return QueryNormalizationRule::where('is_active', true)
                ->orderByDesc('priority')
                ->get();
        });

        foreach ($rules as $rule) {
            $source = preg_quote($rule->source, '/');
            
            // Unicode-safe word boundary pattern to prevent partial token replacements
            $pattern = '/(?<![a-zA-Zа-яА-Я0-9_])' . $source . '(?![a-zA-Zа-яА-Я0-9_])/iu';
            
            $query = preg_replace($pattern, $rule->target, $query);
        }

        return trim(preg_replace('/\s+/', ' ', $query));
    }

    /**
     * Self-learning engine to analyze new search queries and suggest normalization rules.
     *
     * @param string $rawQuery Raw query input from storefront
     */
    public function generateSuggestion(string $rawQuery): void
    {
        $rawQuery = trim($rawQuery);
        $rawQueryLower = mb_strtolower($rawQuery);
        
        if (strlen($rawQueryLower) < 2 || strlen($rawQueryLower) > 40) {
            return;
        }

        try {
            // Check if a rule already exists for this exact source phrase
            if (QueryNormalizationRule::where('source', $rawQueryLower)->exists()) {
                return;
            }

            // Check if a suggestion already exists for this exact source phrase
            if (QueryNormalizationSuggestion::where('source', $rawQueryLower)->exists()) {
                return;
            }

            // Fetch target candidates (known brands and categories from config/db)
            $brands = [];
            try {
                $brands = \App\Models\Brand::pluck('name')
                    ->filter()
                    ->map(fn($n) => trim($n))
                    ->unique()
                    ->all();
            } catch (\Throwable $e) {
                // Fallback
            }

            if (empty($brands)) {
                $brands = ['Steam', 'PlayStation', 'Xbox', 'Nintendo', 'Apple', 'Spotify', 'Roblox', 'PUBG'];
            }

            $categories = array_keys((array) config('catalog_taxonomy.categories', []));
            $targets = array_unique(array_merge($brands, $categories));

            $transliterated = $this->transliterate($rawQueryLower);

            foreach ($targets as $target) {
                $targetLower = mb_strtolower($target);

                if ($targetLower === $rawQueryLower) {
                    continue;
                }

                // Check 1: Exact transliteration match
                if ($transliterated === $targetLower) {
                    $this->saveSuggestion($rawQueryLower, $target, 0.95, 'Exact phonetic transliteration match');
                    return;
                }

                // Check 2: Low Levenshtein distance
                $distance = levenshtein($transliterated, $targetLower);
                if ($distance <= 2 && strlen($targetLower) > 3) {
                    $this->saveSuggestion($rawQueryLower, $target, 0.85, 'Low Levenshtein distance: ' . $distance);
                    return;
                }

                // Check 3: Text similarity percentage
                $similarity = 0;
                similar_text($transliterated, $targetLower, $similarity);
                if ($similarity > 75.0) {
                    $this->saveSuggestion($rawQueryLower, $target, round($similarity / 100, 2), 'High fuzzy similarity: ' . round($similarity, 1) . '%');
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Query normalization auto-suggestion generator failed: ' . $e->getMessage());
        }
    }

    /**
     * Helper to transliterate Russian Cyrillic characters to English Latin.
     */
    private function transliterate(string $text): string
    {
        $chars = [
            'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo', 'ж'=>'zh', 'з'=>'z',
            'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o', 'п'=>'p', 'р'=>'r',
            'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'х'=>'kh', 'ц'=>'ts', 'ч'=>'ch', 'ш'=>'sh', 'щ'=>'shch',
            'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
            'c'=>'s', 'x'=>'ks', 'h'=>'kh'
        ];
        return strtr($text, $chars);
    }

    private function saveSuggestion(string $source, string $target, float $confidence, string $reason): void
    {
        QueryNormalizationSuggestion::create([
            'source' => $source,
            'target' => $target,
            'confidence' => $confidence,
            'reason' => $reason,
            'status' => QueryNormalizationSuggestion::STATUS_PENDING,
        ]);
    }
}
