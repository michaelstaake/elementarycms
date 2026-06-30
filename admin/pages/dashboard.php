<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\Config;
use Elementary\SystemCheck;

$user = Auth::user();
$userId = (int) $user['id'];

$postCount = Database::fetch('SELECT COUNT(*) as cnt FROM posts')['cnt'] ?? 0;
$pageCount = Database::fetch('SELECT COUNT(*) as cnt FROM pages')['cnt'] ?? 0;
$fileCount = Database::fetch('SELECT COUNT(*) as cnt FROM files WHERE deleted_at IS NULL')['cnt'] ?? 0;

// System info
$appVersion = require ELEMENTARY_ROOT . '/version.php';

function dirSize(string $path): int
{
    $size = 0;
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        $size += $file->getSize();
    }
    return $size;
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

$diskSize = dirSize(ELEMENTARY_ROOT);

$dbSizeRow = Database::fetch(
    "SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?",
    [Config::get('db_name', '')]
);
$dbSize = (int) ($dbSizeRow['size'] ?? 0);

$phpVersion = PHP_VERSION;
$allExtensionsPresent = SystemCheck::allExtensionsPresent();
$allDirectoriesOk = SystemCheck::allDirectoriesOk();
$canAccessSettings = Auth::canAccessSettings();

$recentPages = Database::fetchAll(
    'SELECT id, title, slug, page_type FROM pages WHERE author_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 5',
    [$userId]
);
$recentPosts = Database::fetchAll(
    'SELECT id, title, slug FROM posts WHERE author_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 5',
    [$userId]
);
$recentFiles = Database::fetchAll(
    'SELECT id, title, filename FROM files WHERE uploaded_by = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 5',
    [$userId]
);

ob_start();
?>
<div class="dashboard-welcome">
    <h1 class="h3"><?= __t('dashboard') ?></h1>
    <p class="text-muted"><?= __t('welcome_back', ['name' => $user['username']]) ?></p>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <a href="<?= admin_url('pages') ?>" class="dashboard-card-header">
            <i class="bi bi-file-earmark card-icon"></i>
            <span class="card-title"><?= __t('pages') ?></span>
            <span class="card-count"><?= (int) $pageCount ?></span>
        </a>
        <div class="dashboard-card-body">
            <?php if ($recentPages): ?>
                <?php foreach ($recentPages as $page): ?>
                    <a href="<?= admin_entity_url('pages', 'edit', admin_page_slug($page)) ?>" class="recent-item">
                        <?= esc($page['title']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="recent-item" style="color:#999;"><?= __t('no_pages_yet') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-card">
        <a href="<?= admin_url('posts') ?>" class="dashboard-card-header">
            <i class="bi bi-newspaper card-icon"></i>
            <span class="card-title"><?= __t('posts') ?></span>
            <span class="card-count"><?= (int) $postCount ?></span>
        </a>
        <div class="dashboard-card-body">
            <?php if ($recentPosts): ?>
                <?php foreach ($recentPosts as $post): ?>
                    <a href="<?= admin_entity_url('posts', 'edit', $post['slug']) ?>" class="recent-item">
                        <?= esc($post['title']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="recent-item" style="color:#999;"><?= __t('no_posts_yet') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-card">
        <a href="<?= admin_url('files') ?>" class="dashboard-card-header">
            <i class="bi bi-folder card-icon"></i>
            <span class="card-title"><?= __t('files') ?></span>
            <span class="card-count"><?= (int) $fileCount ?></span>
        </a>
        <div class="dashboard-card-body">
            <?php if ($recentFiles): ?>
                <?php foreach ($recentFiles as $file): ?>
                    <a href="<?= admin_entity_url('files', 'edit', admin_file_slug($file['filename'])) ?>" class="recent-item">
                        <?= esc($file['title']) ?>
                        <span class="item-status"><?= esc(pathinfo($file['filename'], PATHINFO_EXTENSION)) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="recent-item" style="color:#999;"><?= __t('no_files_yet') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-card">
        <?php if ($canAccessSettings): ?>
        <a href="<?= admin_url('settings') ?>" class="dashboard-card-header">
            <i class="bi bi-gear card-icon"></i>
            <span class="card-title"><?= __t('system') ?></span>
            <span class="card-count">v<?= esc($appVersion) ?></span>
        </a>
        <?php else: ?>
        <div class="dashboard-card-header">
            <i class="bi bi-gear card-icon"></i>
            <span class="card-title"><?= __t('system') ?></span>
            <span class="card-count">v<?= esc($appVersion) ?></span>
        </div>
        <?php endif; ?>
        <div class="dashboard-card-body">
            <div class="info-item">
                <span><?= __t('disk_usage') ?></span>
                <span class="item-status"><?= esc(formatBytes($diskSize)) ?></span>
            </div>
            <div class="info-item">
                <span><?= __t('database_size') ?></span>
                <span class="item-status"><?= esc(formatBytes($dbSize)) ?></span>
            </div>
            <div class="info-item">
                <span><?= __t('php_version') ?></span>
                <span class="item-status"><?= esc($phpVersion) ?></span>
            </div>
            <div class="info-item">
                <span><?= __t('php_extensions') ?></span>
                <?php if ($allExtensionsPresent): ?>
                    <i class="bi bi-check-circle-fill item-status text-success"></i>
                <?php else: ?>
                    <i class="bi bi-exclamation-triangle-fill item-status text-danger"></i>
                <?php endif; ?>
            </div>
            <div class="info-item">
                <span><?= __t('directory_permissions') ?></span>
                <?php if ($allDirectoriesOk): ?>
                    <i class="bi bi-check-circle-fill item-status text-success"></i>
                <?php else: ?>
                    <i class="bi bi-exclamation-triangle-fill item-status text-danger"></i>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
admin_layout(__t('dashboard'), ob_get_clean());
