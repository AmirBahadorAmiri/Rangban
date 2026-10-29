<?php
/**
 * خروجی پالت در فرمت‌های مختلف
 * ?palette=ID&format=css|json|scss|tailwind|txt
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/repository.php';

$paletteId = (int) ($_GET['palette'] ?? 0);
$format    = strtolower((string) ($_GET['format'] ?? 'css'));

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

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

switch ($format) {

    case 'json':
        $out = ['name' => $palette['name'], 'description' => $palette['description'], 'colors' => []];
        foreach ($colors as $i => $c) {
            $out['colors'][] = [
                'hex'  => $c['hex_code'],
                'name' => $c['name'] ?? $varNames[$i],
            ];
        }
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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
