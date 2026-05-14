<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\WildflowCatalog;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class VideoInstructionService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    public function uploadToR2($localPath, $sku): ?string
    {
        $fileName = "videos/{$sku}.mp4";
        Storage::disk('r2')->put($fileName, file_get_contents($localPath));

        return config('filesystems.disks.r2.url').'/'.$fileName;
    }

    private function drawGradient($image, $color, $opacity = 0.2)
    {
        $w = $image->width();
        $h = $image->height();
        $gradient = $this->manager->createImage(1, 2);
        $gradient->drawRectangle(function ($draw) use ($color, $opacity) {
            $draw->at(0, 0);
            $draw->size(1, 1);
            $draw->background("rgba({$color[0]}, {$color[1]}, {$color[2]}, {$opacity})");
        });
        $gradient->drawRectangle(function ($draw) {
            $draw->at(0, 1);
            $draw->size(1, 1);
            $draw->background('rgba(0, 0, 0, 0)');
        });
        $gradient->resize($w, $h);
        $image->insert($gradient, 0, 0, 'top-left');
    }

    /**
     * Локаль строк для видео (совпадает с регионом магазина).
     */
    private function resolveVideoInstructionLocale(?Shop $shop): string
    {
        $region = strtoupper(trim((string) ($shop?->shop_region ?? '')));
        if ($region === '') {
            $region = 'RU';
        }

        return match ($region) {
            'RU', 'BY', 'KZ' => 'ru',
            'ES' => 'es',
            'TR' => 'tr',
            'TK' => 'tk',
            'GE' => 'ka',
            default => 'en',
        };
    }

    /**
     * Инструкционное видео для позиции каталога Wildflow и конкретного магазина (превью витрины, до создания Product).
     *
     * @return string|null путь вида storage/videos/{sku}.mp4 при успехе
     */
    public function generateForWildflowCatalog(
        WildflowCatalog $wfCatalog,
        ?Shop $shop,
        ?string $customRedeemUrl = null,
        ?string $voucherPrefix = null,
        bool $force = false,
    ): ?string {
        $wfCatalog->loadMissing('brand');
        $sku = $wfCatalog->sku;
        $absoluteOutput = storage_path("app/public/videos/{$sku}.mp4");

        if (! $force && is_file($absoluteOutput) && filesize($absoluteOutput) > 0) {
            return "storage/videos/{$sku}.mp4";
        }

        $fontPath = public_path('fonts/Inter-Black.otf');

        $brandHex = (string) ($wfCatalog->brand?->primary_color ?? '#111111');
        if ($brandHex !== '' && ! str_starts_with($brandHex, '#')) {
            $brandHex = '#'.$brandHex;
        }
        $rgb = $this->hexToRgb($brandHex);

        // Тот же резолв логотипа, что и для карточек — без жёсткого fallback на чужой бренд (1083).
        $productLogoPath = app(CardImageService::class)->resolveLogoPath($wfCatalog);

        // URL как в кабинете магазина: redeem_url / default_redeem_url — не APP_URL (иначе localhost в ролике).
        $displayUrl = strtoupper($this->displayRedeemUrlForVideo($shop, $customRedeemUrl));
        $videoLocale = $this->resolveVideoInstructionLocale($shop);

        $W = 1080;
        $H = 1920;

        // --- SCENE 1: SHOP BRAND (3s) — тёмный фон в духе /redeem ---
        $scene1 = $this->manager->createImage($W, $H)->fill('#09090b');
        $this->drawGradient($scene1, $rgb, 0.22);
        if ($shop && $shop->ym_logo && file_exists(public_path($shop->ym_logo))) {
            $sellerLogo = $this->manager->decode(file_get_contents(public_path($shop->ym_logo)))->scale(width: 600);
            $scene1->insert($sellerLogo, 0, 0, 'center');
        } else {
            $scene1->text(strtoupper($shop?->name ?? 'SELLER'), $W / 2, $H / 2, function ($font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(120);
                $font->color('#FFFFFF');
                $font->align('center');
            });
        }
        $path1 = storage_path("app/public/s1_{$sku}.png");
        $scene1->save($path1);

        // --- SCENE 2: PRODUCT BRAND (4s) ---
        if ($productLogoPath && is_file($productLogoPath)) {
            $scene2 = $this->manager->decode(file_get_contents($productLogoPath))->cover($W, $H)->blur(80);
            $this->drawGradient($scene2, $rgb, 0.4);
            $prodLogo = $this->manager->decode(file_get_contents($productLogoPath))->scale(width: 700);
            $scene2->insert($prodLogo, 0, 0, 'center');
        } else {
            $scene2 = $this->manager->createImage($W, $H)->fill('#09090b');
            $this->drawGradient($scene2, $rgb, 0.35);
            $label = mb_strtoupper($wfCatalog->brand_name);
            if (mb_strlen($label) > 36) {
                $label = mb_substr($label, 0, 33).'…';
            }
            $scene2->text($label, (int) ($W / 2), (int) ($H / 2), function ($font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(68);
                $font->color('#FFFFFF');
                $font->align('center');
            });
        }
        $path2 = storage_path("app/public/s2_{$sku}.png");
        $scene2->save($path2);

        // --- SCENE 3: REDEEM LINK (5s) — как финиш /redeem: чёрный, синий акцент, светлая типографика ---
        $scene3 = $this->manager->createImage($W, $H)->fill('#000000');
        $this->drawGradient($scene3, [59, 130, 246], 0.1);

        $activateHere = (string) Lang::get('video_instruction.activate_here', [], $videoLocale);
        if (mb_strlen($activateHere) > 48) {
            $activateHere = mb_substr($activateHere, 0, 45).'…';
        }
        $scene3->text($activateHere, (int) ($W / 2), (int) ($H / 2 - 140), function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(40);
            $font->color('#60a5fa');
            $font->align('center');
        });
        $urlFontSize = mb_strlen($displayUrl) > 52 ? 40 : 52;
        $scene3->text($displayUrl, (int) ($W / 2), (int) ($H / 2 + 40), function ($font) use ($fontPath, $urlFontSize) {
            $font->filename($fontPath);
            $font->size($urlFontSize);
            $font->color('#e4e4e7');
            $font->align('center');
        });
        $path3 = storage_path("app/public/s3_{$sku}.png");
        $scene3->save($path3);

        // --- SCENE 4: фиксированная подпись бренда (одинаково для всех локалей ролика) ---
        $scene4 = $this->manager->createImage($W, $H)->fill('#000000');
        $line = (string) Lang::get('video_instruction.powered_by', [], $videoLocale);
        $scene4->text($line, (int) ($W / 2), (int) ($H / 2), function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(36);
            $font->color('rgba(228, 228, 231, 0.92)');
            $font->align('center');
        });
        $path4 = storage_path("app/public/s4_{$sku}.png");
        $scene4->save($path4);

        // --- FFMPEG ASSEMBLY ---
        $v1 = storage_path("app/public/v1_{$sku}.mp4");
        $v2 = storage_path("app/public/v2_{$sku}.mp4");
        $v3 = storage_path("app/public/v3_{$sku}.mp4");
        $v4 = storage_path("app/public/v4_{$sku}.mp4");
        $ffmpeg = config('services.ffmpeg.ffmpeg_path', '/opt/homebrew/bin/ffmpeg');

        exec(sprintf('%s -loop 1 -t 3 -i %s -c:v libx264 -pix_fmt yuv420p -vf "fade=t=out:st=2.5:d=0.5" -y %s', $ffmpeg, escapeshellarg($path1), escapeshellarg($v1)));
        exec(sprintf('%s -loop 1 -t 4 -i %s -c:v libx264 -pix_fmt yuv420p -vf "fade=t=in:st=0:d=0.5,fade=t=out:st=3.5:d=0.5" -y %s', $ffmpeg, escapeshellarg($path2), escapeshellarg($v2)));
        exec(sprintf('%s -loop 1 -t 5 -i %s -c:v libx264 -pix_fmt yuv420p -vf "fade=t=in:st=0:d=0.5,fade=t=out:st=4.5:d=0.5" -y %s', $ffmpeg, escapeshellarg($path3), escapeshellarg($v3)));
        exec(sprintf('%s -loop 1 -t 3 -i %s -c:v libx264 -pix_fmt yuv420p -vf "fade=t=in:st=0:d=0.5" -y %s', $ffmpeg, escapeshellarg($path4), escapeshellarg($v4)));

        $listPath = storage_path("app/public/list_{$sku}.txt");
        file_put_contents($listPath, "file '".$v1."'\nfile '".$v2."'\nfile '".$v3."'\nfile '".$v4."'");
        if (! is_dir(dirname($absoluteOutput))) {
            mkdir(dirname($absoluteOutput), 0777, true);
        }
        exec(sprintf('%s -f concat -safe 0 -i %s -c copy -fflags +genpts -y %s', $ffmpeg, escapeshellarg($listPath), escapeshellarg($absoluteOutput)));

        @unlink($path1);
        @unlink($path2);
        @unlink($path3);
        @unlink($path4);
        @unlink($v1);
        @unlink($v2);
        @unlink($v3);
        @unlink($v4);
        @unlink($listPath);

        return file_exists($absoluteOutput) ? "storage/videos/{$sku}.mp4" : null;
    }

    public function generateForProduct(Product $product, $customRedeemUrl = null, $voucherPrefix = 'VOUCHER'): ?string
    {
        $wfCatalog = $product->wildflowCatalog()?->loadMissing('brand');
        if (! $wfCatalog) {
            return null;
        }

        $shop = $product->shop
            ?? Shop::where('voucher_prefix', $voucherPrefix)->first();

        return $this->generateForWildflowCatalog($wfCatalog, $shop, $customRedeemUrl, $voucherPrefix, true);
    }

    /**
     * Текст для кадра видео: host + path из эффективного redeem магазина (или системы), без схемы и query.
     */
    private function displayRedeemUrlForVideo(?Shop $shop, ?string $customRedeemUrl): string
    {
        $raw = trim((string) $customRedeemUrl);
        if ($raw === '' && $shop) {
            $raw = $shop->getEffectiveRedeemUrl(appendShopQueryForPlatform: false);
        }
        if ($raw === '') {
            $raw = (string) SystemSetting::get('default_redeem_url', 'https://wildcloud.ru/redeem');
        }

        $parts = parse_url($raw) ?: [];
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            $parts = parse_url((string) SystemSetting::get('default_redeem_url', 'https://wildcloud.ru/redeem')) ?: [];
            $host = strtolower((string) ($parts['host'] ?? 'wildcloud.ru'));
        }

        $path = isset($parts['path']) ? '/'.ltrim((string) $parts['path'], '/') : '';
        if ($path === '/' || $path === '') {
            $path = '/redeem';
        }

        return $host.rtrim($path, '/');
    }

    private function hexToRgb($hex)
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return [$r, $g, $b];
    }
}
