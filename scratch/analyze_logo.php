<?php
$path = 'public/img/logos/playstation.png';
if (!file_exists($path)) die("File not found\n");
$img = imagecreatefrompng($path);
$w = imagesx($img);
$h = imagesy($img);

$points = [
    [0, 0], [$w-1, 0], [0, $h-1], [$w-1, $h-1],
    [10, 10], [$w-11, 10], [10, $h-11], [$w-11, $h-11],
    [(int)($w/2), 0], [(int)($w/2), $h-1]
];

foreach ($points as $p) {
    $rgba = imagecolorat($img, $p[0], $p[1]);
    $alpha = ($rgba >> 24) & 0x7F;
    $r = ($rgba >> 16) & 0xFF;
    $g = ($rgba >> 8) & 0xFF;
    $b = $rgba & 0xFF;
    echo "Point ({$p[0]}, {$p[1]}): R=$r, G=$g, B=$b, Alpha=$alpha\n";
}
