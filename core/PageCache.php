<?php

declare(strict_types=1);

namespace Elementary;

/**
 * @deprecated Use HtmlCache instead. Kept for backward compatibility.
 */
class PageCache
{
    public static function isEnabled(): bool
    {
        return HtmlCache::isEnabled();
    }

    public static function get(string $slug): ?string
    {
        return HtmlCache::getPage($slug);
    }

    public static function getHome(): ?string
    {
        return HtmlCache::getHome();
    }

    public static function put(string $slug, string $html): void
    {
        HtmlCache::putPage($slug, $html);
    }

    public static function putHome(string $html): void
    {
        HtmlCache::putHome($html);
    }

    public static function delete(string $slug): void
    {
        HtmlCache::deletePage($slug);
    }

    public static function deleteHome(): void
    {
        HtmlCache::deleteHome();
    }

    public static function invalidateForPage(int $pageId, ?string $previousSlug = null): void
    {
        HtmlCache::invalidateForPage($pageId, $previousSlug);
    }

    public static function invalidateHome(): void
    {
        HtmlCache::invalidateHome();
    }

    public static function clearAll(): void
    {
        HtmlCache::clearAll();
    }
}
