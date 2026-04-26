<?php

namespace App\Services;

use Intervention\Image\Drivers\Imagick\Encoders\PngEncoder;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;


class ImageGenerator
{
    private array $countries;

    public function __construct()
    {
        $this->countries = \DB::table('mapping_countries')
            ->pluck('name_ru', 'code')->toArray();
    }
    public function generate(array $item, ?string $basePath = null, ?string $logoPath = null, ?int $shopId = null): string
    {
        $manager = new ImageManager(Driver::class);

        $baseCard = $basePath ? storage_path('app/public/' . $basePath) : public_path('img/base-card.png');

        $image = $manager->decodePath($baseCard);

        $image->text($item['price'] . $item['symbol'], 860, 258, function ($font) {
            $font->filename(public_path('fonts/Inter-Black.otf'));
            $font->size(62);
            $font->color('#7C45F5');
            $font->align('center');
        });

        $logoFile = $logoPath ? storage_path('app/public/' . $logoPath) : public_path('img/logo/' . $item['category'] . '.png');

        $logo = $manager->decodePath($logoFile)
            ->scaleDown(width: 350);

        $image->insert($logo, alignment: 'center-center');


        $flag = $manager->decodePath(
            public_path('img/flag/' . strtoupper($item['region_code']) . '.png'),
        )->resize(width: 98, height: 65);

        $image->insert($flag, x: 50, y: 165, alignment: 'bottom-left');

        $image->text("для аккаунтов\nрегиона {$this->countries[$item['region_code']]}", x: 170, y: 1195, font: function ($font) {
            $font->filename(public_path('fonts/Inter-Black.otf'));
            $font->size(37);
            $font->color('#000000');
            $font->align('left');
        });

        $folder = $shopId ? "img/card/sh_$shopId" : "img/card";
        
        if (!file_exists(public_path($folder))) {
            mkdir(public_path($folder), 0775, true);
        }

        $public_path = $folder . '/' . $item['sku'] . '.png';

        $image->save(public_path($public_path));

        return $public_path;
    }
}
