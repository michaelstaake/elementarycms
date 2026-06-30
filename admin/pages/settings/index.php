<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\Config;
use Elementary\PluginLoader;

$pluginPages = PluginLoader::getSettingsPages();
$appName = Config::get('app_name', 'Elementary');

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')]
]) ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= esc($appName) ?></h2>
        <ul class="list-unstyled mb-0">
            <li class="mb-2"><a href="<?= admin_url('settings/home-page') ?>"><i class="bi bi-house me-2"></i><?= __t('home_page') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('settings/menus') ?>"><i class="bi bi-list-ul me-2"></i><?= __t('menus') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('settings/recycle-bin') ?>"><i class="bi bi-trash3 me-2"></i><?= __t('recycle_bin') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('settings/info') ?>"><i class="bi bi-info-circle me-2"></i><?= __t('info') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('settings/security') ?>"><i class="bi bi-shield-lock me-2"></i><?= __t('security') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('settings/analytics') ?>"><i class="bi bi-bar-chart me-2"></i><?= __t('analytics') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('settings/favicon') ?>"><i class="bi bi-image me-2"></i><?= __t('favicon') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('users') ?>"><i class="bi bi-people me-2"></i><?= __t('users') ?></a></li>
            <li class="mb-2"><a href="<?= admin_url('logs') ?>"><i class="bi bi-journal-text me-2"></i><?= __t('logs') ?></a></li>
        </ul>
    </div>
</div>

<?php if (!empty($pluginPages)): ?>
<div class="card">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= __t('plugins') ?></h2>
        <ul class="list-unstyled mb-0">
            <?php foreach ($pluginPages as $slug => $info): ?>
            <li class="mb-2"><a href="<?= admin_url('settings/' . $slug) ?>"><i class="bi bi-puzzle me-2"></i><?= esc($info['title'] ?? $slug) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
<?php
admin_layout(__t('settings'), ob_get_clean());
