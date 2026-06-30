<?php

use Elementary\Hooks;
use Elementary\BruteForceProtection;

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$error = null;
$banned = false;
$ip = Logger::getClientIp();

if (BruteForceProtection::isBanned($ip)) {
    $banned = true;
    http_response_code(429);
}

$token = $_GET['token'] ?? '';
$userId = (int) ($_GET['user'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$banned) {
    Hooks::doAction('before_password_reset', $_POST);
    if (($_POST['password'] ?? '') !== ($_POST['password_confirm'] ?? '')) {
        $error = __t('password_mismatch');
    } else {
        $result = Auth::resetPassword($userId, $_POST['token'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            flash('success', __t('password_reset_success'));
            redirect(admin_url('login'));
        }
        $error = $result['error'];
    }
}

ob_start();
?>
<div class="login-page">
    <div class="login-card">
        <h1 class="mb-4 text-center"><?= esc(Config::get('app_name', 'Elementary')) ?></h1>
        <?php if ($banned): ?>
            <div class="alert alert-danger"><?= __t('too_many_attempts') ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>
        <?php if (!$banned): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token) ?>">
            <div class="mb-3">
                <label class="form-label" for="password"><?= __t('new_password') ?></label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8" autofocus onfocus="this.select()">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirm"><?= __t('confirm_password') ?></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8" onfocus="this.select()">
            </div>
            <button type="submit" class="btn btn-dark w-100"><?= __t('reset_password') ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php
admin_layout(__t('reset_password'), ob_get_clean());
