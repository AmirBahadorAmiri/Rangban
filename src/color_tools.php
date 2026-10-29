<?php
/**
 * ابزارهای تبدیل و تحلیل رنگ (HEX / RGB / HSL / WCAG)
 */

declare(strict_types=1);

function hex_to_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [
        'r' => (int) hexdec(substr($hex, 0, 2)),
        'g' => (int) hexdec(substr($hex, 2, 2)),
        'b' => (int) hexdec(substr($hex, 4, 2)),
    ];
}

function rgb_to_hex(int $r, int $g, int $b): string
{
    $clamped = static fn(int $v): int => max(0, min(255, $v));
    return sprintf('#%02X%02X%02X', $clamped($r), $clamped($g), $clamped($b));
}

function hsl_to_hex(int $h, int $s, int $l): string
{
    ['r' => $r, 'g' => $g, 'b' => $b] = hsl_to_rgb($h, $s, $l);
    return rgb_to_hex($r, $g, $b);
}

/** روشن‌تر/تیره‌تر کردن بر پایه HSL — درصد روی Lightness */
function shade_hex(string $hex, int $percent): string
{
    ['r' => $r, 'g' => $g, 'b' => $b] = hex_to_rgb($hex);
    $hsl = rgb_to_hsl($r, $g, $b);
    $l = max(0, min(100, $hsl['l'] + $percent));
    return hsl_to_hex($hsl['h'], $hsl['s'], $l);
}

function rgb_to_hsl(int $r, int $g, int $b): array
{
    $rf = $r / 255;
    $gf = $g / 255;
    $bf = $b / 255;

    $max = max($rf, $gf, $bf);
    $min = min($rf, $gf, $bf);
    $delta = $max - $min;

    $h = 0.0;
    if ($delta > 0) {
        if ($max === $rf) {
            $h = fmod(($gf - $bf) / $delta, 6);
        } elseif ($max === $gf) {
            $h = fmod(($bf - $rf) / $delta + 2, 6);
        } else {
            $h = fmod(($rf - $gf) / $delta + 4, 6);
        }
        $h *= 60;
        if ($h < 0) {
            $h += 360;
        }
    }

    $l = ($max + $min) / 2;
    $s = 0.0;
    if ($delta > 0) {
        $s = $delta / (1 - abs(2 * $l - 1));
    }

    return [
        'h' => (int) round($h),
        's' => (int) round($s * 100),
        'l' => (int) round($l * 100),
    ];
}

function hsl_to_rgb(int $h, int $s, int $l): array
{
    $sf = $s / 100;
    $lf = $l / 100;
    $c = (1 - abs(2 * $lf - 1)) * $sf;
    $x = $c * (1 - abs((fmod($h / 60, 2)) - 1));
    $m = $lf - $c / 2;

    [$r, $g, $b] = match (true) {
        $h < 60  => [$c, $x, 0],
        $h < 120 => [$x, $c, 0],
        $h < 180 => [0, $c, $x],
        $h < 240 => [0, $x, $c],
        $h < 300 => [$x, 0, $c],
        default  => [$c, 0, $x],
    };

    return [
        'r' => (int) round(($r + $m) * 255),
        'g' => (int) round(($g + $m) * 255),
        'b' => (int) round(($b + $m) * 255),
    ];
}

/** نسبت کنتراست WCAG بین دو رنگ HEX */
function contrast_ratio(string $hexA, string $hexB): float
{
    $lum = static function (string $hex): float {
        ['r' => $r, 'g' => $g, 'b' => $b] = hex_to_rgb($hex);
        $chan = static function (int $v): float {
            $vf = $v / 255;
            return $vf <= 0.03928 ? $vf / 12.92 : (($vf + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $chan($r) + 0.7152 * $chan($g) + 0.0722 * $chan($b);
    };

    $la = $lum($hexA);
    $lb = $lum($hexB);
    $lighter = max($la, $lb);
    $darker = min($la, $lb);

    return round(($lighter + 0.05) / ($darker + 0.05), 2);
}

/** رنگ متن مناسب روی پس‌زمینه (سفید یا مشکی) */
function readable_text_color(string $hex): string
{
    return contrast_ratio($hex, '#FFFFFF') >= contrast_ratio($hex, '#000000')
        ? '#FFFFFF'
        : '#111111';
}

/** نام تقریبی فارسی رنگ از روی Hue */
function color_family_name(array $hsl): string
{
    $h = $hsl['h'];
    $s = $hsl['s'];
    $l = $hsl['l'];

    if ($s <= 8) {
        if ($l <= 12) {
            return 'مشکی';
        }
        if ($l >= 92) {
            return 'سفید';
        }
        return 'خاکستری';
    }

    return match (true) {
        $h < 15 || $h >= 345 => 'قرمز',
        $h < 45              => 'نارنجی',
        $h < 70              => 'زرد',
        $h < 95              => 'لیمویی',
        $h < 150             => 'سبز',
        $h < 190             => 'فیروزه‌ای',
        $h < 250             => 'آبی',
        $h < 290             => 'بنفش',
        default              => 'صورتی',
    };
}

/** تولید پالت‌های هماهنگ از یک رنگ پایه */
function generate_scheme(string $hex, string $type): array
{
    ['r' => $r, 'g' => $g, 'b' => $b] = hex_to_rgb($hex);
    $hsl = rgb_to_hsl($r, $g, $b);
    $base = $hsl['h'];

    $offsets = match ($type) {
        'complementary' => [0, 180],
        'analogous'     => [-30, 0, 30],
        'triadic'       => [0, 120, 240],
        'tetradic'      => [0, 90, 180, 270],
        'split'         => [0, 150, 210],
        default         => [0],
    };

    $result = [];
    foreach ($offsets as $off) {
        $h = (($base + $off) % 360 + 360) % 360;
        ['r' => $nr, 'g' => $ng, 'b' => $nb] = hsl_to_rgb($h, $hsl['s'], $hsl['l']);
        $result[] = rgb_to_hex($nr, $ng, $nb);
    }

    // مونوکروم: تغییر روشنایی
    if ($type === 'monochrome') {
        $result = [];
        foreach ([15, 30, 45, 60, 75, 90] as $l) {
            ['r' => $nr, 'g' => $ng, 'b' => $nb] = hsl_to_rgb($hsl['h'], $hsl['s'], $l);
            $result[] = rgb_to_hex($nr, $ng, $nb);
        }
    }

    return $result;
}
