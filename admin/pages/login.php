<?php

use Elementary\Hooks;
use Elementary\BruteForceProtection;

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$error = null;
$needs2fa = false;
$banned = false;

$ip = Logger::getClientIp();

if (BruteForceProtection::isBanned($ip)) {
    $banned = true;
    http_response_code(429);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$banned) {
    if (isset($_POST['verify_2fa'])) {
        $result = Auth::verify2fa($_POST['code'] ?? '');
        if ($result['success']) {
            redirect(admin_url());
        }
        BruteForceProtection::recordFailure($ip);
        $error = $result['error'];
        $needs2fa = true;
    } else {
        Hooks::doAction('before_login', $_POST);
        $result = Auth::login($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            redirect(admin_url());
        }
        if (!$result['success'] && !$result['needs_2fa']) {
            BruteForceProtection::recordFailure($ip);
        }
        if ($result['needs_2fa']) {
            $needs2fa = true;
        } else {
            $error = $result['error'];
        }
    }

    // Re-check if this attempt just triggered the ban
    if (BruteForceProtection::isBanned($ip)) {
        $banned = true;
    }
} elseif (isset($_SESSION['pending_2fa_user_id']) && !$banned) {
    $needs2fa = true;
}

$appName = Config::get('app_name', 'Elementary');

ob_start();
?>
<div class="login-page">
    <div class="login-card">
        <h1 class="mb-4 text-center"><?= esc($appName) ?></h1>

        <?php if ($banned): ?>
            <div class="alert alert-danger"><?= __t('too_many_attempts') ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php if ($banned): ?>
            <p class="text-muted mb-3 text-center"><?= __t('too_many_attempts') ?></p>
        <?php elseif ($needs2fa): ?>
            <p class="text-muted mb-3"><?= __t('enter_2fa_code') ?></p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="verify_2fa" value="1">
                <div class="mb-3">
                    <label class="form-label" for="code"><?= __t('verification_code') ?></label>
                    <input type="text" id="code" name="code" class="form-control" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autofocus onfocus="this.select()">
                </div>
                <button type="submit" class="btn btn-dark w-100"><?= __t('verify') ?></button>
            </form>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username"><?= __t('username') ?></label>
                    <input type="text" id="username" name="username" class="form-control" required autocomplete="username" autofocus onfocus="this.select()">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password"><?= __t('password') ?></label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <?php Hooks::doAction('login_form_fields'); ?>
                <button type="submit" class="btn btn-dark w-100"><?= __t('login') ?></button>
            </form>
            <?php if (!$banned): ?>
            <p class="text-center mt-3"><a href="<?= admin_url('forgot-password') ?>"><?= __t('forgot_password') ?></a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php
admin_layout(__t('login'), ob_get_clean());
