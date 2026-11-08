<?php
/**
 * Generate High-Fidelity Beverage Images for Juice Store
 * N.A Fresh Fruits & Coconuts
 */

$juiceDir = __DIR__ . '/../public/assets/images/juice';
if (!is_dir($juiceDir)) {
    mkdir($juiceDir, 0755, true);
}

$items = [
    'mosambi-juice.jpg' => [
        'bg1' => [254, 240, 138], // soft lime yellow
        'bg2' => [234, 179, 8],   // golden citrus
        'drink' => [250, 204, 21], // mosambi yellow
        'fruit_name' => 'Mosambi Juice',
        'sub' => 'Fresh Sweet Lime • Cold Pressed',
        'accent' => [202, 138, 4]
    ],
    'pomegranate-juice.jpg' => [
        'bg1' => [254, 205, 211], // soft ruby rose
        'bg2' => [225, 29, 72],   // deep crimson ruby
        'drink' => [190, 18, 60], // rich anar red
        'fruit_name' => 'Royal Anar Juice',
        'sub' => '100% Pure Pomegranate • Antioxidants',
        'accent' => [159, 18, 57]
    ],
    'orange-juice.jpg' => [
        'bg1' => [254, 215, 170], // soft peach orange
        'bg2' => [234, 88, 12],   // nagpur tangerine
        'drink' => [249, 115, 22], // vibrant orange
        'fruit_name' => 'Valencia Orange',
        'sub' => 'Nagpur Farm Squeezed • Pulp Rich',
        'accent' => [194, 65, 12]
    ],
    'pineapple-juice.jpg' => [
        'bg1' => [254, 249, 195], // golden light
        'bg2' => [202, 138, 4],   // tropical gold
        'drink' => [234, 179, 8],  // golden pineapple
        'fruit_name' => 'Pineapple Cooler',
        'sub' => 'Queen Pineapple & Fresh Mountain Mint',
        'accent' => [161, 98, 7]
    ],
    'ganga-jamuna-juice.jpg' => [
        'bg1' => [254, 243, 199], // citrus sunrise
        'bg2' => [245, 158, 11],  // amber
        'drink' => [251, 146, 60], // orange-yellow swirl
        'fruit_name' => 'Ganga Jamuna Blend',
        'sub' => 'Sweet Lime & Orange Dual Squeeze',
        'accent' => [217, 119, 6]
    ],
    'mango-shake.jpg' => [
        'bg1' => [254, 240, 138], // rich mango cream
        'bg2' => [217, 119, 6],   // deep alphonso
        'drink' => [245, 158, 11], // thick mango shake
        'fruit_name' => 'Alphonso Mango Shake',
        'sub' => 'Thick Shake with Coconut Malai',
        'accent' => [180, 83, 9]
    ],
    'sugarcane-juice.jpg' => [
        'bg1' => [236, 252, 203], // light sugarcane green
        'bg2' => [132, 204, 22],  // fresh green cane
        'drink' => [163, 230, 53], // pale fresh cane juice
        'fruit_name' => 'Fresh Sugarcane Juice',
        'sub' => 'Crushed Fresh with Ginger & Lemon',
        'accent' => [101, 163, 13]
    ],
    'watermelon-juice.jpg' => [
        'bg1' => [254, 205, 211], // melon mist
        'bg2' => [244, 63, 94],   // watermelon ruby
        'drink' => [239, 68, 68],  // bright sweet red
        'fruit_name' => 'Watermelon Cooler',
        'sub' => 'Seedless Cold-Extracted Hydration',
        'accent' => [225, 29, 72]
    ],
    'abc-detox-juice.jpg' => [
        'bg1' => [254, 215, 215], // detox rose
        'bg2' => [159, 18, 57],   // beetroot ruby
        'drink' => [136, 19, 55],  // deep ABC crimson
        'fruit_name' => 'ABC Immunity Detox',
        'sub' => 'Apple • Beetroot • Carrot Cleanse',
        'accent' => [112, 26, 117]
    ],
    'strawberry-banana-shake.jpg' => [
        'bg1' => [252, 231, 243], // berry milk
        'bg2' => [236, 72, 153],  // strawberry pink
        'drink' => [244, 114, 182], // berry shake
        'fruit_name' => 'Berry Banana Smoothie',
        'sub' => 'Strawberries & Sweet Banana Blend',
        'accent' => [219, 39, 119]
    ]
];

$w = 600;
$h = 600;

foreach ($items as $filename => $config) {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);

    // Gradient background
    for ($y = 0; $y < $h; $y++) {
        $ratio = $y / (float)$h;
        $r = (int)($config['bg1'][0] + ($config['bg2'][0] - $config['bg1'][0]) * $ratio);
        $g = (int)($config['bg1'][1] + ($config['bg2'][1] - $config['bg1'][1]) * $ratio);
        $b = (int)($config['bg1'][2] + ($config['bg2'][2] - $config['bg1'][2]) * $ratio);
        $col = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $col);
    }

    // Glass backdrop / circular pedestal
    $pedestalCol = imagecolorallocatealpha($im, 255, 255, 255, 75);
    imagefilledellipse($im, 300, 480, 420, 110, $pedestalCol);

    // Glass body (trapezoid polygon)
    $glassDrinkCol = imagecolorallocate($im, $config['drink'][0], $config['drink'][1], $config['drink'][2]);
    $glassPoints = [
        210, 170, // top-left
        390, 170, // top-right
        360, 470, // bottom-right
        240, 470  // bottom-left
    ];
    imagefilledpolygon($im, $glassPoints, $glassDrinkCol);

    // Chilled highlights on glass (white translucent reflection strips)
    $hlCol = imagecolorallocatealpha($im, 255, 255, 255, 80);
    $hlPoints = [
        230, 185,
        255, 185,
        268, 460,
        250, 460
    ];
    imagefilledpolygon($im, $hlPoints, $hlCol);

    // Floating Ice cubes
    $iceCol = imagecolorallocatealpha($im, 255, 255, 255, 60);
    imagefilledrectangle($im, 260, 210, 310, 260, $iceCol);
    imagefilledrectangle($im, 290, 270, 345, 325, $iceCol);
    imagefilledrectangle($im, 250, 330, 295, 375, $iceCol);

    // Glass rim ellipse
    $rimCol = imagecolorallocatealpha($im, 255, 255, 255, 40);
    imagefilledellipse($im, 300, 170, 180, 30, $rimCol);

    // Bamboo / eco paper straw
    $strawCol = imagecolorallocate($im, 253, 224, 71);
    imagesetthickness($im, 8);
    imageline($im, 320, 420, 420, 90, $strawCol);
    imagesetthickness($im, 1);

    // Top Header Badge Banner
    $badgeBg = imagecolorallocatealpha($im, 0, 0, 0, 60);
    imagefilledrectangle($im, 0, 520, $w, 600, $badgeBg);

    $white = imagecolorallocate($im, 255, 255, 255);
    $accent = imagecolorallocate($im, 254, 240, 138);

    // Text labels
    imagestring($im, 5, 30, 535, $config['fruit_name'] . '  [100% Pure Fruit]', $white);
    imagestring($im, 3, 30, 565, $config['sub'], $accent);

    imagejpeg($im, $juiceDir . '/' . $filename, 92);
    imagedestroy($im);
}

// Generate hero-juice.png
$heroW = 800;
$heroH = 600;
$hero = imagecreatetruecolor($heroW, $heroH);
imagealphablending($hero, true);
for ($y = 0; $y < $heroH; $y++) {
    $ratio = $y / (float)$heroH;
    $r = (int)(254 + (234 - 254) * $ratio);
    $g = (int)(243 + (88 - 243) * $ratio);
    $b = (int)(199 + (12 - 199) * $ratio);
    $col = imagecolorallocate($hero, $r, $g, $b);
    imageline($hero, 0, $y, $heroW, $y, $col);
}

// Pedestal
$ped = imagecolorallocatealpha($hero, 255, 255, 255, 70);
imagefilledellipse($hero, 400, 490, 550, 140, $ped);

// Trio of juice glasses (Citrus, Anar, Mint Cooler)
$drink1 = imagecolorallocate($hero, 249, 115, 22); // Orange
$drink2 = imagecolorallocate($hero, 190, 18, 60);  // Pomegranate
$drink3 = imagecolorallocate($hero, 163, 230, 53); // Sugarcane Mint

imagefilledpolygon($hero, [160, 230, 300, 230, 280, 480, 180, 480], $drink1);
imagefilledpolygon($hero, [330, 180, 470, 180, 450, 480, 350, 480], $drink2);
imagefilledpolygon($hero, [500, 230, 640, 230, 620, 480, 520, 480], $drink3);

// Highlights
$wh70 = imagecolorallocatealpha($hero, 255, 255, 255, 70);
imagefilledpolygon($hero, [180, 245, 200, 245, 210, 470, 195, 470], $wh70);
imagefilledpolygon($hero, [350, 195, 370, 195, 380, 470, 365, 470], $wh70);
imagefilledpolygon($hero, [520, 245, 540, 245, 550, 470, 535, 470], $wh70);

// Straws
$straw = imagecolorallocate($hero, 254, 240, 138);
imagesetthickness($hero, 7);
imageline($hero, 250, 440, 310, 150, $straw);
imageline($hero, 410, 440, 480, 110, $straw);
imageline($hero, 580, 440, 640, 150, $straw);

imagepng($hero, $juiceDir . '/hero-juice.png');
imagedestroy($hero);

echo "All 10 Juice product images and hero banner generated successfully in {$juiceDir}\n";
