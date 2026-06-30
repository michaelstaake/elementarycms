<?php

declare(strict_types=1);

namespace Elementary;

class Router
{
    private string $route;
    private array $segments;

    public function __construct()
    {
        $this->route = trim($_GET['route'] ?? '', '/');
        $this->segments = $this->route !== '' ? explode('/', $this->route) : [];
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getSegment(int $index, ?string $default = null): ?string
    {
        return $this->segments[$index] ?? $default;
    }

    public function isAdmin(): bool
    {
        return ($this->segments[0] ?? '') === 'admin';
    }

    public function isInstall(): bool
    {
        return ($this->segments[0] ?? '') === 'install';
    }

    public function dispatch(): void
    {
        if ($this->isInstall()) {
            if (Config::get('installed', false)) {
                throw new \RuntimeException('Page not found', 404);
            }
            require ELEMENTARY_ROOT . '/install/index.php';
            return;
        }

        if (!Config::get('installed', false)) {
            throw new \RuntimeException('Configuration invalid', 503);
        }

        if ($this->isUpdate()) {
            \redirect(\admin_url('login'));
            return;
        }

        if (!$this->isAdmin() && Upgrader::needsUpgrade()) {
            self::renderUpgradeRequired();
            return;
        }

        if (Config::get('maintenance_mode', false) && !$this->isAdmin()) {
            throw new \RuntimeException('Site is under maintenance', 503);
        }

        Hooks::doAction('init');

        if ($this->isAdmin()) {
            Logger::purge();
            require ELEMENTARY_ROOT . '/admin/index.php';
            return;
        }

        $frontend = new Frontend($this);
        $frontend->dispatch();
    }

    public function isUpdate(): bool
    {
        return ($this->segments[0] ?? '') === 'update';
    }

    private static function renderUpgradeRequired(): void
    {
        $appName = Config::get('app_name', 'Elementary');
        $dbVersion = Upgrader::getDbVersion();
        $fileVersion = require ELEMENTARY_ROOT . '/version.php';

        if (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code(503);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Required - <?= htmlspecialchars($appName) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f0;
            color: #1a1a1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .error-container {
            max-width: 720px;
            width: 100%;
            background: #fff;
            border: 1px solid #e0e0d8;
            border-radius: 8px;
            padding: 2.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .error-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        .error-message {
            color: #555;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .version-info {
            background: #f5f5f0;
            border: 1px solid #e0e0d8;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">503</div>
        <div class="error-name">Update Required</div>
        <p class="error-message">
            A database update is required before this site can continue running.
            The administrator needs to run the update to bring the database up to date.
        </p>
        <?php if (Config::get('debug_mode', false)): ?>
        <div class="version-info">
            Database version: <?= htmlspecialchars($dbVersion) ?><br>
            Application version: <?= htmlspecialchars($fileVersion) ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
        echo ob_get_clean();
        exit;
    }
}
