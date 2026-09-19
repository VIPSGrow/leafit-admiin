<?php
define('PRIMARY_RGB', [5, 166, 232]);

$w = 64;
$h = 64;
$im = imagecreatetruecolor($w, $h);
imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $transparent);

$bg = imagecolorallocate($im, PRIMARY_RGB[0], PRIMARY_RGB[1], PRIMARY_RGB[2]);
$white = imagecolorallocate($im, 255, 255, 255);

$r = (int)($w * 0.125);
$points = [
    $r, 0, $w - $r, 0, $w, $r, $w, $h - $r,
    $w - $r, $h, $r, $h, 0, $h - $r, 0, $r,
];
imagefilledpolygon($im, $points, 8, $bg);

imagestring($im, 5, (int)(($w - imagefontwidth(5) * 2) / 2), (int)($h * 0.65), 'eS', $white);

// Save PNG version
imagepng($im, 'public/favicon.png');

// Create BMP data for ICO
$stride = (int)(($w * 3 + 3) / 4) * 4;
$bmpData = '';
for ($y = $h - 1; $y >= 0; $y--) {
    $pixelData = '';
    for ($x = 0; $x < $w; $x++) {
        $color = imagecolorat($im, $x, $y);
        $rgb = imagecolorsforindex($im, $color);
        $pixelData .= chr($rgb['blue']) . chr($rgb['green']) . chr($rgb['red']);
    }
    $pad = max(0, $stride - $w * 3);
    $bmpData .= str_repeat("\x00", 4) . $pixelData . str_repeat("\x00", $pad);
}

// ICO format
$header = pack('v', 0);
$header .= pack('v', 1);
$header .= pack('v', 1);
$header .= pack('vvVvv', $w, $h * 2, 0, 32, strlen($bmpData));
$ico = $header . $bmpData;
file_put_contents('public/favicon.ico', $ico);
echo "Generated: public/favicon.ico\n";
echo "Generated: public/favicon.png\n";

imagedestroy($im);
