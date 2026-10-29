# رنگ‌بان — ColorManager

[English](README.md) | [فارسی](#رنگ‌بان)

مدیریت پالت رنگ تحت وب با قابلیت خروجی به CSS/SCSS/JSON/Tailwind.

## نصب

```bash
mysql -u root -e "CREATE DATABASE colormanager"
mysql -u root colormanager < db/schema.sql
# فایل config.php را با اطلاعات دیتابیس ویرایش کنید
php -S localhost:8000
```

## امکانات

- ساخت/ویرایش/حذف پالت رنگ
- افزودن رنگ با انتخابگر HEX
- جابه‌جایی با drag-and-drop
- ستاره‌دار کردن رنگ‌ها
- روشن‌تر/تیره‌تر کردن (±10%)
- تولید پالت تصادفی
- خروجی: CSS، SCSS، JSON، Tailwind، TXT
- تم تاریک/روشن با تشخیص خودکار
- رابط فارسی

## پیش‌نیازها

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+

## فرمت‌های خروجی

| فرمت | مثال |
|------|------|
| CSS | `:root { --primary: #FF5733; }` |
| SCSS | `$primary: #FF5733;` |
| JSON | `{"colors": [{"hex": "#FF5733", "name": "primary"}]}` |
| Tailwind | `colors: { primary: '#FF5733' }` |
| TXT | `#FF5733` (هر خط یک رنگ) |

## مرجع API

همه endpointها از طریق `src/api.php` (POST/GET با توکن CSRF).

| عملیات | پارامترهای اجباری | اختیاری |
|--------|-------------------|---------|
| `palette_create` | `name` | `description` |
| `palette_update` | `id`, `name` | `description` |
| `palette_delete` | `id` | — |
| `color_add` | `palette_id`, `hex` | `name` |
| `color_update` | `id`, `hex` | `name` |
| `color_delete` | `id` | — |
| `color_favorite` | `id` | — |
| `color_reorder` | `palette_id`, `ids` (آرایه JSON) | — |
| `color_shade` | `id`, `percent` (±1-50) | — |
| `palette_random` | `id` | `replace` (پیش‌فرض: 1) |

## ساختار پروژه

```
├── index.php           # رابط اصلی برنامه
├── config.php          # تنظیمات دیتابیس
├── export.php          # endpoint خروجی
├── src/
│   ├── api.php         # هندلر درخواست‌های AJAX
│   ├── repository.php  # عملیات CRUD دیتابیس
│   ├── database.php    # اتصال PDO
│   ├── helpers.php     # CSRF، فلش‌مسیج، پاکسازی
│   └── color_tools.php # تبدیل HEX/RGB/HSL، کنتراست WCAG
├── assets/
│   ├── style.css       # استایل‌های برنامه
│   └── app.js          # جاوااسکریپت فرانت‌اند
└── db/
    └── schema.sql      # اسکیمای دیتابیس
```

## ابزارهای رنگ

توابع داخلی در `src/color_tools.php`:

- `hex_to_rgb()` / `rgb_to_hex()` — تبدیل HEX ↔ RGB
- `rgb_to_hsl()` / `hsl_to_rgb()` — تبدیل RGB ↔ HSL
- `shade_hex()` — روشن‌تر/تیره‌تر با درصد
- `contrast_ratio()` — نسبت کنتراست WCAG بین دو رنگ
- `readable_text_color()` — انتخاب خودکار متن سفید/مشکی روی پس‌زمینه
- `generate_scheme()` — تولید پالت‌های هماهنگ (مکمل، مشابه، سه‌گانه، چهارگانه، اسپلیت، تک‌رنگ)

## لایسنس

MIT
