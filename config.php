<?php
/**
 * Rangban — تنظیمات کلی
 */

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'rangban';
const DB_USER = 'root';
const DB_PASS = '';

// تنظیمات رابط
const APP_NAME = 'رنگ‌بان';
const ITEMS_PER_PAGE = 24;

error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/color_tools.php';
