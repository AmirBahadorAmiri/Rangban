-- ============================================================
-- رنگ‌بان — ساختار جداول (utf8mb4 / persian_ci)
-- این فایل مرجع است؛ برنامه در اولین اتصال همین را خودکار می‌سازد.
-- ============================================================

CREATE TABLE IF NOT EXISTS `palettes` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120)     NOT NULL,
    `description`  TEXT             DEFAULT NULL,
    `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_used_at` DATETIME         DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS `colors` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
