<?php

declare(strict_types=1);

namespace Elementary;

class Hooks
{
    private static array $actions = [];
    private static array $filters = [];

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
    }

    public static function doAction(string $hook, mixed ...$args): void
    {
        if (!isset(self::$actions[$hook])) {
            return;
        }
        ksort(self::$actions[$hook]);
        foreach (self::$actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
    }

    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }
        ksort(self::$filters[$hook]);
        foreach (self::$filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback, array_merge([$value], $args));
            }
        }
        return $value;
    }

    public static function removeAction(string $hook, callable $callback): void
    {
        if (!isset(self::$actions[$hook])) {
            return;
        }
        foreach (self::$actions[$hook] as $priority => $callbacks) {
            self::$actions[$hook][$priority] = array_filter(
                $callbacks,
                fn($cb) => $cb !== $callback
            );
        }
    }

    public static function removeFilter(string $hook, callable $callback): void
    {
        if (!isset(self::$filters[$hook])) {
            return;
        }
        foreach (self::$filters[$hook] as $priority => $callbacks) {
            self::$filters[$hook][$priority] = array_filter(
                $callbacks,
                fn($cb) => $cb !== $callback
            );
        }
    }
}
