<?php

// Render the original project identity into social and mobile raster assets.
$root = dirname(__DIR__);
$im = imagecreatetruecolor(1200, 630);
$bg = imagecolorallocate($im, 13, 16, 21);
imagefill($im, 0, 0, $bg);
$hero = imagecreatefromjpeg($root.'/public/images/silver-hero.jpg');
imagecopyresampled($im, $hero, 450, 50, 0, 0, 800, 520, imagesx($hero), imagesy($hero));
$fg = imagecolorallocate($im, 228, 234, 242);
$muted = imagecolorallocate($im, 166, 182, 205);
$arial = '/System/Library/Fonts/Supplemental/Arial.ttf';
$georgia = '/System/Library/Fonts/Supplemental/Georgia.ttf';
if (is_file($arial) && is_file($georgia)) {
    imagettftext($im, 24, 0, 65, 120, $fg, $arial, 'CHANDI.LIVE');
    imagettftext($im, 44, 0, 65, 280, $fg, $georgia, 'Know the Price.');
    imagettftext($im, 44, 0, 65, 350, $fg, $georgia, 'Find the Silver.');
    imagettftext($im, 13, 0, 65, 500, $muted, $arial, 'SILVER MARKET INTELLIGENCE | PAKISTAN');
}
imagejpeg($im, $root.'/public/images/social.jpg', 90);
imagedestroy($im);
imagedestroy($hero);
$im = imagecreatetruecolor(180, 180);
imagefill($im, 0, 0, imagecolorallocate($im, 14, 17, 22));
$silver = imagecolorallocate($im, 201, 214, 233);
imagesetthickness($im, 4);
$pts = [90, 25, 145, 57, 145, 123, 90, 155, 35, 123, 35, 57];
imagepolygon($im,$pts,$silver);
imagefilledpolygon($im,[90, 61, 116, 76, 116, 105, 90, 120, 64, 105, 64, 76],$silver);
imagepng($im,$root.'/public/images/apple-touch-icon.png');
// ICO embeds PNG frames for crisp browser tabs on standard and Retina displays.
$sizes = [16, 32, 48, 64];
$frames = [];
foreach ($sizes as $size) {
    $frame = imagecreatetruecolor($size, $size);
    imagecopyresampled($frame, $im, 0, 0, 0, 0, $size, $size, 180, 180);
    imagepng($frame, $root.'/public/images/favicon-'.$size.'x'.$size.'.png');
    ob_start();
    imagepng($frame);
    $frames[] = ob_get_clean();
    imagedestroy($frame);
}
$directory = pack('vvv', 0, 1, count($frames));
$offset = 6 + 16 * count($frames);
foreach ($frames as $i => $frame) {
    $directory .= pack('CCCCvvVV', $sizes[$i], $sizes[$i], 0, 0, 1, 32, strlen($frame), $offset);
    $offset += strlen($frame);
}
file_put_contents($root.'/public/favicon.ico', $directory.implode('', $frames));
imagedestroy($im);
