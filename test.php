<?php
require_once 'config.php';
require_once 'src/repository.php';

$palettes = palette_all();
foreach ($palettes as $p) {
    echo $p['id'] . ' | ' . $p['name'] . ' | ' . $p['color_count'] . ' colors' . PHP_EOL;
}

echo "--- color tools ---" . PHP_EOL;
$hex = '#0f172a';
$rgb = hex_to_rgb($hex);
$hsl = rgb_to_hsl($rgb['r'], $rgb['g'], $rgb['b']);
echo $hex . ' -> RGB(' . $rgb['r'] . ',' . $rgb['g'] . ',' . $rgb['b'] . ') -> HSL(' . $hsl['h'] . ',' . $hsl['s'] . ',' . $hsl['l'] . ')' . PHP_EOL;
echo 'contrast: ' . contrast_ratio('#0f172a', '#7c6cff') . PHP_EOL;
echo 'scheme complementary of #7c6cff: ' . implode(', ', generate_scheme('#7c6cff', 'complementary')) . PHP_EOL;

echo "--- normalize hex ---" . PHP_EOL;
echo normalize_hex('7c6cff') . PHP_EOL;
echo normalize_hex('#abc') . PHP_EOL;
echo normalize_hex('xyz') . PHP_EOL;
