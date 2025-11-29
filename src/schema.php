<?php

use RedBeanPHP\R as R;

/**
 * Ensure that the basic tables exist and contain required columns.
 */
function ensureDatabaseSchema(): void
{
    createTables();
    ensureUserLevelColumn();
    ensurePostTypeColumn();
    seedDefaultPostType();
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
            `post_type_id` INT UNSIGNED NULL,
            `published_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `slug_unique` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `posttype` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(190) NOT NULL,
            `slug` VARCHAR(190) NOT NULL UNIQUE,
            `description` TEXT NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `category` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(190) NOT NULL,
            `slug` VARCHAR(190) NOT NULL UNIQUE,
            `description` TEXT NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `tag` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(190) NOT NULL,
            `slug` VARCHAR(190) NOT NULL UNIQUE,
            `description` TEXT NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `post_category` (
            `post_id` INT UNSIGNED NOT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`post_id`, `category_id`),
            CONSTRAINT `fk_post_category_post` FOREIGN KEY (`post_id`) REFERENCES `post`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_post_category_category` FOREIGN KEY (`category_id`) REFERENCES `category`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    R::exec(<<<SQL
        CREATE TABLE IF NOT EXISTS `post_tag` (
            `post_id` INT UNSIGNED NOT NULL,
            `tag_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`post_id`, `tag_id`),
            CONSTRAINT `fk_post_tag_post` FOREIGN KEY (`post_id`) REFERENCES `post`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_post_tag_tag` FOREIGN KEY (`tag_id`) REFERENCES `tag`(`id`) ON DELETE CASCADE
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

function ensurePostTypeColumn(): void
{
    $columnExists = R::getAll("SHOW COLUMNS FROM `post` LIKE 'post_type_id'");

    if (!$columnExists) {
        R::exec("ALTER TABLE `post` ADD COLUMN `post_type_id` INT UNSIGNED NULL AFTER `slug`");
    }
}

function seedDefaultPostType(): void
{
    $existing = R::count('posttype');

    if ($existing === 0) {
        $postType = R::dispense('posttype');
        $postType->name = 'Příspěvek';
        $postType->slug = 'post';
        $postType->description = 'Výchozí typ obsahu.';
        $postType->created_at = date('Y-m-d H:i:s');
        R::store($postType);
    }
}
