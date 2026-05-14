<?php
$path = 'public/img/logos/apple.png';
$img = @imagecreatefrompng($path);
if ($img) {
    echo "Success: Image loaded\n";
    echo "Width: " . imagesx($img) . "\n";
    echo "Height: " . imagesy($img) . "\n";
} else {
    echo "Error: Failed to load image\n";
    $info = getimagesize($path);
    print_r($info);
}
