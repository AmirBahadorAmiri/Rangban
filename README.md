# Rangban — رنگ‌بان

[فارسی](README.fa.md) | [English](#rangban)

Web-based color palette manager with export to CSS/SCSS/JSON/Tailwind.

## Install

```bash
mysql -u root -e "CREATE DATABASE rangban"
mysql -u root rangban < db/schema.sql
# Edit config.php with DB credentials
php -S localhost:8000
```

## Features

- Create/edit/delete color palettes
- Add colors with HEX picker
- Drag-and-drop reordering
- Favorite colors
- Lighten/darken colors (±10%)
- Random palette generator
- Export: CSS, SCSS, JSON, Tailwind, TXT
- Dark/light theme with auto-detect
- Persian (Farsi) UI

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+

## Export Formats

| Format | Example |
|--------|---------|
| CSS | `:root { --primary: #FF5733; }` |
| SCSS | `$primary: #FF5733;` |
| JSON | `{"colors": [{"hex": "#FF5733", "name": "primary"}]}` |
| Tailwind | `colors: { primary: '#FF5733' }` |
| TXT | `#FF5733` (one per line) |

## API Reference

All endpoints via `src/api.php` (POST/GET with CSRF token).

| Action | Required Params | Optional |
|--------|----------------|----------|
| `palette_create` | `name` | `description` |
| `palette_update` | `id`, `name` | `description` |
| `palette_delete` | `id` | — |
| `color_add` | `palette_id`, `hex` | `name` |
| `color_update` | `id`, `hex` | `name` |
| `color_delete` | `id` | — |
| `color_favorite` | `id` | — |
| `color_reorder` | `palette_id`, `ids` (JSON array) | — |
| `color_shade` | `id`, `percent` (±1-50) | — |
| `palette_random` | `id` | `replace` (default: 1) |

## Project Structure

```
├── index.php           # Main application UI
├── config.php          # Database configuration
├── export.php          # Export endpoint
├── src/
│   ├── api.php         # AJAX request handler
│   ├── repository.php  # Database CRUD operations
│   ├── database.php    # PDO connection
│   ├── helpers.php     # CSRF, flash messages, sanitization
│   └── color_tools.php # HEX/RGB/HSL conversion, WCAG contrast
├── assets/
│   ├── style.css       # Application styles
│   └── app.js          # Frontend JavaScript
└── db/
    └── schema.sql      # Database schema
```

## Color Tools

Built-in functions in `src/color_tools.php`:

- `hex_to_rgb()` / `rgb_to_hex()` — HEX ↔ RGB conversion
- `rgb_to_hsl()` / `hsl_to_rgb()` — RGB ↔ HSL conversion
- `shade_hex()` — Lighten/darken by percentage
- `contrast_ratio()` — WCAG contrast ratio between colors
- `readable_text_color()` — Auto-select white/black text for background
- `generate_scheme()` — Generate harmonious palettes (complementary, analogous, triadic, tetradic, split, monochrome)

## License

MIT
