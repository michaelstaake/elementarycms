<?php

declare(strict_types=1);

namespace Elementary;

class SystemCheck
{
    /**
     * PHP extensions required for Elementary CMS to work properly.
     *
     * @return array<string, string> Extension name => translation key for description
     */
    public static function requiredExtensions(): array
    {
        return [
            'pdo'       => 'ext_pdo',
            'pdo_mysql' => 'ext_pdo_mysql',
            'json'      => 'ext_json',
            'session'   => 'ext_session',
            'fileinfo'  => 'ext_fileinfo',
            'openssl'   => 'ext_openssl',
            'mbstring'  => 'ext_mbstring',
        ];
    }

    /**
     * @return list<array{name: string, present: bool, description_key: string}>
     */
    public static function checkExtensions(): array
    {
        $results = [];

        foreach (self::requiredExtensions() as $extension => $descriptionKey) {
            $results[] = [
                'name'            => $extension,
                'present'         => self::isExtensionPresent($extension),
                'description_key' => $descriptionKey,
            ];
        }

        return $results;
    }

    public static function allExtensionsPresent(): bool
    {
        foreach (self::requiredExtensions() as $extension => $descriptionKey) {
            if (!self::isExtensionPresent($extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     path: string,
     *     exists: bool,
     *     readable: bool,
     *     writable: bool,
     *     status: 'ok'|'warning'|'error',
     *     status_key: string
     * }
     */
    public static function checkDirectory(string $path, bool $requiresReadable = true): array
    {
        $exists = is_dir($path);
        $readable = $exists && is_readable($path);
        $writable = $exists && is_writable($path);
        $parentWritable = is_writable(dirname($path));

        if ($exists) {
            $ok = $writable && (!$requiresReadable || $readable);
            return [
                'path'        => $path,
                'exists'      => true,
                'readable'    => $readable,
                'writable'    => $writable,
                'status'      => $ok ? 'ok' : 'error',
                'status_key'  => $ok ? 'dir_status_ok' : 'dir_status_not_writable',
            ];
        }

        if ($parentWritable) {
            return [
                'path'        => $path,
                'exists'      => false,
                'readable'    => false,
                'writable'    => false,
                'status'      => 'warning',
                'status_key'  => 'dir_status_missing_creatable',
            ];
        }

        return [
            'path'        => $path,
            'exists'      => false,
            'readable'    => false,
            'writable'    => false,
            'status'      => 'error',
            'status_key'  => 'dir_status_missing_not_creatable',
        ];
    }

    /**
     * @return list<array{
     *     label_key: string,
     *     path: string,
     *     exists: bool,
     *     readable: bool,
     *     writable: bool,
     *     status: 'ok'|'warning'|'error',
     *     status_key: string
     * }>
     */
    public static function checkStorageDirectories(): array
    {
        return [
            array_merge(
                ['label_key' => 'cache_directory', 'path' => HtmlCache::getCacheRoot()],
                self::checkDirectory(HtmlCache::getCacheRoot(), true)
            ),
            array_merge(
                ['label_key' => 'file_upload_directory', 'path' => FileManager::getStorageDir()],
                self::checkDirectory(FileManager::getStorageDir(), true)
            ),
        ];
    }

    public static function allDirectoriesOk(): bool
    {
        foreach (self::checkStorageDirectories() as $directory) {
            if ($directory['status'] !== 'ok' && $directory['status'] !== 'warning') {
                return false;
            }
        }

        return true;
    }

    private static function isExtensionPresent(string $extension): bool
    {
        if ($extension === 'pdo_mysql') {
            return extension_loaded('pdo') && in_array('mysql', \PDO::getAvailableDrivers(), true);
        }

        return extension_loaded($extension);
    }
}
