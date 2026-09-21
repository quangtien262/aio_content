<?php

declare(strict_types=1);

if (! extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension is required.\n");
    exit(1);
}

$slug = trim($argv[1] ?? '');
$category = trim($argv[2] ?? '');

if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
    fwrite(STDERR, "Usage: php tools/generate-automation-cover.php <slug> <category-slug>\n");
    exit(1);
}

$allowedCategories = [
    'kien-thuc-cong-nghe',
    'huong-dan',
    'giai-phap',
    'lap-trinh',
    'he-dieu-hanh',
];

if (! in_array($category, $allowedCategories, true)) {
    fwrite(STDERR, "Unsupported category slug.\n");
    exit(1);
}

$width = 1440;
$height = 810;
$image = imagecreatetruecolor($width, $height);
imageantialias($image, true);

$palette = [
    'kien-thuc-cong-nghe' => [[8, 47, 73], [14, 116, 144], [34, 211, 238]],
    'huong-dan' => [[15, 23, 42], [29, 78, 216], [96, 165, 250]],
    'giai-phap' => [[20, 42, 35], [5, 122, 85], [52, 211, 153]],
    'lap-trinh' => [[30, 27, 75], [91, 33, 182], [167, 139, 250]],
    'he-dieu-hanh' => [[17, 24, 39], [15, 118, 110], [45, 212, 191]],
];
[$dark, $middle, $accent] = $palette[$category];

for ($y = 0; $y < $height; $y++) {
    $ratio = $y / max(1, $height - 1);
    $red = (int) round($dark[0] + (($middle[0] - $dark[0]) * $ratio * 0.72));
    $green = (int) round($dark[1] + (($middle[1] - $dark[1]) * $ratio * 0.72));
    $blue = (int) round($dark[2] + (($middle[2] - $dark[2]) * $ratio * 0.72));
    $color = imagecolorallocate($image, $red, $green, $blue);
    imageline($image, 0, $y, $width, $y, $color);
}

$grid = imagecolorallocatealpha($image, $accent[0], $accent[1], $accent[2], 102);
for ($x = 0; $x <= $width; $x += 90) {
    imageline($image, $x, 0, $x, $height, $grid);
}
for ($y = 0; $y <= $height; $y += 90) {
    imageline($image, 0, $y, $width, $y, $grid);
}

$seed = hexdec(substr(hash('sha256', $slug), 0, 8));
mt_srand($seed);
$line = imagecolorallocatealpha($image, $accent[0], $accent[1], $accent[2], 24);
$soft = imagecolorallocatealpha($image, 255, 255, 255, 82);
$panel = imagecolorallocatealpha($image, 5, 15, 30, 32);

for ($i = 0; $i < 18; $i++) {
    $x1 = mt_rand(80, 1360);
    $y1 = mt_rand(70, 740);
    $x2 = min(1360, $x1 + mt_rand(80, 300));
    $y2 = mt_rand(70, 740);
    imagesetthickness($image, mt_rand(2, 5));
    imageline($image, $x1, $y1, $x2, $y1, $line);
    imageline($image, $x2, $y1, $x2, $y2, $line);
    imagefilledellipse($image, $x1, $y1, 12, 12, $soft);
    imagefilledellipse($image, $x2, $y2, 12, 12, $soft);
}

imagefilledroundedrectangle($image, 390, 170, 1050, 640, 42, $panel);
imagesetthickness($image, 5);
imagerectangle($image, 430, 210, 1010, 600, $line);

for ($i = 0; $i < 7; $i++) {
    $barWidth = 260 + mt_rand(20, 280);
    $barY = 260 + ($i * 42);
    imagefilledroundedrectangle($image, 500, $barY, 500 + $barWidth, $barY + 15, 7, $soft);
}

$ring = imagecolorallocatealpha($image, $accent[0], $accent[1], $accent[2], 8);
imagesetthickness($image, 14);
imageellipse($image, 720, 405, 610, 610, $ring);
imageellipse($image, 720, 405, 700, 700, $line);

$output = dirname(__DIR__).'/assets/'.$slug.'.jpg';
imageinterlace($image, true);
if (! imagejpeg($image, $output, 82)) {
    fwrite(STDERR, "Cannot write cover.\n");
    exit(1);
}
imagedestroy($image);

$size = filesize($output);
if ($size === false || $size >= 250 * 1024) {
    fwrite(STDERR, "Generated cover exceeds 250 KB.\n");
    exit(1);
}

echo $output.' | 1440x810 | '.$size." bytes\n";

function imagefilledroundedrectangle(
    GdImage $image,
    int $x1,
    int $y1,
    int $x2,
    int $y2,
    int $radius,
    int $color,
): void {
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}
