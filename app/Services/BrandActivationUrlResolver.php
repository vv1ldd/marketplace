<?php

namespace App\Services;

final class BrandActivationUrlResolver
{
    /**
     * @param  string  $brandUpper  strtoupper(trim(brand))
     * @param  string  $haystackUpper  strtoupper(title + category)
     */
    public static function fallbackActivationUrl(string $brandUpper, string $haystackUpper): ?string
    {
        foreach (config('brand_activation.rules', []) as $rule) {
            if (! is_array($rule) || empty($rule['url']) || ! is_string($rule['url'])) {
                continue;
            }

            if (isset($rule['brand_contains']) && $rule['brand_contains'] !== '') {
                if (! str_contains($brandUpper, strtoupper((string) $rule['brand_contains']))) {
                    continue;
                }
            }

            if (isset($rule['haystack_contains']) && $rule['haystack_contains'] !== '') {
                if (! str_contains($haystackUpper, strtoupper((string) $rule['haystack_contains']))) {
                    continue;
                }
            }

            if (isset($rule['brand_any']) && is_array($rule['brand_any'])) {
                $matched = false;
                foreach ($rule['brand_any'] as $needle) {
                    if ($needle !== '' && str_contains($brandUpper, strtoupper((string) $needle))) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    continue;
                }
            }

            return $rule['url'];
        }

        return null;
    }
}
