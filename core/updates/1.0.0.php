<?php

declare(strict_types=1);

namespace Elementary;

/**
 * 1.0.0 release migration.
 *
 * Consolidates every 0.0.x schema and data change into a single idempotent upgrade, so any
 * install on a 0.0.x version is brought fully up to the 1.0.0 schema in one step. Fresh
 * installs load core/schema.sql directly and never run this file. Every statement is guarded
 * (CREATE TABLE IF NOT EXISTS, error-swallowing DDL, existence checks) so it is safe to run
 * regardless of how far along a 0.0.x database already is.
 */

/** Run an ALTER/DDL statement, ignoring "already exists"-style errors to stay idempotent. */
$ddl = static function (string $sql): void {
    try {
        Database::query($sql);
    } catch (\Throwable) {
        // Column/index already in the desired state — nothing to do.
    }
};

// SEO meta columns on pages, posts and categories (0.0.2).
foreach (['pages', 'posts', 'categories'] as $table) {
    $ddl("ALTER TABLE `{$table}` ADD COLUMN `meta_keywords` TEXT NULL");
    $ddl("ALTER TABLE `{$table}` ADD COLUMN `meta_description` TEXT NULL");
}

// Navigation menus (0.0.3). The type ENUM already includes 'home' (added separately in 0.0.5).
Database::query("
    CREATE TABLE IF NOT EXISTS `menus` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL UNIQUE,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

Database::query("
    CREATE TABLE IF NOT EXISTS `menu_items` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `menu_id` INT UNSIGNED NOT NULL,
        `parent_id` INT UNSIGNED NULL DEFAULT 0,
        `title` VARCHAR(255) NOT NULL,
        `url` VARCHAR(500) NOT NULL DEFAULT '',
        `type` ENUM('custom', 'home', 'page', 'post', 'category', 'url') NOT NULL DEFAULT 'custom',
        `type_id` INT UNSIGNED NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        FOREIGN KEY (`menu_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Page builder structure (0.0.4).
Database::query("CREATE TABLE IF NOT EXISTS `page_sections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `page_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Database::query("CREATE TABLE IF NOT EXISTS `page_rows` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `section_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    FOREIGN KEY (`section_id`) REFERENCES `page_sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Database::query("CREATE TABLE IF NOT EXISTS `page_columns` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `row_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `width` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    FOREIGN KEY (`row_id`) REFERENCES `page_rows`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Database::query("CREATE TABLE IF NOT EXISTS `page_blocks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `column_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `block_type` VARCHAR(50) NOT NULL DEFAULT 'text',
    `block_data` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    FOREIGN KEY (`column_id`) REFERENCES `page_columns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure menu_items.type includes 'home' for menus created before 0.0.5.
$ddl("ALTER TABLE `menu_items` MODIFY `type` ENUM('custom', 'home', 'page', 'post', 'category', 'url') NOT NULL DEFAULT 'custom'");

// Soft-delete (Recycle Bin) support on posts and pages (0.0.6).
$ddl("ALTER TABLE `posts` ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`");
$ddl("ALTER TABLE `pages` ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`");

// Default "Uncategorized" category, and assign any uncategorized posts to it (0.0.8).
$uncategorized = Database::fetch('SELECT id FROM categories WHERE slug = ?', ['uncategorized']);
if (!$uncategorized) {
    Database::insert('categories', [
        'name'        => 'Uncategorized',
        'slug'        => 'uncategorized',
        'description' => '',
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ]);
    $uncatId = Database::lastInsertId();
} else {
    $uncatId = (int) $uncategorized['id'];
}

$orphanPosts = Database::fetchAll(
    'SELECT p.id FROM posts p
     WHERE p.deleted_at IS NULL
     AND NOT EXISTS (SELECT 1 FROM post_categories pc WHERE pc.post_id = p.id)'
);
foreach ($orphanPosts as $post) {
    Database::insert('post_categories', [
        'post_id'     => (int) $post['id'],
        'category_id' => $uncatId,
    ]);
}

// Optional display name on users (0.0.9).
$ddl("ALTER TABLE `users` ADD COLUMN `display_name` VARCHAR(100) NULL AFTER `username`");

// Media library (0.0.10).
Database::query("CREATE TABLE IF NOT EXISTS `files` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `filename` VARCHAR(255) NOT NULL UNIQUE,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `size` INT UNSIGNED NOT NULL,
    `alt_text` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Persistent login sessions (0.0.11).
Database::query("CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `session_token` VARCHAR(64) NOT NULL UNIQUE,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(512) NOT NULL DEFAULT '',
    `last_activity` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_sessions` (`user_id`, `last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Featured images on posts and pages (0.0.12).
$ddl("ALTER TABLE `posts` ADD COLUMN `featured_image` VARCHAR(255) NULL AFTER `excerpt`");
$ddl("ALTER TABLE `pages` ADD COLUMN `featured_image` VARCHAR(255) NULL AFTER `content`");

// Per-element CSS on page builder tables (0.0.13).
foreach (['page_sections', 'page_rows', 'page_blocks'] as $table) {
    $ddl("ALTER TABLE `{$table}` ADD COLUMN `css_class` VARCHAR(255) NOT NULL DEFAULT '' AFTER `sort_order`");
    $ddl("ALTER TABLE `{$table}` ADD COLUMN `css_id` VARCHAR(255) NOT NULL DEFAULT '' AFTER `css_class`");
    $ddl("ALTER TABLE `{$table}` ADD COLUMN `inline_css` TEXT NULL AFTER `css_id`");
}
$ddl("ALTER TABLE `page_columns` ADD COLUMN `css_class` VARCHAR(255) NOT NULL DEFAULT '' AFTER `width`");
$ddl("ALTER TABLE `page_columns` ADD COLUMN `css_id` VARCHAR(255) NOT NULL DEFAULT '' AFTER `css_class`");
$ddl("ALTER TABLE `page_columns` ADD COLUMN `inline_css` TEXT NULL AFTER `css_id`");

// Featured image on categories (0.0.14).
$ddl("ALTER TABLE `categories` ADD COLUMN `featured_image` VARCHAR(255) NULL AFTER `description`");

// Page type + posts page / legacy home page migration (0.0.15 and 0.0.16).
$ddl("ALTER TABLE `pages` ADD COLUMN `page_type` VARCHAR(50) NOT NULL DEFAULT 'standard' AFTER `template`");
$admin = Database::fetch("SELECT id FROM users WHERE user_group = 'admin' ORDER BY id ASC LIMIT 1");
if ($admin) {
    ContentManager::ensurePostsPage((int) $admin['id']);
    ContentManager::migrateLegacyHomePage();
}

// Brute-force login attempt tracking (0.0.17).
Database::query("
    CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `ip_address` VARCHAR(45) NOT NULL,
        `created_at` DATETIME NOT NULL,
        INDEX `idx_ip_created` (`ip_address`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Tags (0.0.18).
Database::query("
    CREATE TABLE IF NOT EXISTS `tags` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `slug` VARCHAR(100) NOT NULL UNIQUE,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

Database::query("
    CREATE TABLE IF NOT EXISTS `post_tags` (
        `post_id` INT UNSIGNED NOT NULL,
        `tag_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`post_id`, `tag_id`),
        FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
