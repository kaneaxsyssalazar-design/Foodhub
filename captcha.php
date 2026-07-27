<?php
session_start();

// 1. Generate a random 5-character alphanumeric code
$captcha_code = substr(str_shuffle('23456789ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 5);

// 2. Store in PHP session for server-side verification
$_SESSION['captcha'] = $captcha_code;

// 3. Set content header to PNG image
header('Content-Type: image/png');

// 4. Create image canvas (120x40 px)
$image = imagecreate(120, 40);

// Colors (First color allocated becomes the background)
$bg_color     = imagecolorallocate($image, 240, 242, 245);
$text_color   = imagecolorallocate($image, 20, 30, 55);
$noise_color  = imagecolorallocate($image, 180, 190, 200);

// Add light background noise lines to prevent simple scraping
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand(0, 40), 120, rand(0, 40), $noise_color);
}

// Render text onto the canvas using GD built-in font (Font level 5)
imagestring($image, 5, 35, 12, $captcha_code, $text_color);

// Output image & destroy resource
imagepng($image);
imagedestroy($image);