<?php

$letters = 'ABCDEFGKIJKLMNPQRSTUVWXYZ23456789';
$caplen = 4;
$width = 120; $height = 20;
$font = 'fonts/comic.ttf';
$fontsize = 14;

header('Content-type: image/png');

$im = imagecreatetruecolor($width, $height);
imagesavealpha($im, true);
$bg = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $bg);

$captcha = '';

for ($i = 0; $i < $caplen; $i++) {
    $captcha .= $letters[ rand(0, strlen($letters)-1) ];
    $x = ($width - 20) / $caplen * $i + 10;
    $x = rand($x, $x+4);
    $y = $height - ( ($height - $fontsize) / 2 );
    $curcolor = imagecolorallocate( $im, rand(0, 100), rand(0, 100), rand(0, 100) );
    $angle = rand(-25, 25);
    imagettftext($im, $fontsize, $angle, $x, $y, $curcolor, $font, $captcha[$i]);
}

session_start();
$_SESSION['captcha'] = $captcha;

imagepng($im);
imagedestroy($im);