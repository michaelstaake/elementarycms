<?php

declare(strict_types=1);

use Elementary\Auth;
use Elementary\Config;
use Elementary\Upgrader;

Auth::requireLogin();
if (!Auth::canAccessSettings()) {
    throw new RuntimeException('Forbidden', 403);
}

$result = null;
$error = null;
$success = null;

$fileVersion = require ELEMENTARY_ROOT . '/version.php';
$dbVersion = Upgrader::getDbVersion();
$pendingUpdates = Upgrader::getPendingUpdates();
$needsUpgrade = version_compare($fileVersion, $dbVersion, '>');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        if (!$needsUpgrade) {
            $success = 'No updates needed. The database is already up to date.';
        } else {
            $result = Upgrader::run();

            if ($result['success']) {
                $success = $result['message'];
                // Clear the upgrade-required flag so the site works again
                unset($_SESSION['upgrade_required']);
            } else {
                $error = $result['message'];
            }
        }
    }
}

$appName = Config::get('app_name', 'Elementary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update - <?= esc($appName) ?></title>
    <link href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <style>
        body { background: #f5f5f0; color: #1a1a1a; }
        .update-card { max-width: 640px; margin: 4rem auto; background: #fff; border: 1px solid #e0e0d8; border-radius: 8px; padding: 2.5rem; }
        .version-box { background: #f5f5f0; border: 1px solid #e0e0d8; border-radius: 4px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .version-box .label { font-size: 0.85rem; color: #666; margin-bottom: 0.25rem; }
        .version-box .value { font-family: 'Courier New', monospace; font-size: 1.25rem; font-weight: 600; }
        .pending-list { list-style: none; padding: 0; margin: 0; }
        .pending-list li { padding: 0.4rem 0; font-family: 'Courier New', monospace; }
        .btn-dark { background: #1a1a1a; border-color: #1a1a1a; }
        .btn-dark:hover { background: #333; border-color: #333; }
        .btn-success { background: #198754; border-color: #198754; }
        .back-link { color: #666; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="update-card">
            <h1 class="h3 mb-1">Database Update</h1>
            <p class="text-muted mb-4"><?= esc($appName) ?> has been updated and requires a database migration.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= esc($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= esc($success) ?></div>
                <div class="mt-3">
                    <?php if (empty($_SESSION['upgrade_required'])): ?>
                        <a href="<?= admin_url('dashboard') ?>" class="btn btn-dark">Return to Dashboard</a>
                    <?php else: ?>
                        <a href="<?= url('') ?>" class="btn btn-dark">Visit Site</a>
                        <a href="<?= admin_url('login') ?>" class="btn btn-outline-secondary">Admin Login</a>
                    <?php endif; ?>
                </div>
            <?php elseif ($needsUpgrade): ?>
                <div class="version-box">
                    <div class="row">
                        <div class="col-6">
                            <div class="label">Database version</div>
                            <div class="value"><?= esc($dbVersion) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="label">Application version</div>
                            <div class="value"><?= esc($fileVersion) ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($pendingUpdates)): ?>
                    <p class="mb-2"><strong>Pending updates:</strong></p>
                    <ul class="pending-list mb-4">
                        <?php foreach ($pendingUpdates as $v): ?>
                            <li>v<?= esc($v) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-dark" onclick="return confirm('This will run database migrations. Continue?');">
                        Run Update
                    </button>
                </form>
            <?php else: ?>
                <p class="text-muted">No updates are needed. The database is already up to date.</p>
                <div class="mt-3">
                    <a href="<?= admin_url('dashboard') ?>" class="btn btn-dark">Return to Dashboard</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['user_id'])): ?>
                <div class="mt-4">
                    <a href="<?= admin_url('dashboard') ?>" class="back-link">Back to admin dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
