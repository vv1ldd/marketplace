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
    public function generate(array $item): string
    {
        $manager = new ImageManager(Driver::class);

        $image = $manager->decodePath(
            storage_path('app/private/base-card.png')
        );

        $image->text($item['price'] . $item['symbol'], 860, 258, function ($font) {
            $font->filename(public_path('fonts/Inter-Black.otf'));
            $font->size(62);
            $font->color('#7C45F5');
            $font->align('center');
        });

        $logo = $manager->decodePath(
            public_path('img/logo/' . $item['category'] . '.png'),
        )
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

        $public_path = 'img/card/' . $item['sku'] . '.png';
//        $save_path = "app/public/$public_path";

        $image->save(public_path($public_path));

        return $public_path;
    }
}
