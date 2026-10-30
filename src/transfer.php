<?php
/**
 * انتقال داده — خروجی و ورودی JSON برای یک پالت یا همه‌ی پالت‌ها
 *
 * قالب خروجی تکی (سازگار با نسخه‌های قبلی):
 *   { "name": "...", "description": "...", "colors": [{ "hex": "#RRGGBB", "name": "...", "favorite": false }] }
 *
 * قالب خروجی گروهی:
 *   { "app": "rangban", "version": 1, "type": "palettes", "exported_at": "...", "palettes": [ ... ] }
 */

declare(strict_types=1);

const TRANSFER_APP          = 'rangban';
const TRANSFER_VERSION      = 1;
const TRANSFER_MAX_BYTES    = 2 * 1024 * 1024; // ۲ مگابایت
const TRANSFER_MAX_PALETTES = 200;
const TRANSFER_MAX_COLORS   = 500;

/** یک پالت + رنگ‌هایش را به آرایه‌ی قابل‌سریال‌سازی تبدیل می‌کند */
function transfer_palette_data(array $palette, array $colors): array
{
    $data = [
        'name'        => (string) $palette['name'],
        'description' => isset($palette['description']) && trim((string) $palette['description']) !== ''
            ? (string) $palette['description']
            : null,
        'colors'      => [],
    ];

    foreach ($colors as $color) {
        $data['colors'][] = [
            'hex'      => (string) $color['hex_code'],
            'name'     => isset($color['name']) && trim((string) $color['name']) !== ''
                ? (string) $color['name']
                : null,
            'favorite' => !empty($color['is_favorite']),
        ];
    }

    return $data;
}

/** خروجی کامل: همه‌ی پالت‌ها با همه‌ی رنگ‌ها */
function transfer_all_data(): array
{
    $palettes = [];
    foreach (palette_all() as $palette) {
        $palettes[] = transfer_palette_data(
            $palette,
            colors_of_palette((int) $palette['id'])
        );
    }

    return [
        'app'         => TRANSFER_APP,
        'version'     => TRANSFER_VERSION,
        'type'        => 'palettes',
        'exported_at' => date('c'),
        'palettes'    => $palettes,
    ];
}

/**
 * متن JSON را اعتبارسنجی و به شکل داخلی تبدیل می‌کند.
 * خطاهای کاربری با InvalidArgumentException (پیام فارسی) برگردانده می‌شوند.
 *
 * @return array{palettes: array, color_count: int, skipped: int}
 */
function transfer_parse_import(string $raw): array
{
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    $raw = trim($raw);

    if ($raw === '') {
        throw new InvalidArgumentException('فایل JSON خالی است.');
    }
    if (strlen($raw) > TRANSFER_MAX_BYTES) {
        throw new InvalidArgumentException('حجم فایل بیشتر از حد مجاز (۲ مگابایت) است.');
    }

    try {
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $ex) {
        throw new InvalidArgumentException('فایل JSON معتبر نیست: ' . $ex->getMessage());
    }

    if (!is_array($data)) {
        throw new InvalidArgumentException('ساختار فایل JSON باید یک شیء یا آرایه باشد.');
    }

    // سه قالب پذیرفته می‌شود: گروهی، پالت تکی، و آرایه‌ی خام پالت‌ها
    if (array_key_exists('palettes', $data)) {
        if (!is_array($data['palettes'])) {
            throw new InvalidArgumentException('کلید palettes باید آرایه باشد.');
        }
        $rows = $data['palettes'];
    } elseif (array_key_exists('colors', $data) || array_key_exists('name', $data)) {
        $rows = [$data];
    } elseif (transfer_is_list($data)) {
        $rows = $data;
    } else {
        throw new InvalidArgumentException('ساختار فایل شناخته نشد؛ کلید palettes یا colors لازم است.');
    }

    if (!$rows) {
        throw new InvalidArgumentException('فایل هیچ پالتی ندارد.');
    }
    if (count($rows) > TRANSFER_MAX_PALETTES) {
        throw new InvalidArgumentException('تعداد پالت‌ها بیشتر از حد مجاز (' . TRANSFER_MAX_PALETTES . ') است.');
    }

    $palettes   = [];
    $colorCount = 0;
    $skipped    = 0;

    foreach (array_values($rows) as $index => $row) {
        $label = 'پالت شماره ' . ($index + 1);

        if (!is_array($row)) {
            throw new InvalidArgumentException($label . ' معتبر نیست.');
        }

        $name = isset($row['name']) && is_scalar($row['name']) ? trim((string) $row['name']) : '';
        if ($name === '') {
            throw new InvalidArgumentException($label . ' نام ندارد.');
        }

        $description = null;
        if (isset($row['description']) && is_scalar($row['description'])) {
            $desc = trim((string) $row['description']);
            if ($desc !== '') {
                $description = transfer_limit($desc, 5000);
            }
        }

        $rowColors = $row['colors'] ?? [];
        if (!is_array($rowColors)) {
            throw new InvalidArgumentException($label . ': کلید colors باید آرایه باشد.');
        }
        if (count($rowColors) > TRANSFER_MAX_COLORS) {
            throw new InvalidArgumentException($label . ': تعداد رنگ‌ها بیشتر از حد مجاز (' . TRANSFER_MAX_COLORS . ') است.');
        }

        $colors = [];
        foreach ($rowColors as $color) {
            $hex      = null;
            $colorName = null;
            $favorite = false;

            if (is_string($color)) {
                $hex = normalize_hex($color);
            } elseif (is_array($color)) {
                $hexRaw = $color['hex'] ?? $color['hex_code'] ?? $color['color'] ?? null;
                if (is_string($hexRaw)) {
                    $hex = normalize_hex($hexRaw);
                }
                if (isset($color['name']) && is_scalar($color['name'])) {
                    $cn = trim((string) $color['name']);
                    if ($cn !== '') {
                        $colorName = transfer_limit($cn, 120);
                    }
                }
                $favorite = transfer_truthy($color['favorite'] ?? $color['is_favorite'] ?? false);
            }

            if ($hex === null) {
                $skipped++;
                continue;
            }

            $colors[] = ['hex' => $hex, 'name' => $colorName, 'favorite' => $favorite];
            $colorCount++;
        }

        $palettes[] = [
            'name'        => transfer_limit($name, 120),
            'description' => $description,
            'colors'      => $colors,
        ];
    }

    return ['palettes' => $palettes, 'color_count' => $colorCount, 'skipped' => $skipped];
}

/**
 * پالت‌های اعتبارسنجی‌شده را در یک تراکنش می‌سازد.
 *
 * @return array{ids: int[], palettes: int, colors: int}
 */
function transfer_import(array $palettes): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $ids    = [];
        $colors = 0;

        foreach ($palettes as $palette) {
            $id = palette_create($palette['name'], $palette['description']);
            $ids[] = $id;

            foreach ($palette['colors'] as $color) {
                color_create($id, $color['hex'], $color['name'], $color['favorite']);
                $colors++;
            }
        }

        $pdo->commit();

        return ['ids' => $ids, 'palettes' => count($ids), 'colors' => $colors];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** برش امن رشته‌ی یونیکد (با پشتیبانی از mbstring و بدون آن) */
function transfer_limit(string $value, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return substr($value, 0, $max);
}

function transfer_truthy(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value != 0;
    }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    return false;
}

function transfer_is_list(array $value): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($value);
    }

    $i = 0;
    foreach ($value as $key => $_) {
        if ($key !== $i++) {
            return false;
        }
    }

    return true;
}
