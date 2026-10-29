<?php
/**
 * داده‌ی نمونه‌ی اولیه — فقط یک بار اجرا شود:
 *   php db/seed.php
 * پالت و رنگ‌های نمونه را با متن فارسی سالم می‌سازد.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/repository.php';

const SAMPLE = [
    'name' => 'پالت-brand-اصلی',
    'description' => 'رنگ‌های برند من برای وب و رابط کاربری',
    'colors' => [
        ['#0F172A', 'سرمه‌ای شب'],
        ['#7C6CFF', 'بنفش برند'],
        ['#33D6A6', 'سبز فیروزه‌ای'],
        ['#FFD166', 'زرد آفتاب'],
        ['#FF5E78', 'صورتی خربزه‌ای'],
    ],
];

$existing = palette_all();
foreach ($existing as $p) {
    if ($p['name'] === SAMPLE['name']) {
        echo "داده‌ی نمونه قبلاً ساخته شده است (پالت #{$p['id']}).\n";
        exit(0);
    }
}

$id = palette_create(SAMPLE['name'], SAMPLE['description']);
foreach (SAMPLE['colors'] as [$hex, $name]) {
    color_create($id, $hex, $name);
}

echo "پالت نمونه ساخته شد: #{$id} با " . count(SAMPLE['colors']) . " رنگ.\n";
