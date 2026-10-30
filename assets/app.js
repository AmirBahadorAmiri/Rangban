/* ============================================================
   رنگ‌بان — اسکریپت اصلی
   Context menu اختصاصی، مودال، Drag&Drop، جستجو، Toast
   ============================================================ */
'use strict';

const CM = window.CM;
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

/* ---------- ابزار رنگ (هم‌ارز src/color_tools.php) ---------- */
function hexToRgb(hex) {
    const n = hex.replace('#', '');
    return {
        r: parseInt(n.substr(0, 2), 16),
        g: parseInt(n.substr(2, 2), 16),
        b: parseInt(n.substr(4, 2), 16),
    };
}

function rgbToHsl(r, g, b) {
    const rf = r / 255, gf = g / 255, bf = b / 255;
    const max = Math.max(rf, gf, bf), min = Math.min(rf, gf, bf);
    const d = max - min;
    let h = 0;
    if (d > 0) {
        if (max === rf) h = ((gf - bf) / d) % 6;
        else if (max === gf) h = ((bf - rf) / d) + 2;
        else h = ((rf - gf) / d) + 4;
        h *= 60;
        if (h < 0) h += 360;
    }
    const l = (max + min) / 2;
    const s = d > 0 ? d / (1 - Math.abs(2 * l - 1)) : 0;
    return { h: Math.round(h), s: Math.round(s * 100), l: Math.round(l * 100) };
}

function readableText(hex) {
    const { r, g, b } = hexToRgb(hex);
    return (r * 299 + g * 587 + b * 114) / 1000 >= 140 ? '#111111' : '#FFFFFF';
}

function familyName(hsl) {
    if (hsl.s <= 8) {
        if (hsl.l <= 12) return 'مشکی';
        if (hsl.l >= 92) return 'سفید';
        return 'خاکستری';
    }
    const h = hsl.h;
    if (h < 15 || h >= 345) return 'قرمز';
    if (h < 45)  return 'نارنجی';
    if (h < 70)  return 'زرد';
    if (h < 95)  return 'لیمویی';
    if (h < 150) return 'سبز';
    if (h < 190) return 'فیروزه‌ای';
    if (h < 250) return 'آبی';
    if (h < 290) return 'بنفش';
    return 'صورتی';
}

/* ---------- API و پیام‌ها ---------- */
async function api(action, extra = {}) {
    const body = new URLSearchParams({ action, csrf: CM.csrf, ...extra });
    const res = await fetch(CM.api, { method: 'POST', body });
    return res.json();
}

function toast(message, type = 'ok') {
    const stack = $('#toast-stack');
    const el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' error' : '');
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 320); }, 2600);
}

async function copyText(text) {
    // مسیر اصلی: Clipboard API — اگر مجوز در حالت prompt باشد، همین فراخوانی
    // (داخل رویداد کلیک) دیالوگ مجوز مرورگر را باز می‌کند.
    if (navigator.clipboard?.writeText) {
        try {
            if (navigator.permissions?.query) {
                try { await navigator.permissions.query({ name: 'clipboard-write' }); } catch { /* سافاری این نام را ندارد */ }
            }
            await navigator.clipboard.writeText(text);
            toast('کپی شد: ' + text);
            return;
        } catch { /* مجوز رد شد یا زمینه امن نیست — مسیر پشتیبان */ }
    }
    // مسیر پشتیبان: execCommand که در http و وب‌ویوهای محدود هم کار می‌کند
    try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        const ok = document.execCommand('copy');
        ta.remove();
        if (!ok) throw new Error('copy rejected');
        toast('کپی شد: ' + text);
    } catch {
        toast('مرورگر اجازه کپی نداد', 'error');
    }
}

/* ---------- تم روشن / تیره ---------- */
const themeToggle = $('#btn-theme-toggle');
const THEME_KEY = 'cm-theme';

function currentTheme() {
    return document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
}

function storedTheme() {
    try { return localStorage.getItem(THEME_KEY); } catch { return null; }
}

function syncThemeUI(theme) {
    if (themeToggle) {
        const label = 'تغییر به تم ' + (theme === 'dark' ? 'روشن' : 'تیره');
        themeToggle.title = label;
        themeToggle.setAttribute('aria-label', label);
    }
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = theme === 'light' ? '#f4f5fb' : '#0d0f17';
}

function setTheme(theme) {
    document.documentElement.dataset.theme = theme;
    try { localStorage.setItem(THEME_KEY, theme); } catch { /* حالت ناشناس */ }
    syncThemeUI(theme);
}

syncThemeUI(currentTheme());

themeToggle?.addEventListener('click', () => {
    setTheme(currentTheme() === 'light' ? 'dark' : 'light');
});

// تا وقتی کاربر انتخاب نکرده، تغییر تم سیستم را دنبال کن
window.matchMedia?.('(prefers-color-scheme: light)').addEventListener?.('change', ev => {
    if (storedTheme()) return;
    document.documentElement.dataset.theme = ev.matches ? 'light' : 'dark';
    syncThemeUI(currentTheme());
});

/* ---------- مودال ---------- */
const backdrop = $('#modal-backdrop');
const mForm = $('#modal-form');
const mTitle = $('#modal-title');
const mError = $('#modal-error');
const mHex = $('#m-hex');
const mPicker = $('#m-picker');

const FIELD_MATRIX = {
    palette_create: { title: 'پالت جدید',   name: true, desc: true,  hex: false, submit: 'ساخت پالت', action: 'palette_create' },
    palette_rename: { title: 'ویرایش پالت', name: true, desc: true,  hex: false, submit: 'ذخیره',     action: 'palette_update' },
    color_add:      { title: 'افزودن رنگ',  name: true, desc: false, hex: true,  submit: 'افزودن',     action: 'color_add' },
    color_edit:     { title: 'ویرایش رنگ',  name: true, desc: false, hex: true,  submit: 'ذخیره',     action: 'color_update' },
};

let modalMode = null;

function openModal(mode, defaults = {}) {
    modalMode = mode;
    const cfg = FIELD_MATRIX[mode];
    mTitle.textContent = cfg.title;
    mForm.action.value = mode;
    mForm.dataset.recordId = defaults.id ?? '';
    mForm.dataset.paletteId = defaults.paletteId ?? CM.paletteId ?? '';
    mForm.querySelector('button[type=submit]').textContent = cfg.submit;

    $('#field-name').hidden = !cfg.name;
    $('#field-desc').hidden = !cfg.desc;
    $('#field-hex').hidden = !cfg.hex;

    mForm.name.value = defaults.name ?? '';
    mForm.description.value = defaults.description ?? '';
    mHex.value = defaults.hex ?? '';
    mPicker.value = normalizeHex(defaults.hex) || '#7C6CFF';
    mError.hidden = true;
    backdrop.hidden = false;
    (cfg.hex ? mHex : mForm.name).focus();
}

function closeModal() {
    backdrop.hidden = true;
    modalMode = null;
}

function normalizeHex(v) {
    let m = ('' + (v ?? '')).trim().replace(/^#/, '');
    if (/^[0-9a-f]{3}$/i.test(m)) {
        m = m[0] + m[0] + m[1] + m[1] + m[2] + m[2];
    }
    return /^[0-9a-f]{6}$/i.test(m) ? '#' + m.toUpperCase() : null;
}

mPicker.addEventListener('input', () => { mHex.value = mPicker.value.toUpperCase(); });
mHex.addEventListener('input', () => {
    const n = normalizeHex(mHex.value);
    if (n) mPicker.value = n;
});
mHex.addEventListener('change', () => {
    const n = normalizeHex(mHex.value);
    if (n) mHex.value = n;
});

$('#modal-cancel').addEventListener('click', closeModal);
backdrop.addEventListener('mousedown', ev => { if (ev.target === backdrop) closeModal(); });

mForm.addEventListener('submit', async ev => {
    ev.preventDefault();
    if (!modalMode) return;

    const cfg = FIELD_MATRIX[modalMode];
    const data = {
        name: mForm.name.value.trim(),
        hex: mHex.value.trim(),
        description: mForm.description.value.trim(),
    };

    if (cfg.name && data.name === '') {
        mError.textContent = 'نام نمی‌تواند خالی باشد';
        mError.hidden = false;
        return;
    }
    if (cfg.hex && !normalizeHex(data.hex)) {
        mError.textContent = 'کد رنگ نامعتبر است (مثال: #FF5733)';
        mError.hidden = false;
        return;
    }

    // شناسه‌ی رکورد: ویرایش‌ها با id خودشان؛ افزودن رنگ با id پالت مقصد
    const idKey = (modalMode === 'palette_rename' || modalMode === 'color_edit') ? 'id' : 'palette_id';
    const targetId = mForm.dataset.recordId || mForm.dataset.paletteId || '';
    const payload = { [idKey]: targetId, ...data };
    if (modalMode === 'palette_create') {
        delete payload.palette_id;
    }

    const res = await api(cfg.action, payload);
    if (res.ok) {
        closeModal();
        toast('انجام شد ✓');
        if (modalMode === 'palette_create' && res.id) {
            location.href = '?palette=' + res.id;
        } else {
            location.reload();
        }
    } else {
        mError.textContent = res.error || 'خطای نامشخص';
        mError.hidden = false;
    }
});

/* ---------- دکمه‌های هدر و نوار ابزار ---------- */
$('#btn-new-palette').addEventListener('click', () => openModal('palette_create'));
$('#btn-add-color')?.addEventListener('click', () => openModal('color_add'));
$('#btn-first-color')?.addEventListener('click', () => openModal('color_add'));
$('#btn-welcome-create')?.addEventListener('click', () => openModal('palette_create'));
$('#btn-random')?.addEventListener('click', () =>
    randomizePalette(CM.paletteId, $('[data-ctx="palette"]')?.dataset.ctxName ?? 'پالت'));

$('#btn-export')?.addEventListener('click', () => {
    const menu = $('#export-menu');
    menu.hidden = !menu.hidden;
});
$('#export-menu')?.addEventListener('click', ev => {
    if (ev.target.closest('a')) $('#export-menu').hidden = true;
});
document.addEventListener('mousedown', ev => {
    const menu = $('#export-menu');
    if (menu && !menu.hidden && !ev.target.closest('.export-wrap')) menu.hidden = true;
});

/* ---------- درون‌ریزی JSON ---------- */
const importInput = $('#import-file');

$('#btn-import')?.addEventListener('click', () => importInput?.click());

/** استخراج فهرست پالت‌ها از هر سه قالب پشتیبانی‌شده */
function importPaletteList(data) {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.palettes)) return data.palettes;
    if (data && Array.isArray(data.colors)) return [data];
    return null;
}

importInput?.addEventListener('change', async () => {
    const file = importInput.files?.[0];
    importInput.value = '';
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        toast('حجم فایل بیشتر از ۲ مگابایت است.', 'error');
        return;
    }

    let text;
    try {
        text = await file.text();
    } catch {
        toast('خواندن فایل ناموفق بود.', 'error');
        return;
    }

    let data;
    try {
        data = JSON.parse(text);
    } catch {
        toast('فایل JSON معتبر نیست.', 'error');
        return;
    }

    const list = importPaletteList(data);
    if (!list) {
        toast('ساختار فایل شناخته نشد (کلید palettes یا colors لازم است).', 'error');
        return;
    }
    if (!list.length) {
        toast('فایل هیچ پالتی ندارد.', 'error');
        return;
    }

    const colorTotal = list.reduce(
        (sum, p) => sum + (p && Array.isArray(p.colors) ? p.colors.length : 0),
        0
    );
    if (!confirm(`وارد کردن ${list.length} پالت با ${colorTotal} رنگ؟`)) return;

    const res = await api('palette_import', { data: text });
    if (!res.ok) {
        toast(res.error || 'ورود پالت‌ها ناموفق بود.', 'error');
        return;
    }

    let message = `وارد شد: ${res.palettes} پالت و ${res.colors} رنگ`;
    if (res.skipped) message += ` (${res.skipped} رنگ نامعتبر رد شد)`;
    toast(message);

    const firstId = Array.isArray(res.ids) && res.ids.length ? res.ids[0] : null;
    setTimeout(() => {
        location.href = firstId ? '?palette=' + firstId : location.href;
    }, 700);
});

/* ---------- Context Menu اختصاصی ---------- */
const ctxMenu = $('#ctx-menu');
let ctxTarget = null;

function showCtx(template, x, y, data) {
    ctxTarget = data;
    ctxMenu.innerHTML = '';
    const tpl = $('#tpl-ctx-' + template);
    ctxMenu.appendChild(tpl.content.cloneNode(true));

    const label = $('.ctx-hex-label', ctxMenu);
    if (template === 'color') {
        if (label) label.textContent = data.hex;
        const dot = $('.ctx-dot', ctxMenu);
        if (dot) dot.style.setProperty('--dot', data.hex);
        const favItem = $('[data-cmd=favorite]', ctxMenu);
        if (favItem && data.isFavorite) favItem.textContent = 'حذف ستاره';
    } else {
        if (label) label.textContent = data.name;
        // «کپی همهٔ رنگ‌ها» فقط وقتی معنا دارد که رنگ‌های همین پالت در صفحه باشند
        const copyAll = $('[data-cmd=copy-all]', ctxMenu);
        if (copyAll && data.id !== CM.paletteId) copyAll.remove();
    }

    ctxMenu.hidden = false;
    // جاگیری داخل صفحه
    const r = ctxMenu.getBoundingClientRect();
    ctxMenu.style.left = Math.min(x, innerWidth - r.width - 8) + 'px';
    ctxMenu.style.top = Math.min(y, innerHeight - r.height - 8) + 'px';
}

function hideCtx() { ctxMenu.hidden = true; }

document.addEventListener('contextmenu', ev => {
    if (ev.target.closest('input, textarea, [contenteditable]')) {
        hideCtx();
        return; // رفتار پیش‌فرض مرورگر برای فیلدهای متنی
    }

    const swatch = ev.target.closest('.swatch');
    const paletteTitle = ev.target.closest('[data-ctx="palette"]');
    const paletteItem = ev.target.closest('.palette-item');

    if (swatch) {
        ev.preventDefault();
        showCtx('color', ev.clientX, ev.clientY, {
            type: 'color',
            id: +swatch.dataset.id,
            hex: swatch.dataset.hex,
            name: swatch.dataset.name,
            isFavorite: swatch.querySelector('.sw-fav') != null,
            el: swatch,
        });
    } else if (paletteTitle || paletteItem) {
        ev.preventDefault();
        const el = paletteTitle || paletteItem;
        showCtx('palette', ev.clientX, ev.clientY, {
            type: 'palette',
            id: +el.dataset.ctxId || +el.dataset.id,
            name: el.dataset.ctxName ?? el.dataset.name ?? 'پالت',
            description: el.dataset.ctxDesc ?? el.dataset.desc ?? '',
        });
    } else if (CM.paletteId) {
        // منوی پالت فعال برای بقیه‌ی صفحه
        ev.preventDefault();
        const title = $('[data-ctx="palette"]');
        showCtx('palette', ev.clientX, ev.clientY, {
            type: 'palette',
            id: CM.paletteId,
            name: title?.dataset.ctxName ?? 'پالت',
            description: title?.dataset.ctxDesc ?? '',
        });
    }
});

document.addEventListener('mousedown', ev => {
    if (!ev.target.closest('.ctx-menu')) hideCtx();
});

/** به‌روزرسانی کامل یک سواچ در صفحه بعد از تغییر رنگش */
function repaintSwatch(el, hex) {
    const rgb = hexToRgb(hex);
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    el.dataset.hex = hex;
    el.style.setProperty('--sw-bg', hex);
    el.style.setProperty('--sw-text', readableText(hex));
    el.querySelector('.sw-hex').textContent = hex;
    const meta = el.querySelector('.sw-meta');
    if (meta) {
        meta.children[0].textContent = `RGB ${rgb.r} ${rgb.g} ${rgb.b}`;
        meta.children[1].textContent = `HSL ${hsl.h}° ${hsl.s}% ${hsl.l}%`;
        meta.children[2].textContent = familyName(hsl);
    }
}

/** پالت تصادفی — با تأیید جایگزینی وقتی پالت رنگ دارد */
async function randomizePalette(pid, name) {
    if (!pid) return;
    const isCurrent = pid === CM.paletteId;
    const hasColors = isCurrent && $$('.swatch').length > 0;
    if (hasColors && !confirm(`همهٔ رنگ‌های «${name}» با پالت تصادفی جایگزین شود؟`)) return;

    const res = await api('palette_random', { id: pid, replace: hasColors ? 1 : 0 });
    if (!res.ok) {
        toast(res.error || 'خطای نامشخص', 'error');
        return;
    }
    toast('پالت تصادفی ساخته شد (' + res.colors + ' رنگ) 🎲');
    if (isCurrent) setTimeout(() => location.reload(), 500);
}

ctxMenu.addEventListener('click', async ev => {
    const btn = ev.target.closest('.ctx-item');
    if (!btn || !ctxTarget) return;
    const cmd = btn.dataset.cmd;
    const t = ctxTarget;
    hideCtx();

    switch (cmd) {
        case 'copy-hex':
            await copyText(t.hex);
            break;

        case 'copy-rgb': {
            const { r, g, b } = hexToRgb(t.hex);
            await copyText(`rgb(${r}, ${g}, ${b})`);
            break;
        }

        case 'copy-css-var':
            await copyText(`--${t.name || 'color'}: ${t.hex};`);
            break;

        case 'edit':
            openModal('color_edit', { id: t.id, hex: t.hex, name: t.name });
            break;

        case 'favorite': {
            const res = await api('color_favorite', { id: t.id });
            if (!res.ok) { toast(res.error, 'error'); break; }
            let star = t.el.querySelector('.sw-fav');
            if (star) {
                star.remove();
                toast('ستاره حذف شد');
            } else {
                star = document.createElement('span');
                star.className = 'sw-fav';
                star.textContent = '★';
                t.el.querySelector('.sw-top').prepend(star);
                toast('ستاره‌دار شد ★');
            }
            t.isFavorite = !t.isFavorite;
            break;
        }

        case 'lighten':
        case 'darken': {
            const percent = cmd === 'lighten' ? 10 : -10;
            const res = await api('color_shade', { id: t.id, percent });
            if (!res.ok) { toast(res.error, 'error'); break; }
            repaintSwatch(t.el, res.hex);
            toast(`${t.hex} ← ${res.hex}`);
            break;
        }

        case 'delete':
            if (t.type === 'palette') {
                if (confirm(`پالت «${t.name}» و همهٔ رنگ‌هایش برای همیشه حذف شود؟`)) {
                    const res = await api('palette_delete', { id: t.id });
                    if (res.ok) {
                        toast('پالت حذف شد');
                        location.href = t.id === CM.paletteId ? location.pathname : location.href;
                    } else {
                        toast(res.error, 'error');
                    }
                }
            } else if (confirm('این رنگ حذف شود؟')) {
                const res = await api('color_delete', { id: t.id });
                if (res.ok) {
                    t.el.remove();
                    toast('رنگ حذف شد');
                    if (!$$('.swatch').length) location.reload();
                } else {
                    toast(res.error, 'error');
                }
            }
            break;

        case 'rename':
            openModal('palette_rename', { id: t.id, name: t.name, description: t.description });
            break;

        case 'add-color':
            openModal('color_add', { paletteId: t.id });
            break;

        case 'randomize':
            await randomizePalette(t.id, t.name);
            break;

        case 'copy-all': {
            const hexes = $$('.swatch').map(s => s.dataset.hex);
            if (hexes.length) await copyText(hexes.join('\n'));
            else toast('این پالت رنگی ندارد', 'error');
            break;
        }
    }
});

/* ---------- Drag & Drop ترتیب رنگ‌ها ---------- */
const grid = $('#swatch-grid');
let dragEl = null;
let dragStartOrder = null;

grid?.addEventListener('dragstart', ev => {
    const sw = ev.target.closest('.swatch');
    if (!sw) return;
    dragEl = sw;
    dragStartOrder = $$('.swatch', grid).map(el => el.dataset.id);
    sw.classList.add('dragging');
    ev.dataTransfer.effectAllowed = 'move';
    ev.dataTransfer.setData('text/plain', sw.dataset.id);
});

grid?.addEventListener('dragover', ev => {
    ev.preventDefault();
    ev.dataTransfer.dropEffect = 'move';
    if (!dragEl) return;
    const over = ev.target.closest('.swatch');
    if (!over || over === dragEl) return;

    $$('.drag-over', grid).forEach(x => x.classList.remove('drag-over'));
    over.classList.add('drag-over');

    // چیدمان RTL: نیمه‌ی بصری راستِ المان مقصد = قبل در ترتیب DOM
    const r = over.getBoundingClientRect();
    const before = ev.clientX > r.left + r.width / 2;
    grid.insertBefore(dragEl, before ? over : over.nextSibling);
});

grid?.addEventListener('drop', ev => ev.preventDefault());

grid?.addEventListener('dragend', async () => {
    $$('.drag-over', grid).forEach(x => x.classList.remove('drag-over'));
    if (!dragEl) return;
    dragEl.classList.remove('dragging');

    const order = $$('.swatch', grid).map(el => el.dataset.id);
    const changed = dragStartOrder && JSON.stringify(order) !== JSON.stringify(dragStartOrder);
    dragEl = null;
    dragStartOrder = null;

    if (changed) {
        const res = await api('color_reorder', { palette_id: CM.paletteId, ids: JSON.stringify(order) });
        res.ok ? toast('ترتیب ذخیره شد') : toast(res.error, 'error');
    }
});

/* ---------- جستجو (Ctrl+K) ---------- */
const searchOverlay = $('#search-overlay');
const searchInput = $('#search-input');
const searchResults = $('#search-results');

function openSearch() {
    searchOverlay.hidden = false;
    setTimeout(() => searchInput.focus(), 40);
}
function closeSearch() {
    searchOverlay.hidden = true;
    searchInput.value = '';
    searchResults.innerHTML = '';
}

$('#btn-search-toggle').addEventListener('click', openSearch);
searchOverlay.addEventListener('mousedown', ev => { if (ev.target === searchOverlay) closeSearch(); });

document.addEventListener('keydown', ev => {
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') {
        ev.preventDefault();
        openSearch();
    }
    if (ev.key === 'Escape') {
        hideCtx();
        if (!searchOverlay.hidden) closeSearch();
        if (!backdrop.hidden) closeModal();
    }
});

searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim().toLowerCase();
    if (q.length < 1) { searchResults.innerHTML = ''; return; }

    // جستجو در DOM صفحه (پالت‌ها + رنگ‌های پالت فعال)
    const rows = [];
    $$('.palette-item').forEach(item => {
        if ((item.dataset.name || '').toLowerCase().includes(q)) {
            rows.push({
                label: item.dataset.name,
                type: 'پالت',
                href: item.getAttribute('href'),
                swatch: 'linear-gradient(135deg, #7c6cff, #33d6a6)',
            });
        }
    });
    $$('.swatch').forEach(sw => {
        if (sw.dataset.hex.toLowerCase().includes(q) ||
            (sw.dataset.name || '').toLowerCase().includes(q)) {
            rows.push({
                label: (sw.dataset.name ? sw.dataset.name + ' — ' : '') + sw.dataset.hex,
                type: 'رنگ',
                href: null,
                swatch: sw.dataset.hex,
                hex: sw.dataset.hex,
            });
        }
    });

    searchResults.innerHTML = '';
    if (!rows.length) {
        searchResults.innerHTML = '<div class="sr-empty">نتیجه‌ای پیدا نشد</div>';
        return;
    }
    rows.slice(0, 12).forEach(r => {
        const el = document.createElement(r.href ? 'a' : 'button');
        el.className = 'sr-item';
        if (r.href) el.href = r.href;
        if (r.hex) {
            el.addEventListener('click', () => { closeSearch(); copyText(r.hex); });
        }
        el.innerHTML = `<span class="sr-dot" style="background:${r.swatch}"></span>
            <span class="sr-label">${r.label.replace(/</g, '&lt;')}</span>
            <span class="sr-type">${r.type}</span>`;
        searchResults.appendChild(el);
    });
});
