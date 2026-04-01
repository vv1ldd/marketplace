<?php

namespace App\Services;

use Intervention\Image\Drivers\Imagick\Encoders\PngEncoder;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;


class ImageGenerator
{
    public static function generate(): \Intervention\Image\Interfaces\ImageInterface
    {
        $manager = new ImageManager(Driver::class);

        // открываем изображение
        $image = $manager->decodePath(
            storage_path('app/private/base-card.png')
        );

        $image->text('100$', 860, 258, function ($font) {
            $font->filename(storage_path('app/private/Inter-Black.otf'));
            $font->size(62);
            $font->color('#7C45F5');
            $font->align('center');
        });

        $logo = $manager->decodePath(
            storage_path('app/private/logo/steam.png'),
        )
            ->resize(width: 350, height: 350);

        $image->insert($logo, alignment: 'center-center');


        $flag = $manager->decodePath(
            storage_path('app/private/flags/US.png'),
        )->resize(width: 98, height: 65);

        $image->insert($flag, x: 50, y: 165, alignment: 'bottom-left');

        $image->text("для аккаунтов\nрегиона США", x: 170, y: 1195, font: function($font) {
            $font->filename(storage_path('app/private/Inter-Black.otf'));
            $font->size(37);
            $font->color('#000000');
            $font->align('left');
        });

        return $image->save(storage_path('app/public/logo/test.png'));
    }
}
