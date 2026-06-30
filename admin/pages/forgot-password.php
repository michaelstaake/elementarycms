<?php

use Elementary\Hooks;
use Elementary\BruteForceProtection;

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$banned = false;
$ip = Logger::getClientIp();

if (BruteForceProtection::isBanned($ip)) {
    $banned = true;
    http_response_code(429);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$banned) {
    Hooks::doAction('before_password_reset_request', $_POST);
    Auth::requestPasswordReset($_POST['username'] ?? '');
    flash('success', __t('reset_email_sent'));
    redirect(admin_url('forgot-password'));
}

ob_start();
?>
<div class="login-page">
    <div class="login-card">
        <h1 class="mb-4 text-center"><?= esc(Config::get('app_name', 'Elementary')) ?></h1>
        <?= admin_flash() ?>
        <?php if ($banned): ?>
            <div class="alert alert-danger"><?= __t('too_many_attempts') ?></div>
        <?php else: ?>
            <p class="text-muted mb-3"><?= __t('forgot_password_instructions') ?></p>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username"><?= __t('username') ?></label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus onfocus="this.select()">
                </div>
                <?php Hooks::doAction('forgot_password_form_fields'); ?>
                <button type="submit" class="btn btn-dark w-100"><?= __t('send_reset_link') ?></button>
            </form>
        <?php endif; ?>
        <p class="text-center mt-3"><a href="<?= admin_url('login') ?>"><?= __t('back_to_login') ?></a></p>
    </div>
</div>
<?php
admin_layout(__t('forgot_password'), ob_get_clean());
