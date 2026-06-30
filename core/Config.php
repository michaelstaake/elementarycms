<?php

declare(strict_types=1);

namespace Elementary;

class Config
{
    private static ?array $config = null;

    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = ELEMENTARY_ROOT . '/config.php';
        if (!file_exists($path)) {
            self::$config = require ELEMENTARY_ROOT . '/config.sample.php';
            return self::$config;
        }

        self::$config = require $path;
        return self::$config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::load();
        return $config[$key] ?? $default;
    }

    public static function validate(): void
    {
        $config = self::load();

        $langPath = ELEMENTARY_ROOT . '/lang/' . ($config['lang'] ?? '') . '.php';
        if (!file_exists($langPath)) {
            throw new \RuntimeException('Invalid language setting: ' . ($config['lang'] ?? ''), 500);
        }

        $themePath = ELEMENTARY_ROOT . '/theme/' . ($config['theme'] ?? '');
        if (!is_dir($themePath) || !file_exists($themePath . '/index.php')) {
            throw new \RuntimeException('Invalid theme: ' . ($config['theme'] ?? ''), 500);
        }

        $plugins = $config['plugins'] ?? [];
        if (!is_array($plugins)) {
            throw new \RuntimeException('Plugins setting must be an array', 500);
        }

        foreach ($plugins as $plugin) {
            $pluginPath = ELEMENTARY_ROOT . '/plugin/' . $plugin;
            if (!is_dir($pluginPath) || !file_exists($pluginPath . '/index.php') || !file_exists($pluginPath . '/plugin.php')) {
                throw new \RuntimeException('Invalid plugin: ' . $plugin, 500);
            }
        }

        $validTimezones = timezone_identifiers_list();
        if (!in_array($config['time_zone'] ?? '', $validTimezones, true)) {
            throw new \RuntimeException('Invalid timezone: ' . ($config['time_zone'] ?? ''), 500);
        }
    }
}
