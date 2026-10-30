<?php
/**
 * endpoint تمام عملیات AJAX
 * ورودی: action + فیلدها (JSON یا فرم) — خروجی JSON
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/transfer.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// تأیید CSRF برای همه‌ی عملیات تغییردهنده
if (!csrf_verify($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    json_out(['ok' => false, 'error' => 'توکن امنیتی نامعتبر است.'], 403);
}

try {
    switch ($action) {

        case 'palette_create': {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                json_out(['ok' => false, 'error' => 'نام پالت نمی‌تواند خالی باشد.'], 422);
            }
            $id = palette_create($name, $_POST['description'] ?? null);
            json_out(['ok' => true, 'id' => $id]);
        }

        case 'palette_update': {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if (!$id || $name === '') {
                json_out(['ok' => false, 'error' => 'نام پالت الزامی است.'], 422);
            }
            if (!palette_find($id)) {
                json_out(['ok' => false, 'error' => 'پالت یافت نشد.'], 404);
            }
            palette_update($id, $name, $_POST['description'] ?? null);
            json_out(['ok' => true]);
        }

        case 'palette_delete': {
            $id = (int) ($_POST['id'] ?? 0);
            if (!palette_find($id)) {
                json_out(['ok' => false, 'error' => 'پالت یافت نشد.'], 404);
            }
            palette_delete($id);
            json_out(['ok' => true]);
        }

        case 'color_add': {
            $pid = (int) ($_POST['palette_id'] ?? 0);
            $hex = normalize_hex((string) ($_POST['hex'] ?? ''));
            if (!palette_find($pid)) {
                json_out(['ok' => false, 'error' => 'پالت یافت نشد.'], 404);
            }
            if ($hex === null) {
                json_out(['ok' => false, 'error' => 'کد رنگ نامعتبر است. مثال: #FF5733'], 422);
            }
            $id = color_create($pid, $hex, $_POST['name'] ?? null);
            json_out(['ok' => true, 'id' => $id, 'hex' => $hex]);
        }

        case 'color_update': {
            $id = (int) ($_POST['id'] ?? 0);
            $hex = normalize_hex((string) ($_POST['hex'] ?? ''));
            if (!color_find($id)) {
                json_out(['ok' => false, 'error' => 'رنگ یافت نشد.'], 404);
            }
            if ($hex === null) {
                json_out(['ok' => false, 'error' => 'کد رنگ نامعتبر است.'], 422);
            }
            color_update($id, $hex, $_POST['name'] ?? null);
            json_out(['ok' => true, 'hex' => $hex]);
        }

        case 'color_delete': {
            $id = (int) ($_POST['id'] ?? 0);
            if (!color_find($id)) {
                json_out(['ok' => false, 'error' => 'رنگ یافت نشد.'], 404);
            }
            color_delete($id);
            json_out(['ok' => true]);
        }

        case 'color_favorite': {
            $id = (int) ($_POST['id'] ?? 0);
            if (!color_find($id)) {
                json_out(['ok' => false, 'error' => 'رنگ یافت نشد.'], 404);
            }
            color_toggle_favorite($id);
            json_out(['ok' => true]);
        }

        case 'color_reorder': {
            $pid = (int) ($_POST['palette_id'] ?? 0);
            $ids = json_decode((string) ($_POST['ids'] ?? '[]'), true);
            if (!is_array($ids) || !palette_find($pid)) {
                json_out(['ok' => false, 'error' => 'داده نامعتبر است.'], 422);
            }
            color_reorder($pid, array_map('intval', $ids));
            json_out(['ok' => true]);
        }

        case 'color_shade': {
            $id = (int) ($_POST['id'] ?? 0);
            $percent = (int) ($_POST['percent'] ?? 10);
            $color = color_find($id);
            if (!$color) {
                json_out(['ok' => false, 'error' => 'رنگ یافت نشد.'], 404);
            }
            if ($percent === 0 || abs($percent) > 50) {
                json_out(['ok' => false, 'error' => 'درجه تغییر باید بین ۱ تا ۵۰ باشد.'], 422);
            }
            $hex = shade_hex($color['hex_code'], $percent);
            color_set_hex($id, $hex);
            json_out(['ok' => true, 'hex' => $hex]);
        }

        case 'palette_import': {
            $raw = (string) ($_POST['data'] ?? '');
            try {
                $parsed = transfer_parse_import($raw);
                $result = transfer_import($parsed['palettes']);
            } catch (InvalidArgumentException $ex) {
                json_out(['ok' => false, 'error' => $ex->getMessage()], 422);
            }
            json_out([
                'ok'       => true,
                'ids'      => $result['ids'],
                'palettes' => $result['palettes'],
                'colors'   => $result['colors'],
                'skipped'  => $parsed['skipped'],
            ]);
        }

        default:
            json_out(['ok' => false, 'error' => 'عملیات ناشناخته.'], 400);
    }
} catch (Throwable $ex) {
    json_out(['ok' => false, 'error' => 'خطای سرور: ' . $ex->getMessage()], 500);
}
