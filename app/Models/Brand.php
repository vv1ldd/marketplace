<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ym_vendor_names',
        'slug',
        'logo',
        'logo_source',
        'logo_svg',
        'logo_enhanced',
        'logo_png',
        'description',
        'primary_color',
        'secondary_color',
        'text_color',
        'cover_path',
        'is_active',
        'identity_settings',
        'catalog_group_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'identity_settings' => 'array',
        'ym_vendor_names' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($brand) {
            if (empty($brand->slug)) {
                $baseSlug = Str::slug($brand->name);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$counter++;
                }

                $brand->slug = $slug;
            }
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function providerProducts()
    {
        return $this->hasMany(ProviderProduct::class);
    }

    public function mappings()
    {
        return $this->hasMany(ProviderBrandMapping::class);
    }

    public function catalogGroup()
    {
        return $this->belongsTo(CatalogGroup::class);
    }

    public function wildflowCatalogs()
    {
        return $this->hasMany(WildflowCatalog::class);
    }

    public function getTotalItemsCountAttribute(): int
    {
        return $this->products_count ?? ($this->products()->count() + $this->wildflowCatalogs()->count() + $this->providerProducts()->count());
    }

    /**
     * Get the logo URL — prioritizes enhanced/upscaled images for best quality on redeem pages.
     * Priority: logo_enhanced → logo_png → logo → BrandLogoService → catalog CDN image
     */
    public function getLogoUrlAttribute(): string
    {
        // 1. Best quality: enhanced upscaled PNG (Real-ESRGAN or manual)
        foreach (['logo_enhanced', 'logo_png'] as $field) {
            $path = $this->getRawOriginal($field);
            if (filled($path)) {
                if (str_starts_with($path, 'http')) {
                    return $path;
                }
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    return \Illuminate\Support\Facades\Storage::url($path);
                }
                // Also check public/ directly (some paths are stored relative to public/)
                if (file_exists(public_path($path))) {
                    return asset($path);
                }
            }
        }

        // 2. Regular logo field
        if ($this->logo && !str_starts_with($this->logo, 'http')) {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($this->logo)) {
                return \Illuminate\Support\Facades\Storage::url($this->logo);
            }
            if (file_exists(public_path($this->logo))) {
                return asset($this->logo);
            }
        }

        // 3. Centralized logo service (looks in public/img/logos/)
        $localLogo = \App\Services\BrandLogoService::getLogoUrl($this->name);
        if (! str_starts_with($localLogo, 'data:image')) {
            return $localLogo;
        }

        // 4. Last resort: product image from Wildflow catalog CDN
        $catalogItem = $this->wildflowCatalogs()->whereNotNull('data')->first();
        if ($catalogItem) {
            $imageUrl = data_get($catalogItem->data, 'data.product.image')
                ?? data_get($catalogItem->data, 'data.image')
                ?? data_get($catalogItem->data, 'product.image')
                ?? data_get($catalogItem->data, 'image');

            if ($imageUrl) {
                return $imageUrl;
            }
        }

        // 5. Placeholder SVG
        return $localLogo;
    }

    /**
     * Generate a beautiful SVG avatar for the brand.
     */
    public function generateDefaultLogoSvg(): string
    {
        $name = $this->name ?? 'Brand';
        $letter = mb_strtoupper(mb_substr($name, 0, 1));

        // Use brand colors or generate from name hash
        $color1 = $this->primary_color ?? $this->generateColorFromName($name, 0);
        $color2 = $this->secondary_color ?? $this->generateColorFromName($name, 40);

        $svg = '<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="grad_'.$this->id.'" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:'.$color1.';stop-opacity:1" />
                    <stop offset="100%" style="stop-color:'.$color2.';stop-opacity:1" />
                </linearGradient>
            </defs>
            <rect width="100%" height="100%" fill="url(#grad_'.$this->id.')" />
            <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="280" font-weight="900" fill="#ffffff">'.$letter.'</text>
        </svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    protected function generateColorFromName(string $name, int $offset = 0): string
    {
        $hash = md5($name);
        $hue = (hexdec(substr($hash, 0, 2)) + $offset) % 360;

        return "hsl({$hue}, 65%, 45%)";
    }
}
