<?php
/**
 * اتصال PDO به دیتابیس (سینگلتن)
 * در اولین اتصال، دیتابیس و جداول در صورت نبود خودکار ساخته می‌شوند (نصب بدون دست).
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // ساختن دیتابیس در صورت نبود
        $server = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT),
            DB_USER,
            DB_PASS,
            $opts
        );
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci'
        );
        $server = null;

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
            DB_USER,
            DB_PASS,
            $opts
        );

        db_ensure_schema($pdo);
    }

    return $pdo;
}

/** ساخت جداول در صورت نبود — همراستا با db/schema.sql */
function db_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `palettes` (
            `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            `name`         VARCHAR(120)     NOT NULL,
            `description`  TEXT             DEFAULT NULL,
            `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `last_used_at` DATETIME         DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `colors` (
            `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
            `palette_id`  INT UNSIGNED      NOT NULL,
            `hex_code`    CHAR(7)           NOT NULL,
            `name`        VARCHAR(120)      DEFAULT NULL,
            `position`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `is_favorite` TINYINT(1)        NOT NULL DEFAULT 0,
            `created_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_palette_pos` (`palette_id`, `position`),
            CONSTRAINT `fk_colors_palette`
                FOREIGN KEY (`palette_id`) REFERENCES `palettes` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci'
    );
}
