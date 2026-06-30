<?php

declare(strict_types=1);

namespace Elementary;

class Validator
{
    public const RESERVED_SLUGS = ['admin', 'lang', 'plugin', 'theme', 'cache', 'vendor', 'install', 'core', 'file'];

    public static function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_SLUGS, true);
    }

    public static function slug(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    public static function validateSlug(string $slug, string $type = 'item'): ?string
    {
        if ($slug === '') {
            return ucfirst($type) . ' slug is required.';
        }
        if (self::isReservedSlug($slug)) {
            return 'The slug "' . $slug . '" is reserved and cannot be used.';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Slug must contain only lowercase letters, numbers, and hyphens.';
        }
        return null;
    }

    public static function validateEmail(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address.';
        }
        return null;
    }

    public static function validateUsername(string $username): ?string
    {
        if (strlen($username) < 3) {
            return 'Username must be at least 3 characters.';
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return 'Username can only contain letters, numbers, underscores, and hyphens.';
        }
        return null;
    }

    public static function sanitizeMenuUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return '#';
        }

        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url)) {
            return '#';
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (preg_match('#^mailto:[^\s]+@#i', $url)) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return '#';
    }
}
