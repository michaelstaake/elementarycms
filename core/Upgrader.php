<?php

declare(strict_types=1);

namespace Elementary;

class Upgrader
{
    public static function run(): array
    {
        if (!Config::get('installed', false)) {
            return ['success' => false, 'message' => 'Not installed.'];
        }

        $fileVersion = require ELEMENTARY_ROOT . '/version.php';
        $dbVersion = self::getDbVersion();

        if (version_compare($fileVersion, $dbVersion, '<=')) {
            return ['success' => true, 'message' => 'Already up to date.'];
        }

        $updatesDir = ELEMENTARY_ROOT . '/core/updates';
        $files = glob($updatesDir . '/*.php') ?: [];
        usort($files, function ($a, $b) {
            $va = basename($a, '.php');
            $vb = basename($b, '.php');
            return version_compare($va, $vb);
        });

        $upgradedVersions = [];
        try {
            foreach ($files as $file) {
                $version = basename($file, '.php');
                if (version_compare($version, $dbVersion, '>') && version_compare($version, $fileVersion, '<=')) {
                    require $file;
                    self::setDbVersion($version);
                    Logger::log('system_upgrade', null, 'Upgraded to version ' . $version);
                    $upgradedVersions[] = $version;
                }
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage(), 'upgraded_versions' => $upgradedVersions];
        }

        return ['success' => true, 'message' => count($upgradedVersions) > 0 ? 'Successfully upgraded to version ' . end($upgradedVersions) : 'Already up to date.', 'upgraded_versions' => $upgradedVersions];
    }

    public static function needsUpgrade(): bool
    {
        if (!Config::get('installed', false)) {
            return false;
        }

        $fileVersion = require ELEMENTARY_ROOT . '/version.php';
        $dbVersion = self::getDbVersion();

        return version_compare($fileVersion, $dbVersion, '>');
    }

    public static function getPendingUpdates(): array
    {
        $fileVersion = require ELEMENTARY_ROOT . '/version.php';
        $dbVersion = self::getDbVersion();

        $updates = [];
        $updatesDir = ELEMENTARY_ROOT . '/core/updates';
        $files = glob($updatesDir . '/*.php') ?: [];
        usort($files, function ($a, $b) {
            $va = basename($a, '.php');
            $vb = basename($b, '.php');
            return version_compare($va, $vb);
        });

        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (version_compare($version, $dbVersion, '>') && version_compare($version, $fileVersion, '<=')) {
                $updates[] = $version;
            }
        }

        return $updates;
    }

    public static function getDbVersion(): string
    {
        try {
            $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = ?', ['db_version']);
            return $row ? (string) $row['setting_value'] : '0.0.0';
        } catch (\Throwable) {
            return '0.0.0';
        }
    }

    public static function setDbVersion(string $version): void
    {
        $existing = Database::fetch('SELECT id FROM settings WHERE setting_key = ?', ['db_version']);
        if ($existing) {
            Database::update('settings', ['setting_value' => $version], 'setting_key = ?', ['db_version']);
        } else {
            Database::insert('settings', [
                'setting_key'   => 'db_version',
                'setting_value' => $version,
            ]);
        }
    }

    public static function isDatabaseEmpty(): bool
    {
        $tables = Database::fetchAll('SHOW TABLES');
        return count($tables) === 0;
    }

    public static function isSchemaInstalled(): bool
    {
        try {
            $tables = Database::fetchAll('SHOW TABLES');
            $names = array_map(static fn(array $row): string => (string) array_values($row)[0], $tables);
            return in_array('users', $names, true) && in_array('settings', $names, true);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function installSchema(): void
    {
        $sql = file_get_contents(ELEMENTARY_ROOT . '/core/schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                Database::query($statement);
            }
        }

        $version = require ELEMENTARY_ROOT . '/version.php';
        self::setDbVersion($version);

        Database::insert('settings', [
            'setting_key'   => 'home_page',
            'setting_value' => 'none',
        ]);
    }
}
