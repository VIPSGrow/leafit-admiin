<?php
define('PRIMARY_RGB', [5, 166, 232]);

function createIcon(string $dest, int $w, int $h, string $text): void {
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);

    $bg = imagecolorallocate($im, PRIMARY_RGB[0], PRIMARY_RGB[1], PRIMARY_RGB[2]);
    $white = imagecolorallocate($im, 255, 255, 255);

    $r = (int)($w * 0.125);
    $points = [
        $r, 0,
        $w - $r, 0,
        $w, $r,
        $w, $h - $r,
        $w - $r, $h,
        $r, $h,
        0, $h - $r,
        0, $r,
    ];
    imagefilledpolygon($im, $points, 8, $bg);

    $fontSize = (int)($w * 0.32);
    $fontPaths = [
        __DIR__ . '/public/images/fonts/DejaVuSans-Bold.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/tahoma.ttf',
    ];

    $fontFile = null;
    foreach ($fontPaths as $fp) {
        if (file_exists($fp)) { $fontFile = $fp; break; }
    }

    if ($fontFile) {
        $bbox = @imagettfbbox($fontSize, 0, $fontFile, $text);
        if ($bbox !== false) {
            $tw = $bbox[2] - $bbox[0];
            $th = $bbox[1] - $bbox[7];
            $x = (int)(($w - $tw) / 2 - $bbox[0]);
            $y = (int)(($h - $th) / 2 - $bbox[7]);
            @imagettftext($im, $fontSize, 0, $x, $y, $white, $fontFile, $text);
        } else {
            imagestring($im, 5, (int)(($w - imagefontwidth(5) * strlen($text)) / 2), (int)($h * 0.65), $text, $white);
        }
    } else {
        imagestring($im, 5, (int)(($w - imagefontwidth(5) * strlen($text)) / 2), (int)($h * 0.65), $text, $white);
    }

    imagepng($im, $dest);
    imagedestroy($im);
    echo "Generated: {$dest}\n";
}

$icons = [
    ['public/images/icons/icon-16.png', 16, 16, 'L'],
    ['public/images/icons/icon-32.png', 32, 32, 'L'],
    ['public/images/icons/icon-48.png', 48, 48, 'L'],
    ['public/images/icons/icon-72.png', 72, 72, 'L'],
    ['public/images/icons/icon-96.png', 96, 96, 'L'],
    ['public/images/icons/icon-144.png', 144, 144, 'L'],
    ['public/images/icons/icon-192.png', 192, 192, 'LeafIT'],
    ['public/images/icons/icon-512.png', 512, 512, 'LeafIT'],
    ['public/images/icons/icon-maskable-192.png', 192, 192, 'LeafIT'],
    ['public/images/icons/icon-maskable-512.png', 512, 512, 'LeafIT'],
];

foreach ($icons as [$dest, $w, $h, $text]) {
    createIcon($dest, $w, $h, $text);
}

echo "\nAll icons generated!\n";
