<?php
/**
 * خروجی پالت در فرمت‌های مختلف
 * ?palette=ID&format=css|json|scss|tailwind|txt
 * ?scope=all&format=json  — خروجی JSON همه‌ی پالت‌ها (قابل درون‌ریزی)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/repository.php';
require_once __DIR__ . '/src/transfer.php';

$format  = strtolower((string) ($_GET['format'] ?? 'css'));
$allowed = ['css', 'json', 'scss', 'tailwind', 'txt'];
if (!in_array($format, $allowed, true)) {
    $format = 'css';
}

// خروجی گروهی (همه‌ی پالت‌ها) فقط در قالب JSON معنا دارد
$scope = strtolower((string) ($_GET['scope'] ?? ''));
if ($scope === 'all') {
    if ($format !== 'json') {
        http_response_code(400);
        exit('خروجی گروهی فقط در قالب JSON موجود است.');
    }

    $payload  = transfer_all_data();
    $filename = 'rangban-palettes-' . date('Ymd-His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$paletteId = (int) ($_GET['palette'] ?? 0);

$palette = palette_find($paletteId);
if (!$palette) {
    http_response_code(404);
    exit('پالت یافت نشد.');
}

$colors = colors_of_palette($paletteId);
$slug = trim((string) preg_replace('/[^\w-]+/u', '-', $palette['name']), '-');
if ($slug === '') {
    $slug = 'palette';
}
$filename = $slug . '.' . ($format === 'tailwind' ? 'js' : $format);

// نام متغیر انگلیسی برای هر رنگ
$varNames = [];
foreach ($colors as $i => $c) {
    $base = !empty($c['name']) ? $c['name'] : ('color-' . ($i + 1));
    $clean = preg_replace('/[^\w-]+/u', '-', $base);
    $clean = trim(preg_replace('/-+/', '-', $clean), '-');
    if ($clean === '') {
        $clean = 'color-' . ($i + 1);
    }
    // جلوگیری از تکرار
    $candidate = $clean;
    $n = 2;
    while (in_array($candidate, $varNames, true)) {
        $candidate = $clean . '-' . $n++;
    }
    $varNames[] = $candidate;
}

$contentTypes = [
    'css'      => 'text/css; charset=utf-8',
    'json'     => 'application/json; charset=utf-8',
    'scss'     => 'text/x-scss; charset=utf-8',
    'tailwind' => 'application/javascript; charset=utf-8',
    'txt'      => 'text/plain; charset=utf-8',
];

header('Content-Type: ' . $contentTypes[$format]);
header('Content-Disposition: attachment; filename="' . $filename . '"');

switch ($format) {

    case 'json':
        echo json_encode(
            transfer_palette_data($palette, $colors),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        break;

    case 'scss':
        echo "// " . $palette['name'] . "\n";
        foreach ($colors as $i => $c) {
            echo '$' . $varNames[$i] . ': ' . $c['hex_code'] . ";\n";
        }
        break;

    case 'tailwind':
        echo "/** " . $palette['name'] . " — tailwind.config.js */\n";
        echo "module.exports = {\n  theme: { extend: { colors: {\n";
        echo "    '" . $slug . "': {\n";
        foreach ($colors as $i => $c) {
            echo "      '" . $varNames[$i] . "': '" . $c['hex_code'] . "',\n";
        }
        echo "    },\n  } } } }\n";
        break;

    case 'txt':
        foreach ($colors as $c) {
            echo $c['hex_code'] . "\n";
        }
        break;

    case 'css':
    default:
        echo "/* " . $palette['name'] . " */\n:root {\n";
        foreach ($colors as $i => $c) {
            echo "  --" . $varNames[$i] . ": " . $c['hex_code'] . ";\n";
        }
        echo "}\n";
        break;
}
