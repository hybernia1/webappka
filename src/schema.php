<?php

use RedBeanPHP\R as R;

/**
 * Ensure that the basic tables exist and contain required columns.
 */
function ensureDatabaseSchema(): void
{
    createTables();
    ensureUserLevelColumn();
}

function createTables(): void
{
    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `user` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(190) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'viewer',
            `level` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `setting` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(190) NOT NULL UNIQUE,
            `value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `post` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `content` LONGTEXT NOT NULL,
            `thumbnail_url` VARCHAR(255) NULL,
            `excerpt` TEXT NULL,
            `tags` TEXT NULL,
            `slug` VARCHAR(190) NULL,
            `published_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `slug_unique` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `upload` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `path` VARCHAR(255) NOT NULL,
            `type` VARCHAR(20) NOT NULL,
            `original_name` VARCHAR(255) NULL,
            `mime_type` VARCHAR(190) NULL,
            `size` INT UNSIGNED NULL,
            `created_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);
}

function ensureUserLevelColumn(): void
{
    $columnExists = R::getAll("SHOW COLUMNS FROM `user` LIKE 'level'");

    if (!$columnExists) {
        R::exec("ALTER TABLE `user` ADD COLUMN `level` INT NOT NULL DEFAULT 0 AFTER `role`");
    }
}
