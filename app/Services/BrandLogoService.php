<?php

namespace App\Services;

use Illuminate\Support\Str;

class BrandLogoService
{
    public static function getLogoUrl(?string $brandName): string
    {
        if (empty($brandName)) {
            return self::getPlaceholderUrl('?');
        }

        $slug = Str::slug($brandName, '');
        $slug = str_replace(['and', 'plus'], ['&', '+'], $slug); // Reverse some slugs if needed, but usually icons are at-basic
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $slug));

        $extensions = ['svg', 'png', 'jpg'];
        foreach ($extensions as $ext) {
            $path = "img/logos/{$slug}.{$ext}";
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        // Fallback to dynamic Simple Icons if local fails (optional, but since user wants sovereign, we use placeholder)
        return self::getPlaceholderUrl(substr($brandName, 0, 1));
    }

    protected static function getPlaceholderUrl(string $char): string
    {
        $char = strtoupper($char);
        $colors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899'];
        $color = $colors[ord($char) % count($colors)];
        
        // Return a data-uri SVG placeholder
        $svg = '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"40\" height=\"40\" viewBox=\"0 0 40 40\">
            <rect width=\"100%\" height=\"100%\" fill=\"' . $color . '\" rx=\"8\"/>
            <text x=\"50%\" y=\"50%\" dominant-baseline=\"middle\" text-anchor=\"middle\" fill=\"white\" font-family=\"sans-serif\" font-size=\"20\" font-weight=\"bold\">' . $char . '</text>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
