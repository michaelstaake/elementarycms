<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\HtmlCache;

$analyticsEnabled = (bool) setting('analytics_enabled', false);
$analyticsCode = (string) setting('analytics_code', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newEnabled = isset($_POST['analytics_enabled']);
    $newCode = trim($_POST['analytics_code'] ?? '');

    set_setting('analytics_enabled', $newEnabled);
    set_setting('analytics_code', $newCode);

    // Invalidate all cached content so new analytics code is included
    HtmlCache::clearAll();

    $analyticsEnabled = $newEnabled;
    $analyticsCode = $newCode;

    Logger::log('settings_updated', Auth::user()['id'], 'Analytics settings updated: ' . ($newEnabled ? 'enabled' : 'disabled'));
    flash('success', __t('settings_saved'));
    redirect(admin_url('settings/analytics'));
}

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('analytics'), 'url' => '#']
]) ?>
<?= admin_flash() ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= __t('analytics') ?></h2>
        <p class="text-muted mb-4"><?= __t('analytics_desc') ?></p>

        <form method="post" style="max-width: 600px;">
            <?= csrf_field() ?>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="analytics_enabled" name="analytics_enabled" value="1" <?= $analyticsEnabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="analytics_enabled"><?= __t('analytics_enabled') ?></label>
                </div>
                <div class="form-text"><?= __t('analytics_enabled_help') ?></div>
            </div>

            <?php if ($analyticsEnabled): ?>
            <div class="mb-3">
                <label class="form-label" for="analytics_code"><?= __t('analytics_code') ?></label>
                <textarea class="form-control" id="analytics_code" name="analytics_code" rows="8" style="font-family: monospace; font-size: 0.875rem;"><?= esc($analyticsCode) ?></textarea>
                <div class="form-text"><?= __t('analytics_code_help') ?></div>
            </div>

            <p class="text-muted mb-3"><?= __t('analytics_cache_warning') ?></p>
            <?php endif; ?>

            <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
        </form>
    </div>
</div>

<?php
admin_layout(__t('analytics'), ob_get_clean());
