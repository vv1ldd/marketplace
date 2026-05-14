<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\WildflowCatalog;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Generates product card images using Intervention Image v4 and Real-ESRGAN.
 */
class CardImageService
{
    const CARD_W = 1027;

    const CARD_H = 1366;

    /** Квадратная обложка для /redeem (cover из основной light-карточки). */
    private const REDEEM_SQ = 1024;

    protected ?Shop $shop = null;

    protected string $locale = 'EN';

    private array $countries;

    private ImageManager $manager;

    public function __construct()
    {
        $this->countries = \DB::table('mapping_countries')
            ->pluck('name_ru', 'code')
            ->toArray();

        $this->manager = new ImageManager(new Driver);
    }

    public static function getBackgroundUploadPath(int $shopId): string
    {
        return "img/card/sh_{$shopId}/bg.png";
    }

    // ------------------------------------------------------------------ //
    //  PUBLIC API
    // ------------------------------------------------------------------ //

    public function generateForCatalogItem(WildflowCatalog $item, $shop, string $template = 'light', bool $force = false, ?string $localeOverride = null): array
    {
        set_time_limit(120);
        
        if ($shop instanceof \App\Models\LegalEntity) {
            $shop = $shop->shops()->first();
        }

        if (! $shop) {
             return ['images' => [], 'title' => null, 'description' => null];
        }

        $this->shop = $shop;
        $this->locale = $localeOverride !== null
            ? $this->normalizeCardImageLocale($localeOverride)
            : $this->shop->getCardImageLocale();
        try {
            $images = [
                'main' => $this->render($item, 'light', $force),
                'dark' => $this->render($item, 'dark', $force),
                'nft' => $this->render($item, 'nft', $force),
                'origin' => $this->render($item, 'origin', $force),
                'info' => $this->render($item, 'info', $force),
                'white' => $this->render($item, 'white', $force),
                'blend' => $this->render($item, 'blend', $force),
                'instruction' => $this->render($item, 'instruction', $force),
                'support' => $this->render($item, 'support', $force),
            ];
            $redeemRel = $this->renderRedeemSquare($item, $force);
            if ($redeemRel !== '') {
                $images['redeem'] = $redeemRel;
            }

            return [
                'images' => $images,
                'title' => $this->generateTitle($item),
                'description' => $this->generateDescription($item),
            ];
        } catch (\Throwable $e) {
            Log::error("Product kit generation failed for SKU {$item->sku}: ".$e->getMessage()."\n".$e->getTraceAsString());

            return ['images' => [], 'title' => null, 'description' => null];
        }
    }

    // ------------------------------------------------------------------ //
    //  CORE RENDERING ENGINE
    // ------------------------------------------------------------------ //

    private function render(WildflowCatalog $item, string $template, bool $force = false): string
    {
        $folder = "img/card/sh_{$this->shop?->id}";
        $suffix = match ($template) {
            'dark' => '_dark',
            'origin' => '_origin',
            'nft' => '_nft',
            'info' => '_info',
            'white' => '_white',
            'blend' => '_blend',
            'instruction' => '_instruction',
            'support' => '_support',
            default => ''
        };
        $relativePath = "{$folder}/{$item->sku}{$suffix}_v3.jpg";
        $absolutePath = public_path($relativePath);

        if (! $force && file_exists($absolutePath)) {
            return $relativePath;
        }

        $canvas = $this->manager->createImage(self::CARD_W, self::CARD_H);

        match ($template) {
            'nft' => $this->renderNFT($canvas, $item),
            'origin' => $this->renderOrigin($canvas, $item),
            'dark' => $this->renderDark($canvas, $item),
            'info' => $this->renderInfo($canvas, $item),
            'white' => $this->renderWhite($canvas, $item),
            'blend' => $this->renderBlend($canvas, $item),
            'instruction' => $this->renderInstruction($canvas, $item),
            'support' => $this->renderSupport($canvas, $item),
            default => $this->renderLight($canvas, $item),
        };

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }
        $canvas->encode(new \Intervention\Image\Encoders\JpegEncoder(90))->save($absolutePath);

        return $relativePath;
    }

    /**
     * Квадрат 1024×1024 для redeem: центральный crop основной light-карточки.
     */
    private function renderRedeemSquare(WildflowCatalog $item, bool $force = false): string
    {
        $folder = "img/card/sh_{$this->shop?->id}";
        $relativePath = "{$folder}/{$item->sku}_redeem_v3.jpg";
        $absolutePath = public_path($relativePath);

        if (! $force && is_file($absolutePath)) {
            return $relativePath;
        }

        $lightAbsolute = public_path("{$folder}/{$item->sku}_v3.jpg");
        if (! is_file($lightAbsolute)) {
            $this->render($item, 'light', $force);
        }
        if (! is_file($lightAbsolute)) {
            Log::warning("Redeem square skipped for {$item->sku}: missing light card");

            return '';
        }

        try {
            $bytes = file_get_contents($lightAbsolute);
            if ($bytes === false) {
                return '';
            }
            $img = $this->manager->decode($bytes)->cover(self::REDEEM_SQ, self::REDEEM_SQ);
            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0775, true);
            }
            $img->encode(new \Intervention\Image\Encoders\JpegEncoder(88))->save($absolutePath);
        } catch (\Throwable $e) {
            Log::error("Redeem square failed for {$item->sku}: ".$e->getMessage());

            return '';
        }

        return $relativePath;
    }

    // ------------------------------------------------------------------ //
    //  TEMPLATES
    // ------------------------------------------------------------------ //

    private function renderLight(ImageInterface $image, WildflowCatalog $item): void
    {
        $brandInfo = $this->getBrandColor($item);
        $bgColor = $brandInfo['color'];
        $isLight = $this->isColorLight($brandInfo['rgb']);

        $this->drawVerticalGradient($image, $brandInfo['rgb'], 0.15);

        $textColor = $isLight ? '#1a1a1a' : '#ffffff';

        $this->overlayLogo($image, $item, self::CARD_W, self::CARD_H, false, true);
        $this->drawNativePrice($image, $item, self::CARD_W, self::CARD_H - 180, $isLight);
        $this->drawActivationLabel($image, self::CARD_W, self::CARD_H, $isLight);

        $this->drawShopNameAtBottom($image, $textColor);

        $regionCode = $item->region?->code ?? 'GLB';
        $regionName = $this->getRegionName($item->region, $this->locale);
        $this->drawRegion($image, $regionCode, $regionName, self::CARD_H, ! $isLight);
    }

    private function renderDark(ImageInterface $image, WildflowCatalog $item): void
    {
        $brandInfo = $this->getBrandColor($item);
        $bgColor = $brandInfo['color'];
        $isLight = $this->isColorLight($brandInfo['rgb']);

        $this->drawVerticalGradient($image, $brandInfo['rgb'], 0.15);
        $this->overlayLogo($image, $item, self::CARD_W, self::CARD_H, false, true);

        $textColor = $isLight ? '#141428' : '#ffffff';
        $accentColor = $isLight ? '#6432dc' : '#a06eff';
        $fontBold = public_path('fonts/Inter-Black.otf');

        $this->drawShopNameAtBottom($image, $accentColor);

        $locale = $this->locale;
        $typeFont = $this->getFontForLocale($locale, bold: true);
        $typeLabel = $this->getCardTypeLabel($item);
        $lines = explode("\n", $typeLabel);
        foreach ($lines as $idx => $line) {
            $image->text($line, 60, 320 + ($idx * 90), function ($font) use ($typeFont, $textColor) {
                $font->filename($typeFont);
                $font->size(64);
                $font->color($textColor);
            });
        }

        $this->drawNativePrice($image, $item, self::CARD_W, self::CARD_H - 180, $isLight);
        $this->drawActivationLabel($image, self::CARD_W, self::CARD_H, $isLight);
        $regionCode = $item->region?->code ?? 'GLB';
        $regionName = $this->getRegionName($item->region, $this->locale);
        $this->drawRegion($image, $regionCode, $regionName, self::CARD_H, ! $isLight);
    }

    private function renderNFT(ImageInterface $image, WildflowCatalog $item): void
    {
        $brandInfo = $this->getBrandColor($item);
        $bgColor = $brandInfo['color'];
        $isLight = $this->isColorLight($brandInfo['rgb']);

        $this->drawVerticalGradient($image, $brandInfo['rgb'], 0.15);
        $this->overlayLogo($image, $item, self::CARD_W, self::CARD_H, ! $isLight);

        $accentColor = $brandInfo['color'];
        $textColor = $isLight ? '#141428' : '#ffffff';
        $fontBold = public_path('fonts/Inter-Black.otf');

        $this->drawShopNameAtBottom($image, $textColor);

        $this->drawNativePrice($image, $item, self::CARD_W, 145, $isLight);
        $this->drawActivationLabel($image, self::CARD_W, self::CARD_H, $isLight);

        $regionCode = $item->region?->code ?? 'GLB';
        $regionName = $this->getRegionName($item->region, $this->locale);
        $this->drawRegion($image, $regionCode, $regionName, self::CARD_H, ! $isLight);

        $image->drawRectangle(function ($draw) use ($accentColor) {
            $draw->at(30, 30);
            $draw->size((int) (self::CARD_W - 60), (int) (self::CARD_H - 60));
            $draw->border($accentColor, 2);
        });
    }

    private function renderOrigin(ImageInterface $image, WildflowCatalog $item): void
    {
        // A sophisticated neutral slate gray background
        // that provides excellent contrast for both black and white logos
        $image->fill('#8b95a1');

        $this->overlayLogo($image, $item, self::CARD_W, self::CARD_H, false, true);
        $this->drawPriceBadge($image, $this->getPriceText($item), self::CARD_W, 60);

        // Dark bottom overlay for high text contrast
        $image->drawRectangle(function ($draw) {
            $draw->at(0, (int) (self::CARD_H - 180));
            $draw->size(self::CARD_W, 180);
            $draw->background('rgba(0,0,0,0.85)');
        });

        $this->drawShopNameAtBottom($image, '#ffffff');
        $this->drawActivationLabel($image, self::CARD_W, self::CARD_H, false);

        $regionCode = $item->region?->code ?? 'GLB';
        $regionName = $this->getRegionName($item->region, $this->locale);
        $this->drawRegion($image, $regionCode, $regionName, self::CARD_H, true);
    }

    private function renderInfo(ImageInterface $image, WildflowCatalog $item): void
    {
        // Neutral background for optimal visibility of both white and black logos
        $bgColor = '#b2bec3';
        $image->fill($bgColor);

        $isLight = true; // Background is relatively light
        $textColor = '#1a1a1a';
        $fontBold = $this->getFontForLocale($this->locale, true);

        // Add the logo in the center
        $this->overlayLogo($image, $item, self::CARD_W, self::CARD_H, false, false);

        // Footer (Price + Region + Shop Name)
        $this->drawNativePrice($image, $item, self::CARD_W, self::CARD_H - 150, $isLight);

        $image->drawRectangle(function ($draw) {
            $draw->at(0, (int) (self::CARD_H - 150));
            $draw->size(self::CARD_W, 150);
            $draw->background('rgba(255,255,255,0.7)');
        });

        $this->drawShopNameAtBottom($image, $textColor);

        $regionCode = $item->region?->code ?? 'GLB';
        $regionName = $this->getRegionName($item->region, $this->locale);
        $this->drawRegion($image, $regionCode, $regionName, self::CARD_H, false);

        // "Instant Delivery" Vertical Label
        $accentColor = 'rgba(0,0,0,0.15)';
        $image->text('INSTANT DELIVERY', self::CARD_W - 40, (int) (self::CARD_H / 2), function ($font) use ($fontBold, $accentColor) {
            $font->filename($fontBold);
            $font->size(32);
            $font->color($accentColor);
            $font->align('center', 'center');
            $font->angle(90);
        });
    }

    private function renderWhite(ImageInterface $image, WildflowCatalog $item): void
    {
        $brandInfo = $this->getBrandColor($item);
        $image->fill('#ffffff');

        $textColor = '#1a1a1a';
        $fontBold = public_path('fonts/Inter-Black.otf');

        // 1. Logo (Center)
        $this->overlayLogo($image, $item, self::CARD_W, self::CARD_H, false, true);

        // 2. Footer (Price + Region + Shop Name)
        $this->drawNativePrice($image, $item, self::CARD_W, self::CARD_H - 180, true);
        $this->drawActivationLabel($image, self::CARD_W, self::CARD_H, true);
        $this->drawShopNameAtBottom($image, $textColor);

        $regionCode = $item->region?->code ?? 'GLB';
        $regionName = $this->getRegionName($item->region, $this->locale);
        $this->drawRegion($image, $regionCode, $regionName, self::CARD_H, false);

    }

    private function renderInstruction(ImageInterface $image, WildflowCatalog $item): void
    {
        $brandInfo = $this->getBrandColor($item);
        $bgColor = $brandInfo['color'];
        $isLight = $this->isColorLight($brandInfo['rgb']);

        $this->drawVerticalGradient($image, $brandInfo['rgb'], 0.25);
        $textColor = $isLight ? '#1a1a1a' : '#ffffff';
        $fontBold = $this->getFontForLocale($this->locale, true);

        $domain = rtrim(preg_replace('#^https?://#', '', $this->shop?->domain ?? 'meanly.io'), '/');
        $redeemUrlFull = $this->shop?->getEffectiveRedeemUrl()
            ?? ('https://'.$domain.'/redeem');
        $redeemStepTarget = preg_replace('#^https?://#', '', $redeemUrlFull);

        $header = match ($this->locale) {
            'RU' => 'ИНСТРУКЦИЯ ПО АКТИВАЦИИ',
            'GE' => 'გააქტიურების ინსტრუქცია',
            'ES' => 'INSTRUCCIONES DE ACTIVACIÓN',
            'TR' => 'AKTİVASYON TALİMATLARI',
            'TK' => 'AKTIWLEŞDIRME GÖZÜKDIRIJISI',
            default => 'ACTIVATION INSTRUCTION'
        };

        $steps = match ($this->locale) {
            'RU' => [
                "1. Перейдите на сайт {$redeemStepTarget}",
                '2. Введите полученный после покупки ваучер',
                '3. Нажмите кнопку «Активировать»',
                '4. Получите финальный код продукта',
            ],
            'GE' => [
                "1. გადადით {$redeemStepTarget}",
                '2. შეიყვანეთ შეძენილი ვაუჩერი',
                '3. დააჭირეთ აქტივაციის ღილაკს',
                '4. მიიღეთ პროდუქტის საბოლოო კოდი',
            ],
            'ES' => [
                "1. Ve a {$redeemStepTarget}",
                '2. Ingresa el cupón comprado',
                '3. Haz clic en el botón Activar',
                '4. Recibe el código final del producto',
            ],
            'TR' => [
                "1. {$redeemStepTarget} adresine gidin",
                '2. Satın aldığınız kuponu girin',
                '3. Etkinleştir düğmesine tıklayın',
                '4. Son ürün kodunuzu alın',
            ],
            'TK' => [
                "1. {$redeemStepTarget} sahypasyna giriň",
                '2. Satyn alnan wauçeri giriziň',
                '3. Aktiwleşdir düwmesine basyň',
                '4. Ahyrky önüm koduny alyň',
            ],
            default => [
                "1. Go to {$redeemStepTarget}",
                '2. Enter your purchased voucher',
                '3. Click the Activate button',
                '4. Receive your final product code',
            ]
        };

        // Header
        $image->text($header, 60, 100, function ($font) use ($fontBold, $textColor) {
            $font->filename($fontBold);
            $font->size(38);
            $font->color($textColor);
        });

        foreach ($steps as $idx => $step) {
            $image->text($step, 60, 250 + ($idx * 120), function ($font) use ($fontBold, $textColor) {
                $font->filename($fontBold);
                $font->size(32);
                $font->color($textColor);
            });
        }

        // QR на фактическую страницу активации
        $this->drawQrCode($image, $redeemUrlFull, (int) (self::CARD_W - 320), (int) (self::CARD_H - 350), 250);

        $qrLabel = match ($this->locale) {
            'RU' => 'Сканируйте для активации',
            'TK' => 'Aktiwleşdirmek üçin skanirläň',
            default => 'Scan to activate'
        };

        $image->text($qrLabel, (int) (self::CARD_W - 195), (int) (self::CARD_H - 80), function ($font) use ($fontBold, $textColor) {
            $font->filename($fontBold);
            $font->size(18);
            $font->color($textColor);
            $font->align('center', 'center');
        });
    }

    private function drawQrCode(ImageInterface $image, string $url, int $x, int $y, int $size): void
    {
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'connectPaths' => true,
        ]);

        $qrcode = (new QRCode($options))->render($url);
        $qrImage = $this->manager->decode($qrcode);
        $qrImage->resize($size, $size);

        $image->insert($qrImage, $x, $y, 'top-left');
    }

    private function renderSupport(ImageInterface $image, WildflowCatalog $item): void
    {
        $brandInfo = $this->getBrandColor($item);
        $isLight = $this->isColorLight($brandInfo['rgb']);

        $this->drawVerticalGradient($image, $brandInfo['rgb'], 0.3);
        $textColor = $isLight ? '#1a1a1a' : '#ffffff';
        $fontBold = $this->getFontForLocale($this->locale, true);

        $header = match ($this->locale) {
            'RU' => 'СЛУЖБА ПОДДЕРЖКИ',
            'GE' => 'მხარდაჭერის სამსახური',
            'ES' => 'SOPORTE TÉCNICO',
            'TR' => 'MÜŞTERİ HİZMETLERİ',
            'TK' => 'MÜŞDERI GOLDAWY',
            default => 'CUSTOMER SUPPORT'
        };

        $msg = match ($this->locale) {
            'RU' => 'Если у вас возникли сложности с активацией или появились вопросы — мы всегда на связи!',
            'GE' => 'თუ გაგიჩნდათ კითხვები ან პრობლემები გააქტიურებისას — ჩვენ ყოველთვის კავშირზე ვართ!',
            'ES' => '¡Si tienes algún problema con la activación o alguna pregunta, siempre estamos aquí para ayudarte!',
            'TR' => 'Aktivasyonla ilgili herhangi bir sorun yaşarsanız veya sorularınız olursa her zaman yanınızdayız!',
            'TK' => 'Aktiwasiýa bilen baglanyşykly kynçylyklar ýa-da soraglar ýüze çyksa — biz hemişe aragatnaşykda!',
            default => 'If you have any difficulties with activation or any questions — we are always in touch!'
        };

        $image->text($header, (int) (self::CARD_W / 2), 200, function ($font) use ($fontBold, $textColor) {
            $font->filename($fontBold);
            $font->size(48);
            $font->color($textColor);
            $font->align('center', 'center');
        });

        $this->drawWrappedText($image, $msg, (int) (self::CARD_W / 2), 400, 800, 32, $textColor, $fontBold);

        $currentY = 700;

        if (! empty($this->shop?->support_telegram)) {
            $tg = $this->shop?->support_telegram;
            if (! str_starts_with($tg, '@')) {
                $tg = '@'.ltrim($tg, '@');
            }

            $image->text('TG: '.strtoupper($tg), (int) (self::CARD_W / 2), $currentY, function ($font) use ($fontBold, $textColor) {
                $font->filename($fontBold);
                $font->size(42);
                $font->color($textColor);
                $font->align('center', 'center');
            });
            $currentY += 100;
        }

        if (! empty($this->shop?->support_email)) {
            $email = strtoupper($this->shop?->support_email);
            $image->text($email, (int) (self::CARD_W / 2), $currentY, function ($font) use ($fontBold, $textColor) {
                $font->filename($fontBold);
                $font->size(32);
                $font->color($textColor);
                $font->align('center', 'center');
            });
        }
    }

    private function drawWrappedText(ImageInterface $image, string $text, int $x, int $y, int $maxWidth, int $fontSize, string $color, string $fontFile): void
    {
        $words = explode(' ', $text);
        $line = '';
        $currentY = $y;

        foreach ($words as $word) {
            $testLine = $line.$word.' ';
            if (strlen($testLine) * ($fontSize * 0.5) > $maxWidth) {
                $image->text(trim($line), $x, $currentY, function ($font) use ($fontFile, $fontSize, $color) {
                    $font->filename($fontFile);
                    $font->size($fontSize);
                    $font->color($color);
                    $font->align('center', 'center');
                });
                $line = $word.' ';
                $currentY += (int) ($fontSize * 1.5);
            } else {
                $line = $testLine;
            }
        }
        $image->text(trim($line), $x, $currentY, function ($font) use ($fontFile, $fontSize, $color) {
            $font->filename($fontFile);
            $font->size($fontSize);
            $font->color($color);
            $font->align('center', 'center');
        });
    }

    private function renderBlend(ImageInterface $image, WildflowCatalog $item): void
    {
        $brandInfo = $this->getBrandColor($item);
        $bgColor = $brandInfo['color'];
        $isLight = $this->isColorLight($brandInfo['rgb']);

        $this->drawVerticalGradient($image, $brandInfo['rgb'], 0.15);

        // Apply a subtle vignette to soften the edges
        $vignetteColor = $isLight ? 'rgba(0,0,0,0.05)' : 'rgba(0,0,0,0.15)';
        $image->drawRectangle(function ($draw) use ($vignetteColor) {
            $draw->at(0, 0);
            $draw->size(self::CARD_W, self::CARD_H);
            $draw->border($vignetteColor, 120);
        });

        $textColor = $isLight ? '#1a1a1a' : '#ffffff';

        // Overlay with GLOW for soft transition
        $this->overlayLogo($image, $item, self::CARD_W, self::CARD_H, true);

        $this->drawNativePrice($image, $item, self::CARD_W, self::CARD_H - 180, $isLight);
        $this->drawActivationLabel($image, self::CARD_W, self::CARD_H, $isLight);

        $this->drawShopNameAtBottom($image, $textColor);

        $regionCode = $item->region?->code ?? 'GLB';
        $regionName = $this->getRegionName($item->region, $this->locale);
        $this->drawRegion($image, $regionCode, $regionName, self::CARD_H, ! $isLight);
    }

    // ------------------------------------------------------------------ //
    //  COMPONENT DRAWING
    // ------------------------------------------------------------------ //

    private function overlayLogo(ImageInterface $image, WildflowCatalog $item, int $W, int $H, bool $withGlow = false, bool $withShadow = false): void
    {
        $logoPath = $this->resolveLogoPath($item);
        if (! $logoPath) {
            $this->drawSyntheticLogo($image, $item, $W, $H);

            return;
        }

        try {
            $logo = $this->manager->decode($logoPath);
            $isSvg = str_contains($logoPath, '_v2');

            // For SVG-derived logos, we only need to trim transparency
            if (! $isSvg) {
                $logo = $this->removeLogoBackground($logo);
            }
            $logo->trim(0);

            // Maintain aspect ratio! ONLY specify width.
            $logo->scale(width: 750);

            // Removed glow and shadow circles as they cause collisions with rounded stickers
            $image->insert($logo, 0, (int) ($H * -0.05), 'center');
        } catch (\Throwable $e) {
            Log::warning("Logo placement failed for {$item->sku}: ".$e->getMessage());
            $this->drawSyntheticLogo($image, $item, $W, $H);
        }
    }

    private function drawNativePrice(ImageInterface $image, WildflowCatalog $item, int $W, int $y, bool $isLight): void
    {
        $priceText = $this->getPriceText($item);
        $fontBold = public_path('fonts/Inter-Black.otf');
        $bgColor = $isLight ? 'rgba(0,0,0,0.8)' : 'rgba(255,255,255,0.9)';
        $textColor = $isLight ? '#ffffff' : '#000000';

        $approxWidth = (int) (mb_strlen($priceText) * 45 + 80);
        $image->drawRectangle(function ($draw) use ($W, $approxWidth, $y, $bgColor) {
            $draw->at((int) ($W - $approxWidth - 60), (int) $y);
            $draw->size($approxWidth, 120);
            $draw->background($bgColor);
        });

        $image->text($priceText, (int) ($W - 60 - ($approxWidth / 2)), (int) ($y + 60), function ($font) use ($fontBold, $textColor) {
            $font->filename($fontBold);
            $font->size(72);
            $font->color($textColor);
            $font->align('center', 'center');
        });
    }

    private function drawRegion(ImageInterface $image, string $code, string $name, int $H, bool $isDarkBackground): void
    {
        $fontBold = public_path('fonts/Inter-Black.otf');
        $textColor = $isDarkBackground ? '#ffffff' : '#1a1a1a';

        // 1. Check and Draw Flag first to determine X offset
        $flagPath = public_path('img/flag/'.strtoupper($code).'.png');
        $textX = 60;
        if (file_exists($flagPath)) {
            try {
                $flag = $this->manager->decode($flagPath);
                $flag->resize(110, 70);
                $image->insert($flag, 60, 90, 'top-left');
                $textX = 190; // 60 + 110 + 20px padding
            } catch (\Throwable $e) {
            }
        }

        // 2. Draw Region Name next to the flag
        $image->text(mb_strtoupper($name), $textX, 150, function ($font) use ($fontBold, $textColor) {
            $font->filename($fontBold);
            $font->size(54);
            $font->color($textColor);
        });
    }

    private function drawPriceBadge(ImageInterface $image, string $text, int $W, int $y): void
    {
        $fontBold = public_path('fonts/Inter-Black.otf');
        $image->drawRectangle(function ($draw) use ($W, $y) {
            $draw->at($W - 450, $y);
            $draw->size(400, 130);
            $draw->background('#000000');
        });
        $image->text($text, $W - 250, $y + 65, function ($font) use ($fontBold) {
            $font->filename($fontBold);
            $font->size(60);
            $font->color('#ffffff');
            $font->align('center', 'center');
        });
    }

    // ------------------------------------------------------------------ //
    //  NEURAL & ASSET HELPERS
    // ------------------------------------------------------------------ //

    private function convertSvgToPng(string $svgPath, string $slug): ?string
    {
        $pngPath = public_path("storage/brands/enhanced/{$slug}_v3.png");

        if (file_exists($pngPath)) {
            return $pngPath;
        }

        if (! is_dir(dirname($pngPath))) {
            mkdir(dirname($pngPath), 0775, true);
        }

        // Use 600 DPI for high quality without overcomplicating with IM-side trimming/resizing
        $command = 'magick -background none -density 600 '.escapeshellarg($svgPath).' '.escapeshellarg($pngPath);
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($pngPath)) {
            return $pngPath;
        }

        return null;
    }

    public function enhanceLogoWithAI(string $path, ?string $slug = null): ?string
    {
        $hash = md5_file($path);
        $fileName = ($slug ?: $hash).'.png';
        $enhancedPath = public_path("storage/brands/enhanced/{$fileName}");

        if (file_exists($enhancedPath)) {
            return $enhancedPath;
        }

        if (! is_dir(dirname($enhancedPath))) {
            mkdir(dirname($enhancedPath), 0775, true);
        }

        $binaryName = (PHP_OS_FAMILY === 'Darwin') ? 'realesrgan-ncnn-vulkan' : 'realesrgan-ubuntu';
        $binary = base_path("bin/{$binaryName}");

        if (! file_exists($binary)) {
            $linuxFallback = base_path('bin/realesrgan-ncnn-vulkan-v0.2.0-ubuntu/realesrgan-ncnn-vulkan');
            if (PHP_OS_FAMILY !== 'Darwin' && file_exists($linuxFallback)) {
                $binary = $linuxFallback;
            } else {
                return null;
            }
        }

        $cmd = "{$binary} -i ".escapeshellarg($path).' -o '.escapeshellarg($enhancedPath).' -n realesrgan-x4plus-anime -s 4 -f png -m '.escapeshellarg(base_path('bin/models'));
        shell_exec($cmd);

        return file_exists($enhancedPath) ? $enhancedPath : null;
    }

    public function resolveLogoPath(WildflowCatalog $item): ?string
    {
        $data = is_array($item->data) ? $item->data : (json_decode($item->data, true) ?: []);
        $slug = $item->brand?->slug ?: data_get($data, 'brand_slug') ?: data_get($data, 'data.brand_slug');

        if (! $slug) {
            return null;
        }

        if ($item->brand) {
            $brand = $item->brand;

            // 1. Database explicit paths (Highest priority)
            if ($brand->logo_enhanced && file_exists(public_path($brand->logo_enhanced))) {
                return public_path($brand->logo_enhanced);
            }
            if ($brand->logo_png && file_exists(public_path($brand->logo_png))) {
                return public_path($brand->logo_png);
            }
            if ($brand->logo_svg) {
                $svgPath = public_path($brand->logo_svg);
                if (file_exists($svgPath)) {
                    return $this->convertSvgToPng($svgPath, (string) $slug);
                }
            }
            if ($brand->logo_source && file_exists(public_path($brand->logo_source))) {
                return public_path($brand->logo_source);
            }
        }

        // 2. Fallback to Filesystem Convention (Central Media Storage)
        $enhancedPaths = [
            public_path("storage/media/logos/enhanced/{$slug}.png"),
            public_path("storage/media/logos/{$slug}.png"),
            public_path("storage/media/logos/{$slug}.svg"),
        ];
        foreach ($enhancedPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // 2. SECOND PRIORITY: SVG fallback from old locations
        $svgPaths = [
            public_path("img/logos/{$slug}.svg"),
            public_path("storage/brands/svg/{$slug}.svg"),
        ];

        $svgFile = null;
        foreach ($svgPaths as $path) {
            if (file_exists($path)) {
                $svgFile = $path;
                break;
            }
        }

        if (! $svgFile && $item->brand && $item->brand->logo && str_ends_with($item->brand->logo, '.svg')) {
            $svgFile = public_path($item->brand->logo);
        }

        if ($svgFile && file_exists($svgFile)) {
            return $this->convertSvgToPng($svgFile, (string) $slug);
        }

        // 3. THIRD PRIORITY: High-res PNG from Brand Model
        if ($item->brand && $item->brand->logo && str_ends_with($item->brand->logo, '.png')) {
            $logoPath = public_path($item->brand->logo);
            if (file_exists($logoPath)) {
                return $this->enhanceLogoWithAI($logoPath, $slug);
            }
        }

        // 4. FOURTH PRIORITY: Download and enhance
        $url = data_get($data, 'image')
            ?: data_get($data, 'data.image')
            ?: data_get($data, 'data.product.image')
            ?: $item->image;

        if ($url && str_starts_with($url, 'http')) {
            $tempDir = storage_path('app/temp_logos');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }

            $rawPath = "{$tempDir}/".$slug.'_raw.png';

            if (! file_exists($rawPath)) {
                try {
                    $response = Http::timeout(10)->get($url);
                    if ($response->successful()) {
                        file_put_contents($rawPath, $response->body());
                    }
                } catch (\Throwable $e) {
                    return null;
                }
            }

            if (file_exists($rawPath)) {
                return $this->enhanceLogoWithAI($rawPath, $slug);
            }
        }

        return null;
    }

    private function getBrandColor(WildflowCatalog $item): array
    {
        $defaultRGB = ['r' => 20, 'g' => 20, 'b' => 35];

        // 1. ABSOLUTE PRIORITY: Use primary color from Database (Brands table)
        if ($item->brand && $item->brand->primary_color) {
            $hex = ltrim($item->brand->primary_color, '#');
            if (strlen($hex) === 6) {
                $rgb = [
                    'r' => hexdec(substr($hex, 0, 2)),
                    'g' => hexdec(substr($hex, 2, 2)),
                    'b' => hexdec(substr($hex, 4, 2)),
                ];

                return ['color' => $item->brand->primary_color, 'rgb' => $rgb];
            }
        }

        // 2. SECOND PRIORITY: Extract from image ONLY if no primary_color in DB
        $logoPath = $this->resolveLogoPath($item);
        if ($logoPath && file_exists($logoPath)) {
            try {
                $img = $this->manager->decode($logoPath);
                $img->trim(0); // Trim before sampling to get better edge colors
                $w = $img->width();
                $h = $img->height();

                $corners = [
                    $img->colorAt(min(5, $w - 1), min(5, $h - 1)),
                    $img->colorAt(max(0, $w - 5), min(5, $h - 1)),
                    $img->colorAt(min(5, $w - 1), max(0, $h - 5)),
                    $img->colorAt(max(0, $w - 5), max(0, $h - 5)),
                ];

                foreach ($corners as $c) {
                    if ($c->alpha()->value() > 50) {
                        $rgb = [
                            'r' => $c->red()->value(),
                            'g' => $c->green()->value(),
                            'b' => $c->blue()->value(),
                        ];

                        return ['color' => $this->rgbToString($rgb), 'rgb' => $rgb];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return ['color' => $this->rgbToString($defaultRGB), 'rgb' => $defaultRGB];
    }

    // ------------------------------------------------------------------ //
    //  UTILITIES
    // ------------------------------------------------------------------ //

    private function isColorLight(array $rgb): bool
    {
        return (0.299 * $rgb['r'] + 0.587 * $rgb['g'] + 0.114 * $rgb['b']) / 255 > 0.75;
    }

    private function removeLogoBackground(ImageInterface $logo): ImageInterface
    {
        $w = $logo->width();
        $h = $logo->height();

        // Sample corners to detect background
        $corners = [
            $logo->colorAt(0, 0),
            $logo->colorAt($w - 1, 0),
            $logo->colorAt(0, $h - 1),
            $logo->colorAt($w - 1, $h - 1),
        ];

        $isConsistent = true;
        $first = $corners[0];
        foreach ($corners as $c) {
            $diff = abs($c->red()->value() - $first->red()->value()) +
                    abs($c->green()->value() - $first->green()->value()) +
                    abs($c->blue()->value() - $first->blue()->value());
            if ($diff > 30) {
                $isConsistent = false;
                break;
            }
        }

        if ($isConsistent) {
            // If it's white or very light, it's likely a background
            $isLight = $this->isColorLight(['r' => $first->red()->value(), 'g' => $first->green()->value(), 'b' => $first->blue()->value()]);
            if ($isLight) {
                try {
                    // Flood fill with tolerance (v3 fill doesn't have tolerance, so we use multiple fills)
                    $logo->fill('rgba(255, 255, 255, 0)', 0, 0);
                    $logo->fill('rgba(255, 255, 255, 0)', $w - 1, 0);
                    $logo->fill('rgba(255, 255, 255, 0)', 0, $h - 1);
                    $logo->fill('rgba(255, 255, 255, 0)', $w - 1, $h - 1);
                } catch (\Throwable $e) {
                    $logo->trim(0);
                }
            }
        }

        return $logo;
    }

    private function drawVerticalGradient(ImageInterface $image, array $rgb, float $factor = 0.2): void
    {
        $W = $image->width();
        $H = $image->height();

        // Create a 1x2 image for the gradient
        $gradient = $this->manager->createImage(1, 2);

        // Top color (original)
        $top = $this->rgbToString($rgb);

        // Bottom color (darkened or lightened)
        $isLight = $this->isColorLight($rgb);
        $f = $isLight ? (1 - $factor) : (1 + $factor);
        $newRgb = [
            'r' => (int) max(0, min(255, $rgb['r'] * $f)),
            'g' => (int) max(0, min(255, $rgb['g'] * $f)),
            'b' => (int) max(0, min(255, $rgb['b'] * $f)),
        ];
        $bottom = $this->rgbToString($newRgb);

        $gradient->drawPixel(0, 0, $top);
        $gradient->drawPixel(0, 1, $bottom);

        // Scale it up to fill the whole image
        $gradient->resize($W, $H);

        $image->insert($gradient, 0, 0, 'top-left');
    }

    private function rgbToString(array $rgb): string
    {
        return sprintf('rgb(%d, %d, %d)', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    private function getPriceText(WildflowCatalog $item): string
    {
        $val = data_get($item->data, 'data.price')
            ?? data_get($item->data, 'price')
            ?? $item->retail_price
            ?? $item->face_value
            ?? 0;

        $currency = data_get($item->data, 'data.product.currency.code')
            ?? data_get($item->data, 'data.currency.code')
            ?? data_get($item->data, 'currency_code')
            ?? $item->currency_code
            ?? 'USD';

        return (int) $val.' '.strtoupper($currency);
    }

    private function getRegionName(?\App\Models\MappingCountry $region, string $locale): string
    {
        if (! $region) {
            return 'GLOBAL';
        }

        return match ($locale) {
            'RU' => $region->name_ru ?? $region->name_en ?? $region->code,
            'ES' => $region->name_es ?? $region->name_en ?? $region->code,
            'TR' => $region->name_tr ?? $region->name_en ?? $region->code,
            'TK' => $region->name_tk ?? $region->name_en ?? $region->code,
            default => $region->name_en ?? $region->name_ru ?? $region->code,
        };
    }

    /**
     * Явная локаль карточки (CLI, тесты). Неизвестные коды → локаль магазина.
     */
    private function normalizeCardImageLocale(string $locale): string
    {
        $u = strtoupper(trim($locale));

        return match ($u) {
            'RU', 'EN', 'GE', 'ES', 'TR', 'TK' => $u,
            default => $this->shop?->getCardImageLocale() ?? 'EN',
        };
    }

    private function getFontForLocale(string $locale, bool $bold = true): string
    {
        // Georgian requires its own Unicode-aware font
        if ($locale === 'GE') {
            $path = public_path('fonts/NotoSansGeorgian.ttf');
            if (file_exists($path)) {
                return $path;
            }
        }
        // Fallback to Inter for RU / EN and any other locale
        $font = $bold ? 'Inter-Black.otf' : 'Inter-Regular.ttf';

        return public_path('fonts/'.$font);
    }

    private function getCardTypeLabel(WildflowCatalog $item): string
    {
        $brand = strtolower($item->brand?->name ?? '');
        $title = strtolower($item->data['title'] ?? $item->data['product']['title'] ?? '');
        $locale = $this->locale;

        $topUpKeywords = [
            'playstation', 'steam', 'xbox', 'nintendo', 'roblox', 'blizzard',
            'ea play', 'google play', 'apple', 'itunes', 'spotify', 'netflix',
            'tinder', 'xsolla', 'pubg', 'free fire', 'mobile legends',
            'valorant', 'league of legends', 'riot', 'discord', 'skype',
            'v-bucks', 'fortnite', 'apex legends', 'brawl stars', 'genshin',
        ];

        foreach ($topUpKeywords as $kw) {
            if (str_contains($brand, $kw) || str_contains($title, $kw)) {
                return match ($locale) {
                    'RU' => "ПОПОЛНЕНИЕ\nБАЛАНСА",
                    'GE' => "ბალანსის\nშევსება",
                    'ES' => "RECARGAR\nSALDO",
                    'TR' => "BAKİYE\nYÜKLEME",
                    'TK' => "BALANS\nDOLDURMA",
                    default => "BALANCE\nTOP-UP",
                };
            }
        }

        return match ($locale) {
            'RU' => "ПОДАРОЧНАЯ\nКАРТА",
            'GE' => "სასაჩუქრე\nბარათი",
            'ES' => "TARJETA\nDE REGALO",
            'TR' => "HEDİYE\nKARTI",
            'TK' => "SOWGAT\nKARTY",
            default => "GIFT\nCARD",
        };
    }

    private function drawShopNameAtBottom(ImageInterface $image, string $color): void
    {
        $fontBold = public_path('fonts/Inter-Black.otf');
        $image->text(strtoupper($this->shop?->name ?? 'MARKET'), 60, (int) (self::CARD_H - 120), function ($font) use ($fontBold, $color) {
            $font->filename($fontBold);
            $font->size(38);
            $font->color($color);
        });

        if (! empty($this->shop?->domain)) {
            $domain = preg_replace('#^https?://#', '', $this->shop->domain);
            $domain = rtrim($domain, '/');
            $image->text(strtolower($domain), 60, (int) (self::CARD_H - 75), function ($font) use ($fontBold, $color) {
                $font->filename($fontBold);
                $font->size(24);
                $font->color($color);
            });
        }
    }

    private function drawHeaderWithColor(ImageInterface $image, WildflowCatalog $item, string $color): void
    {
        $fontBold = public_path('fonts/Inter-Black.otf');
        $image->text(strtoupper($this->shop?->name ?? 'MARKET'), 60, 100, function ($font) use ($fontBold, $color) {
            $font->filename($fontBold);
            $font->size(38);
            $font->color($color);
        });

        if (! empty($this->shop?->domain)) {
            $domain = preg_replace('#^https?://#', '', $this->shop->domain);
            $domain = rtrim($domain, '/');
            $image->text(strtolower($domain), 60, 145, function ($font) use ($fontBold, $color) {
                $font->filename($fontBold);
                $font->size(24);
                $font->color($color);
            });
        }
    }

    private function drawHeader(ImageInterface $image, WildflowCatalog $item): void
    {
        $this->drawHeaderWithColor($image, $item, '#1a1a1a');
    }

    private function drawSyntheticLogo(ImageInterface $image, WildflowCatalog $item, int $W, int $H): void
    {
        $image->drawCircle(function ($draw) use ($W, $H) {
            $draw->at((int) ($W / 2), (int) ($H * 0.48));
            $draw->radius(160);
            $draw->background('#6445f5');
        });
    }

    private function generateTitle(WildflowCatalog $item): string
    {
        return $item->getTitleForShop($this->shop);
    }

    private function generateDescription(WildflowCatalog $item): string
    {
        return 'Gift card for '.($item->brand?->name ?? 'service');
    }

    /**
     * Upload local image to Cloudflare R2 and return public URL.
     */
    public function uploadToR2(string $path): ?string
    {
        // Handle external URLs
        if (str_starts_with($path, 'http')) {
            try {
                $tempFile = tempnam(sys_get_temp_dir(), 'r2_');
                file_put_contents($tempFile, file_get_contents($path));
                $fileName = basename(parse_url($path, PHP_URL_PATH) ?: 'image.jpg');
                if (! str_contains($fileName, '.')) {
                    $fileName .= '.jpg';
                }

                $result = $this->uploadToR2File($tempFile, $fileName);
                @unlink($tempFile);

                return $result;
            } catch (\Throwable $e) {
                Log::error('R2 upload from URL failed: '.$e->getMessage());

                return null;
            }
        }

        return $this->uploadToR2File($path);
    }

    private function uploadToR2File(string $path, ?string $customFileName = null): ?string
    {
        if (! file_exists($path) && file_exists(public_path($path))) {
            $path = public_path($path);
        }

        if (! file_exists($path)) {
            Log::warning("R2 upload skipped: file not found at {$path}");

            return null;
        }

        try {
            $fileName = $customFileName ?: basename($path);
            $folder = 'cards/sh_'.($this->shop?->id ?? 'unknown');
            $fullPath = "{$folder}/{$fileName}";

            Storage::disk('r2')->put($fullPath, file_get_contents($path), 'public');

            $publicUrl = config('filesystems.disks.r2.url');

            return rtrim($publicUrl, '/').'/'.ltrim($fullPath, '/');
        } catch (\Throwable $e) {
            Log::error("R2 upload failed for {$path}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Upload local image to ImgBB and return public URL.
     * Required for Yandex Market as it needs public image links.
     */
    public function uploadToImgBB(string $path): ?string
    {
        // Try R2 first if configured
        if (config('filesystems.disks.r2.key')) {
            $r2Url = $this->uploadToR2($path);
            if ($r2Url) {
                return $r2Url;
            }
        }

        $apiKey = config('services.imgbb.key') ?: env('IMGBB_API_KEY');

        // Resolve relative paths to public directory
        if (! file_exists($path) && file_exists(public_path($path))) {
            $path = public_path($path);
        }

        if (! file_exists($path)) {
            Log::warning("ImgBB upload skipped: file not found at {$path}");

            return null;
        }

        try {
            $response = Http::asMultipart()
                ->post("https://api.imgbb.com/1/upload?key={$apiKey}", [
                    'image' => fopen($path, 'r'),
                ]);

            if ($response->successful()) {
                return $response->json('data.url');
            }

            Log::error("ImgBB upload failed for {$path}: ".$response->body());
        } catch (\Throwable $e) {
            Log::error('ImgBB upload exception: '.$e->getMessage());
        }

        return null;
    }

    private function drawActivationLabel(ImageInterface $image, int $W, int $H, bool $isLight): void
    {
        $locale = $this->locale;
        $text = match ($locale) {
            'RU' => 'ЦИФРОВОЙ КОД АКТИВАЦИИ',
            'GE' => 'ციფრული გააქტიურების კოდი',
            'ES' => 'CÓDIGO DIGITAL DE ACTIVACIÓN',
            'TR' => 'DİJİTAL AKTİVASYON KODU',
            'TK' => 'SANLY İŞJEŃLEŞdİRİŞ KODY',
            default => 'DIGITAL ACTIVATION CODE',
        };
        $font = $this->getFontForLocale($locale, bold: true);
        if (! file_exists($font)) {
            return;
        }

        $color = $isLight ? 'rgba(0,0,0,0.5)' : 'rgba(255,255,255,0.4)';

        $image->text($text, 60, 60, function ($fontOptions) use ($font, $color) {
            $fontOptions->filename($font);
            $fontOptions->size(24);
            $fontOptions->color($color);
            $fontOptions->align('left', 'bottom');
        });
    }
}
