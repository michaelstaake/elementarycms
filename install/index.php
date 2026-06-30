<?php

declare(strict_types=1);

use Elementary\Config;
use Elementary\ContentManager;
use Elementary\Database;
use Elementary\ErrorHandler;
use Elementary\Upgrader;

if (Config::get('installed', false)) {
    header('Location: ' . url('/'));
    exit;
}

$error = null;
$success = false;
$pendingConfig = false;
$ready = false;

try {
    Database::connect();
    if (!Upgrader::isDatabaseEmpty()) {
        if (Upgrader::isSchemaInstalled()) {
            $pendingConfig = true;
        } else {
            throw new \RuntimeException('Database is not empty', 403);
        }
    } else {
        $ready = true;
    }
} catch (\RuntimeException $e) {
    if ($e->getCode() === 403) {
        ErrorHandler::handleException($e);
    }
    $error = $e->getMessage();
} catch (\Throwable $e) {
    $error = 'Database connection failed. Check the database settings in config.php.';
}

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
    try {
        if (!Upgrader::isDatabaseEmpty()) {
            throw new \RuntimeException('Database is not empty', 403);
        }

        $adminPass = $_POST['admin_password'] ?? '';
        if (strlen($adminPass) < 8) {
            throw new \RuntimeException('Admin password must be at least 8 characters.');
        }

        Upgrader::installSchema();

        $now = date('Y-m-d H:i:s');
        Database::insert('users', [
            'username'   => $_POST['admin_username'] ?? 'admin',
            'email'      => $_POST['admin_email'] ?? '',
            'password'   => password_hash($adminPass, PASSWORD_DEFAULT),
            'user_group' => 'admin',
            'timezone'   => 'system',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ContentManager::ensurePostsPage(1);
        set_setting('home_page', (string) ContentManager::getPostsPage()['id']);

        $success = true;
        $pendingConfig = true;
    } catch (\RuntimeException $e) {
        if ($e->getCode() === 403) {
            ErrorHandler::handleException($e);
        }
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
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
  <title>Install <?= esc($appName) ?></title>
  <link href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
  <style>
    body { background: #f5f5f0; color: #1a1a1a; }
    .install-card { max-width: 600px; margin: 3rem auto; background: #fff; border: 1px solid #e0e0d8; border-radius: 8px; padding: 2rem; }
  </style>
</head>
<body>
  <div class="container">
    <div class="install-card">
      <h1 class="h3 mb-4">Install <?= esc($appName) ?></h1>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
      <?php endif; ?>

      <?php if ($success || $pendingConfig): ?>
        <div class="alert alert-success">
          <?php if ($success): ?>
            Database setup and admin account created.
          <?php else: ?>
            Database setup is already complete.
          <?php endif; ?>
          Edit <code>config.php</code> and set <code>'installed' => true</code>, then
          <a href="<?= admin_url('login') ?>">log in to admin</a>.
        </div>
      <?php elseif ($ready): ?>
        <p class="text-muted mb-4">Database connection successful. Create your admin account to finish installation.</p>
        <form method="post">
          <?= csrf_field() ?>
          <h2 class="h5 mb-3">Admin Account</h2>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="admin_username" class="form-control" required value="admin">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="admin_email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="admin_password" class="form-control" required minlength="8">
          </div>
          <button type="submit" class="btn btn-dark">Complete Installation</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
