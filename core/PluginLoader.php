<?php

declare(strict_types=1);

namespace Elementary;

class PluginLoader
{
    private static array $loaded = [];
    private static array $pluginInfo = [];

    public static function loadAll(): void
    {
        $plugins = Config::get('plugins', []);
        foreach ($plugins as $plugin) {
            self::load($plugin);
        }
    }

    public static function load(string $plugin): void
    {
        if (isset(self::$loaded[$plugin])) {
            return;
        }

        $path = ELEMENTARY_ROOT . '/plugin/' . $plugin;
        if (!is_dir($path)) {
            return;
        }

        $infoFile = $path . '/index.php';
        $pluginFile = $path . '/plugin.php';

        if (!file_exists($infoFile) || !file_exists($pluginFile)) {
            return;
        }

        $info = require $infoFile;
        self::$pluginInfo[$plugin] = is_array($info) ? $info : [];

        $enabled = Database::fetch('SELECT enabled FROM plugin_status WHERE plugin_slug = ?', [$plugin]);
        if (!$enabled) {
            Database::insert('plugin_status', [
                'plugin_slug' => $plugin,
                'enabled'     => 1,
                'installed_at' => date('Y-m-d H:i:s'),
            ]);
            Hooks::doAction('plugin_install', $plugin, self::$pluginInfo[$plugin]);
        }

        require_once $pluginFile;
        self::$loaded[$plugin] = true;
        Hooks::doAction('plugin_loaded', $plugin, self::$pluginInfo[$plugin]);
    }

    public static function getInfo(string $plugin): array
    {
        return self::$pluginInfo[$plugin] ?? [];
    }

    public static function getAllInfo(): array
    {
        $plugins = [];
        $dirs = glob(ELEMENTARY_ROOT . '/plugin/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $infoFile = $dir . '/index.php';
            if (file_exists($infoFile)) {
                $info = require $infoFile;
                $plugins[$slug] = is_array($info) ? $info : [];
            }
        }
        return $plugins;
    }

    public static function getSettingsPages(): array
    {
        return Hooks::applyFilters('admin_settings_pages', []);
    }
}
