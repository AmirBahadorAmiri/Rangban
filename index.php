<?php
/**
 * Rangban — صفحه اصلی
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/repository.php';

$flashes = flash_pull();
$palettes = palette_all();

$activeId = isset($_GET['palette']) ? (int) $_GET['palette'] : 0;
if ($activeId && !palette_find($activeId)) {
    $activeId = 0;
}
if (!$activeId && $palettes) {
    $activeId = (int) $palettes[0]['id'];
}

$colors = $activeId ? colors_of_palette($activeId) : [];
if ($activeId) {
    palette_touch($activeId);
}

$csrf = csrf_token();
$activePalette = $activeId ? palette_find($activeId) : null;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>رنگ‌بان — مدیریت پالت رنگ</title>
<script>
(function () {
    var theme;
    try { theme = localStorage.getItem('cm-theme'); } catch (e) {}
    if (theme !== 'light' && theme !== 'dark') {
        theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }
    document.documentElement.dataset.theme = theme;
    var meta = document.createElement('meta');
    meta.name = 'theme-color';
    meta.content = theme === 'light' ? '#f4f5fb' : '#0d0f17';
    document.head.appendChild(meta);
})();
</script>
<link rel="stylesheet" href="assets/style.css?v=4">
<link rel="icon" href="assets/logo.svg">
</head>
<body>

<!-- ===== هدر ===== -->
<header class="app-header">
    <div class="brand">
        <img class="brand-mark" src="assets/logo.svg" alt="رنگ‌بان">
        <h1>رنگ‌بان</h1>
    </div>
    <nav class="header-actions">
        <button class="btn btn-ghost theme-toggle" id="btn-theme-toggle" type="button" title="تغییر تم" aria-label="تغییر تم">
            <svg class="icon-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2 12h2.5M19.5 12H22M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>
            <svg class="icon-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
        </button>
        <button class="btn btn-ghost" id="btn-search-toggle" title="جستجو (Ctrl+K)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <span>جستجو</span>
            <kbd>Ctrl K</kbd>
        </button>
        <button class="btn btn-primary" id="btn-new-palette">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            <span>پالت جدید</span>
        </button>
    </nav>
</header>

<div class="app-body">

    <!-- ===== سایدبار پالت‌ها ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-head">
            <h2>پالت‌های من</h2>
            <span class="count-badge"><?= count($palettes) ?></span>
        </div>
        <div class="palette-list" id="palette-list">
            <?php if (!$palettes): ?>
                <div class="empty-hint">هنوز پالتی نساخته‌ای.<br>از دکمه «پالت جدید» شروع کن.</div>
            <?php endif; ?>
            <?php foreach ($palettes as $p): ?>
                <a class="palette-item <?= (int) $p['id'] === $activeId ? 'active' : '' ?>"
                   href="?palette=<?= (int) $p['id'] ?>"
                   data-id="<?= (int) $p['id'] ?>"
                   data-name="<?= e($p['name']) ?>"
                   data-desc="<?= e($p['description'] ?? '') ?>">
                    <span class="pi-name"><?= e($p['name']) ?></span>
                    <span class="pi-count"><?= (int) $p['color_count'] ?> رنگ</span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- ===== ناحیه اصلی ===== -->
    <main class="workspace">
        <?php if ($activePalette): ?>
        <div class="ws-head">
            <div class="ws-title">
                <h2 data-ctx="palette" data-ctx-id="<?= (int) $activePalette['id'] ?>" data-ctx-name="<?= e($activePalette['name']) ?>" data-ctx-desc="<?= e($activePalette['description'] ?? '') ?>"><?= e($activePalette['name']) ?></h2>
                <?php if (!empty($activePalette['description'])): ?>
                    <p class="ws-desc"><?= e($activePalette['description']) ?></p>
                <?php endif; ?>
            </div>
            <div class="ws-tools">
                <button class="tool-btn" id="btn-add-color" title="افزودن رنگ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                </button>
                <button class="tool-btn" id="btn-random" title="پالت تصادفی">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.3" fill="currentColor"/><circle cx="15.5" cy="15.5" r="1.3" fill="currentColor"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/></svg>
                </button>
                <div class="export-wrap">
                    <button class="tool-btn" id="btn-export" title="خروجی گرفتن">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    </button>
                    <div class="dropdown" id="export-menu" hidden>
                        <?php foreach (['css' => 'CSS Variables', 'scss' => 'SCSS', 'json' => 'JSON', 'tailwind' => 'Tailwind', 'txt' => 'TXT'] as $f => $label): ?>
                            <a href="export.php?palette=<?= (int) $activePalette['id'] ?>&format=<?= $f ?>" download><?= $label ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="swatch-grid" id="swatch-grid" data-palette="<?= (int) $activePalette['id'] ?>">
            <?php foreach ($colors as $c):
                $hex = $c['hex_code'];
                $rgb = hex_to_rgb($hex);
                $hsl = rgb_to_hsl($rgb['r'], $rgb['g'], $rgb['b']);
                $text = readable_text_color($hex);
            ?>
            <div class="swatch"
                 draggable="true"
                 data-id="<?= (int) $c['id'] ?>"
                 data-hex="<?= e($hex) ?>"
                 data-name="<?= e($c['name'] ?? '') ?>"
                 style="--sw-bg: <?= e($hex) ?>; --sw-text: <?= $text ?>">
                <div class="sw-top">
                    <?php if ($c['is_favorite']): ?><span class="sw-fav">★</span><?php endif; ?>
                    <span class="sw-drag">⠿</span>
                </div>
                <div class="sw-body">
                    <div class="sw-hex"><?= e($hex) ?></div>
                    <?php if (!empty($c['name'])): ?><div class="sw-name"><?= e($c['name']) ?></div><?php endif; ?>
                    <div class="sw-meta">
                        <span>RGB <?= $rgb['r'] ?> <?= $rgb['g'] ?> <?= $rgb['b'] ?></span>
                        <span>HSL <?= $hsl['h'] ?>° <?= $hsl['s'] ?>% <?= $hsl['l'] ?>%</span>
                        <span class="sw-family"><?= color_family_name($hsl) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!$colors): ?>
                <div class="grid-empty">
                    <p>این پالت هنوز رنگی ندارد</p>
                    <button class="btn btn-primary" id="btn-first-color">اولین رنگ را اضافه کن</button>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="welcome">
            <div class="welcome-orb orb-1"></div>
            <div class="welcome-orb orb-2"></div>
            <div class="welcome-orb orb-3"></div>
            <h2>به رنگ‌بان خوش آمدی 🎨</h2>
            <p>برای شروع، یک پالت رنگ بساز و کد رنگ‌هایت را سازمان بده.</p>
            <button class="btn btn-primary btn-lg" id="btn-welcome-create">ساخت اولین پالت</button>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- ===== Context Menu اختصاصی ===== -->
<template id="tpl-ctx-color">
    <div class="ctx-head"><span class="ctx-dot" style="--dot: var(--ctx-color)"></span><span class="ctx-hex-label"></span></div>
    <button class="ctx-item" data-cmd="copy-hex">کپی HEX</button>
    <button class="ctx-item" data-cmd="copy-rgb">کپی RGB</button>
    <button class="ctx-item" data-cmd="copy-css-var">کپی به‌صورت متغیر CSS</button>
    <div class="ctx-sep"></div>
    <button class="ctx-item" data-cmd="edit">ویرایش رنگ…</button>
    <button class="ctx-item" data-cmd="favorite">ستاره‌دار کردن</button>
    <button class="ctx-item" data-cmd="lighten">روشن‌تر +۱۰٪</button>
    <button class="ctx-item" data-cmd="darken">تیره‌تر −۱۰٪</button>
    <div class="ctx-sep"></div>
    <button class="ctx-item danger" data-cmd="delete">حذف رنگ</button>
</template>
<template id="tpl-ctx-palette">
    <div class="ctx-head"><span class="ctx-hex-label"></span></div>
    <button class="ctx-item" data-cmd="rename">تغییر نام پالت…</button>
    <button class="ctx-item" data-cmd="add-color">افزودن رنگ…</button>
    <button class="ctx-item" data-cmd="randomize">پالت تصادفی 🎲</button>
    <button class="ctx-item" data-cmd="copy-all">کپی همهٔ رنگ‌ها</button>
    <div class="ctx-sep"></div>
    <button class="ctx-item danger" data-cmd="delete">حذف پالت</button>
</template>
<div class="ctx-menu" id="ctx-menu" hidden></div>

<!-- ===== مودال فرم ===== -->
<div class="modal-backdrop" id="modal-backdrop" hidden>
    <div class="modal" role="dialog" aria-modal="true">
        <h3 id="modal-title"></h3>
        <form id="modal-form">
            <input type="hidden" name="action">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="field" id="field-name">
                <label for="m-name">نام</label>
                <input type="text" id="m-name" name="name" autocomplete="off" maxlength="120">
            </div>
            <div class="field" id="field-desc">
                <label for="m-desc">توضیح (اختیاری)</label>
                <textarea id="m-desc" name="description" rows="2" maxlength="500"></textarea>
            </div>
            <div class="field" id="field-hex">
                <label for="m-hex">کد رنگ</label>
                <div class="hex-row">
                    <input type="color" id="m-picker" class="mini-picker">
                    <input type="text" id="m-hex" name="hex" placeholder="#FF5733" maxlength="7" autocomplete="off" spellcheck="false">
                </div>
            </div>
            <div class="modal-error" id="modal-error" hidden></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="modal-cancel">انصراف</button>
                <button type="submit" class="btn btn-primary">ذخیره</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== نوار جستجو ===== -->
<div class="search-overlay" id="search-overlay" hidden>
    <div class="search-box">
        <input type="text" id="search-input" placeholder="جستجوی پالت یا کد رنگ…" autocomplete="off">
        <div class="search-results" id="search-results"></div>
    </div>
</div>

<div class="toast-stack" id="toast-stack"></div>

<script>
window.CM = {
    csrf: <?= json_encode($csrf) ?>,
    api: 'src/api.php',
    paletteId: <?= (int) $activeId ?: 'null' ?>
};
</script>
<script src="assets/app.js?v=4"></script>
</body>
</html>
