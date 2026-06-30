<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\Logger;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    turnstile_set_setting('site_key', trim($_POST['site_key'] ?? ''));
    turnstile_set_setting('secret_key', trim($_POST['secret_key'] ?? ''));
    Logger::log('plugin_settings_updated', Auth::user()['id'], 'cloudflare-turnstile');
    flash('success', __t('settings_saved'));
    redirect(admin_url('settings/cloudflare-turnstile'));
}

$siteKey = turnstile_get_setting('site_key');
$secretKey = turnstile_get_setting('secret_key');

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => 'Cloudflare Turnstile', 'url' => '#']
]) ?>
<h1 class="h3 mb-4">Cloudflare Turnstile</h1>
<?= admin_flash() ?>

<form method="post" class="card p-4" style="max-width: 600px;">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Site Key</label>
        <input type="text" name="site_key" class="form-control" value="<?= esc($siteKey) ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Secret Key</label>
        <input type="password" name="secret_key" class="form-control" value="<?= esc($secretKey) ?>">
    </div>
    <p class="text-muted small">Get your keys from the <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener">Cloudflare Dashboard</a>.</p>
    <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
</form>
<?php
admin_layout('Cloudflare Turnstile', ob_get_clean());
