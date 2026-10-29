# رنگ‌بان — ColorManager
## مدیریت و سازمان‌دهی پالت‌های رنگی (PHP + MySQL)

رنگ‌بان یک ابزار تحت وب برای ساخت پالت‌های رنگی، ذخیره‌سازی آن‌ها در دیتابیس، ویرایش، کپی‌کردن و خروجی گرفتن در فرمت‌های مختلف است. با دیزاین اختصاصی RTL فارسی و **context menu (منوی راست‌کلیک) اختصاصی**.

---

## 📋 ویژگی‌ها

| دسته | ویژگی |
|---|---|
| **مدیریت** | ساخت پالت جدید، ویرایش نام و توضیحات، حذف پالت |
| **رنگ‌ها** | افزودن/ویرایش/حذف رنگ، اولویت/ترتیب‌دهی با Drag & Drop، ستاره‌دار کردن |
| **ابزارها** | کپی HEX/RGB/متغیر CSS، انتخابگر رنگ، روشن‌تر/تیره‌تر |
| **خروجی** | CSS Variables / SCSS / JSON / Tailwind / TXT |
| **UI** | تم روشن/تیره با ذخیرهٔ انتخاب کاربر، راست‌کلیک اختصاصی (Color Palette + Color)، جستجوی سریع (`Ctrl+K`)، Toast، ریسپانسیو |

---

## 🚀 نصب و اجرا

### پیش‌نیازها
- PHP 8.2+ (نصب است)
- MySQL / MariaDB 10.4+ (نصب است)
- XAMPP (Apache یا `php-cgi`)

### گام‌های راه‌اندازی

1. **دیتابیس** — نیازی به کار دستی نیست: برنامه در اولین اجرا دیتابیس و جداول را خودکار می‌سازد.
   برای داده‌ی نمونه:
   ```bash
   C:\xampp\php\php.exe db\seed.php
   ```
   فایل مرجع ساختار جداول: `db/schema.sql`

2. **دسترسی از وب**:
   - روی سیستم خود: `http://localhost/ColorManager/`
   - یا با `php-cgi`:
     ```bash
     C:\xampp\php\php-cgi.exe -S 127.0.0.1:8765 -t C:\xampp\htdocs\www
     # سپس: http://127.0.0.1:8765/ColorManager/
     ```

3. **پایان** — فایل `test.php` را پس از تست حذف کنید.

---

## 🗂 ساختار فایل‌ها

```
ColorManager/
├── config.php              # تنظیمات دیتابیس + بارگذاری
├── index.php               # صفحه اصلی (UI کامل)
├── export.php              # خروجی پالت (CSS/SCSS/JSON/Tailwind/TXT)
├── test.php                # تِست عملکردی — بعداً حذف شود
├── db/
│   ├── schema.sql          # مرجع ساختار جداول
│   └── seed.php            # داده‌ی نمونه‌ی اولیه
├── src/
│   ├── database.php        # اتصال PDO سینگلتن + نصب خودکار جداول
│   ├── helpers.php         # e(), json_out(), normalize_hex(), csrf
│   ├── color_tools.php     # HEX↔RGB↔HSL، کنتراست WCAG، scheme، shade
│   ├── repository.php      # عملیات CRUD پالت/رنگ + پالت تصادفی
│   └── api.php             # endpoint AJAX
└── assets/
    ├── style.css           # استایل اختصاصی با تم روشن/تیره + RTL
    └── app.js              # مودال، context menu، Drag&Drop، جستجو
```

---

## 🗄 نقشه جداول

**palettes**
| ستون | توضیح |
|---|---|
| `id` | کلید اصلی |
| `name` | نام پالت |
| `description` | توضیح اختیاری |
| `created_at` / `updated_at` | تاریخ‌ها |
| `last_used_at` | مرتب‌سازی پیش‌فرض |

**colors**
| ستون | توضیح |
|---|---|
| `id` | کلید اصلی |
| `palette_id` | کلید خارجی → palettes (CASCADE) |
| `hex_code` | کد ۶ رقمی HEX (`#RRGGBB`) |
| `name` | نام اختیاری رنگ |
| `position` | ترتیب نمایش |
| `is_favorite` | ستاره‌دار |

---

## ⚙️ API (src/api.php)

همه‌ی عملیات تغییردهنده از CSRF توکن محافظت می‌شوند. پاسخ JSON:

| action | ورودی | خروجی |
|---|---|---|
| `palette_create` | name, description | `{ok, id}` |
| `palette_update` | id, name, description | `{ok}` |
| `palette_delete` | id | `{ok}` |
| `palette_random` | id, replace (0/1) | `{ok, colors, hexes[]}` |
| `color_add` | palette_id, hex, name | `{ok, id, hex}` |
| `color_update` | id, hex, name | `{ok, hex}` |
| `color_delete` | id | `{ok}` |
| `color_favorite` | id | `{ok}` |
| `color_reorder` | palette_id, ids[] | `{ok}` |
| `color_shade` | id, percent (۱ تا ±۵۰) | `{ok, hex}` |

---

## 🎨 استفاده از خروجی‌ها

دانلود فایل **Tailwind**:
```js
module.exports = {
  theme: { extend: { colors: {
    'my-palette': {
      'primary': '#7C6CFF',
      'accent': '#33D6A6',
    },
  } } }
}
```

دانلود **CSS Variables**:
```css
:root { --primary: #7C6CFF; --accent: #33D6A6; }
```

---

## 🛠 نکات توسعه

- برای افزودن رنگ جدید: `color_create($paletteId, '#RRGGBB', $name)`
- برای تغییر ترتیب: `color_reorder($paletteId, [id, id, ...])`
- روشن/تیره کردن: `shade_hex('#7C6CFF', +10)` → `#A99EFF`
- پالت تصادفی: `palette_randomize($paletteId, replace: true)`
- محاسبه کنتراست: `contrast_ratio('#0f172a', '#ffffff')` → برمی‌گرداند عدد ۱ تا ۲۱
- رنگ متنی مناسب: `readable_text_color('#7C6CFF')` → `#FFFFFF`
- تولید scheme: `generate_scheme('#7c6cff', 'complementary')` → `['#7C6CFF', '#FF5E78']`

---

## 📝 مجوز

ساخته‌شده برای امیر — استفاده شخصی و پروژه‌های شخصی آزاد است.
