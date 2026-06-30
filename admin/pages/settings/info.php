<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\Config;
use Elementary\Database;
use Elementary\SystemCheck;

// App version
$appVersion = require ELEMENTARY_ROOT . '/version.php';

// Size on disk
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

// Database size
$dbSizeRow = Database::fetch(
    "SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?",
    [Config::get('db_name', '')]
);
$dbSize = (int) ($dbSizeRow['size'] ?? 0);

// PHP version
$phpVersion = PHP_VERSION;

// Server software / Apache version
$apacheVersion = function_exists('apache_get_version') ? apache_get_version() : ($_SERVER['SERVER_SOFTWARE'] ?? __t('unknown'));

$phpExtensions = SystemCheck::checkExtensions();
$allExtensionsPresent = SystemCheck::allExtensionsPresent();
$storageDirectories = SystemCheck::checkStorageDirectories();
$allDirectoriesOk = SystemCheck::allDirectoriesOk();

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('info'), 'url' => '#']
]) ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= __t('system_info') ?></h2>
        <table class="table table-sm mb-0">
            <tbody>
                <tr>
                    <th scope="row" style="width: 220px;"><?= __t('app_version') ?></th>
                    <td><?= esc($appVersion) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= __t('disk_usage') ?></th>
                    <td><?= esc(formatBytes($diskSize)) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= __t('database_size') ?></th>
                    <td><?= esc(formatBytes($dbSize)) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= __t('php_version') ?></th>
                    <td><?= esc($phpVersion) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= __t('apache_version') ?></th>
                    <td><?= esc($apacheVersion) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><?= __t('php_extensions') ?></h2>
            <?php if ($allExtensionsPresent): ?>
                <span class="badge bg-success"><?= __t('all_requirements_met') ?></span>
            <?php else: ?>
                <span class="badge bg-danger"><?= __t('requirements_missing') ?></span>
            <?php endif; ?>
        </div>
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th scope="col"><?= __t('extension') ?></th>
                    <th scope="col"><?= __t('purpose') ?></th>
                    <th scope="col" style="width: 120px;"><?= __t('status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($phpExtensions as $extension): ?>
                <tr>
                    <td><code><?= esc($extension['name']) ?></code></td>
                    <td class="text-muted"><?= __t($extension['description_key']) ?></td>
                    <td>
                        <?php if ($extension['present']): ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i><?= __t('present') ?></span>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i><?= __t('missing') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><?= __t('directory_permissions') ?></h2>
            <?php if ($allDirectoriesOk): ?>
                <span class="badge bg-success"><?= __t('all_requirements_met') ?></span>
            <?php else: ?>
                <span class="badge bg-danger"><?= __t('requirements_missing') ?></span>
            <?php endif; ?>
        </div>
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th scope="col"><?= __t('directory') ?></th>
                    <th scope="col"><?= __t('path') ?></th>
                    <th scope="col" style="width: 120px;"><?= __t('status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($storageDirectories as $directory): ?>
                <tr>
                    <td><?= __t($directory['label_key']) ?></td>
                    <td><code><?= esc($directory['path']) ?></code></td>
                    <td>
                        <?php if ($directory['status'] === 'ok'): ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i><?= __t('dir_status_ok') ?></span>
                        <?php elseif ($directory['status'] === 'warning'): ?>
                            <span class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= __t($directory['status_key']) ?></span>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i><?= __t($directory['status_key']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
admin_layout(__t('info'), ob_get_clean());
