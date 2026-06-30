<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\BruteForceProtection;
use Elementary\Database;

$enabled = BruteForceProtection::isEnabled();
$maxAttempts = BruteForceProtection::getMaxAttempts();
$blockDuration = BruteForceProtection::getBlockDuration();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newEnabled = isset($_POST['brute_force_enabled']);
    $newMaxAttempts = (int) ($_POST['brute_force_max_attempts'] ?? 5);
    $newBlockDuration = (int) ($_POST['brute_force_block_duration'] ?? 3600);

    if (!in_array($newMaxAttempts, [5, 10, 20], true)) {
        $newMaxAttempts = 5;
    }
    if (!in_array($newBlockDuration, [3600, 86400, 2592000], true)) {
        $newBlockDuration = 3600;
    }

    set_setting('brute_force_protection_enabled', $newEnabled);
    set_setting('brute_force_max_attempts', $newMaxAttempts);
    set_setting('brute_force_block_duration', $newBlockDuration);

    if (!$newEnabled) {
        BruteForceProtection::clearAll();
    }

    $enabled = $newEnabled;
    $maxAttempts = $newMaxAttempts;
    $blockDuration = $newBlockDuration;

    Logger::log('settings_updated', Auth::user()['id'], 'Security settings updated: brute force protection ' . ($newEnabled ? 'enabled' : 'disabled'));
    flash('success', __t('settings_saved'));
    redirect(admin_url('settings/security'));
}

$bannedIps = $enabled ? BruteForceProtection::getBannedIps() : [];

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('security'), 'url' => '#']
]) ?>
<?= admin_flash() ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= __t('brute_force_protection') ?></h2>
        <p class="text-muted mb-4"><?= __t('brute_force_protection_desc') ?></p>

        <form method="post" style="max-width: 600px;">
            <?= csrf_field() ?>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="brute_force_enabled" name="brute_force_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="brute_force_enabled"><?= __t('brute_force_enabled') ?></label>
                </div>
                <div class="form-text"><?= __t('brute_force_enabled_help') ?></div>
            </div>

            <?php if ($enabled): ?>
            <div class="mb-3">
                <label class="form-label" for="brute_force_max_attempts"><?= __t('brute_force_max_attempts') ?></label>
                <select name="brute_force_max_attempts" id="brute_force_max_attempts" class="form-select" style="max-width: 200px;">
                    <option value="5" <?= $maxAttempts === 5 ? 'selected' : '' ?>>5</option>
                    <option value="10" <?= $maxAttempts === 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $maxAttempts === 20 ? 'selected' : '' ?>>20</option>
                </select>
                <div class="form-text"><?= __t('brute_force_max_attempts_help') ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="brute_force_block_duration"><?= __t('brute_force_block_duration') ?></label>
                <select name="brute_force_block_duration" id="brute_force_block_duration" class="form-select" style="max-width: 200px;">
                    <option value="3600" <?= $blockDuration === 3600 ? 'selected' : '' ?>>60 <?= __t('minutes') ?></option>
                    <option value="86400" <?= $blockDuration === 86400 ? 'selected' : '' ?>>24 <?= __t('hours') ?></option>
                    <option value="2592000" <?= $blockDuration === 2592000 ? 'selected' : '' ?>>30 <?= __t('days') ?></option>
                </select>
                <div class="form-text"><?= __t('brute_force_block_duration_help') ?></div>
            </div>

            <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
            <?php else: ?>
            <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($enabled): ?>
<div class="card">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= __t('banned_ips') ?></h2>
        <?php if (empty($bannedIps)): ?>
            <p class="text-muted mb-0"><?= __t('banned_ips_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __t('ip_address') ?></th>
                            <th><?= __t('failed_attempts') ?></th>
                            <th><?= __t('ban_expires') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bannedIps as $ban): ?>
                        <tr>
                            <td><?= esc($ban['ip_address']) ?></td>
                            <td><?= $ban['attempts'] ?></td>
                            <td><?= format_date($ban['ban_expires']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning mb-0"><?= __t('brute_force_disabled_msg') ?></div>
<?php endif; ?>
<?php
admin_layout(__t('security'), ob_get_clean());
