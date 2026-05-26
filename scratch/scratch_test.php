<?php

require 'vendor/autoload.php';

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

$manager = new ImageManager(new Driver);
try {
    echo "Testing read()...\n";
    $img = $manager->read(file_get_contents('public/storage/media/logos/enhanced/digital.png'));
    echo 'Success! Image size: '.$img->width().'x'.$img->height()."\n";
} catch (\Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
