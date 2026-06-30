<?php

declare(strict_types=1);

namespace Elementary;

class HtmlCache
{
    private const HOME_FILENAME = '_home.html';

    public static function isEnabled(): bool
    {
        return (bool) Config::get('cache', Config::get('page_cache', true));
    }

    /**
     * Serve cached HTML before full bootstrap when possible.
     */
    public static function tryServeEarly(): bool
    {
        $config = Config::load();
        if (!($config['installed'] ?? false)) {
            return false;
        }

        if (!self::isEnabled()) {
            return false;
        }

        $route = trim($_GET['route'] ?? '', '/');
        $segments = $route !== '' ? explode('/', $route) : [];
        $first = $segments[0] ?? '';

        if (in_array($first, ['admin', 'install', 'update'], true)) {
            return false;
        }

        if (($config['maintenance_mode'] ?? false) && $first !== 'admin') {
            return false;
        }

        if ($route === '') {
            $html = self::getHome();
        } elseif ($first === 'search') {
            $query = trim($_GET['q'] ?? '');
            $html = $query !== '' ? self::getSearch($query) : null;
        } else {
            // Check for /author/[username] pattern
            if ($first === 'author' && isset($segments[1])) {
                $html = self::getAuthor(urldecode($segments[1]));
            } else {
                $html = self::getPage($first) ?? self::getPost($first) ?? self::getCategory($first);
            }
        }

        if ($html === null) {
            return false;
        }

        echo $html;
        return true;
    }

    public static function getPage(string $slug): ?string
    {
        return self::read(self::pathIn('pages', $slug));
    }

    public static function getHome(): ?string
    {
        return self::read(self::pagesDir() . '/' . self::HOME_FILENAME);
    }

    public static function getPost(string $slug): ?string
    {
        return self::read(self::pathIn('posts', $slug));
    }

    public static function getCategory(string $slug): ?string
    {
        return self::read(self::pathIn('categories', $slug));
    }

    public static function getAuthor(string $username): ?string
    {
        return self::read(self::pathIn('authors', $username));
    }

    /**
     * Get cached search results for a query (returns HTML or null).
     */
    public static function getSearch(string $query): ?string
    {
        $hash = self::searchHash($query);
        return self::read(self::searchDir() . '/' . $hash . '.html');
    }

    /**
     * Cache search results HTML along with the normalized terms in the sidecar index.
     */
    public static function putSearch(string $query, string $html, array $terms): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $hash = self::searchHash($query);
        $path = self::searchDir() . '/' . $hash . '.html';
        self::write($path, $html);
        self::addSearchIndexEntry($hash, $terms);
    }

    /**
     * Delete a single cached search result and remove from index.
     */
    public static function deleteSearch(string $query): void
    {
        $hash = self::searchHash($query);
        self::unlink(self::searchDir() . '/' . $hash . '.html');
        self::removeSearchIndexEntry($hash);
    }

    /**
     * Invalidate all cached search queries whose terms match the updated post content.
     */
    public static function invalidateSearchForPost(int $postId): void
    {
        $post = Database::fetch(
            'SELECT title, content, excerpt FROM posts WHERE id = ?',
            [$postId]
        );
        if (!$post) {
            return;
        }

        $content = strtolower(($post['title'] ?? '') . ' ' . ($post['content'] ?? '') . ' ' . ($post['excerpt'] ?? ''));
        if ($content === '') {
            return;
        }

        $index = self::loadSearchIndex();
        if (empty($index)) {
            return;
        }

        $changed = false;
        foreach ($index as $hash => $terms) {
            foreach ($terms as $term) {
                if (stripos($content, $term) !== false) {
                    self::unlink(self::searchDir() . '/' . $hash . '.html');
                    unset($index[$hash]);
                    $changed = true;
                    break;
                }
            }
        }

        if ($changed) {
            self::saveSearchIndex($index);
        }
    }

    public static function putAuthor(string $username, string $html): void
    {
        self::write(self::pathIn('authors', $username), $html);
    }

    public static function deleteAuthor(string $username): void
    {
        self::unlink(self::pathIn('authors', $username));
    }

    public static function putPage(string $slug, string $html): void
    {
        self::write(self::pathIn('pages', $slug), $html);
    }

    public static function putHome(string $html): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::ensureDir(self::pagesDir());
        @file_put_contents(self::pagesDir() . '/' . self::HOME_FILENAME, $html, LOCK_EX);
    }

    public static function putPost(string $slug, string $html): void
    {
        self::write(self::pathIn('posts', $slug), $html);
    }

    public static function putCategory(string $slug, string $html): void
    {
        self::write(self::pathIn('categories', $slug), $html);
    }

    public static function deletePage(string $slug): void
    {
        self::unlink(self::pathIn('pages', $slug));
    }

    public static function deleteHome(): void
    {
        self::unlink(self::pagesDir() . '/' . self::HOME_FILENAME);
    }

    public static function deletePost(string $slug): void
    {
        self::unlink(self::pathIn('posts', $slug));
    }

    public static function deleteCategory(string $slug): void
    {
        self::unlink(self::pathIn('categories', $slug));
    }

    public static function invalidateForPage(int $pageId, ?string $previousSlug = null): void
    {
        if ($previousSlug !== null && $previousSlug !== '') {
            self::deletePage($previousSlug);
        }

        $page = Database::fetch('SELECT slug FROM pages WHERE id = ?', [$pageId]);
        if ($page && !empty($page['slug'])) {
            self::deletePage($page['slug']);
        }

        if (ContentManager::getHomePageId() === $pageId) {
            self::deleteHome();
        }
    }

    public static function invalidateHome(): void
    {
        self::deleteHome();
    }

    public static function invalidateForPost(int $postId, ?string $previousSlug = null): void
    {
        if ($previousSlug !== null && $previousSlug !== '') {
            self::deletePost($previousSlug);
        }

        $post = Database::fetch('SELECT slug FROM posts WHERE id = ?', [$postId]);
        if ($post && !empty($post['slug'])) {
            self::deletePost($post['slug']);
        }

        self::invalidatePostListings();
        self::invalidateCategoriesForPost($postId);
        self::invalidateSearchForPost($postId);
    }

    public static function invalidateForCategory(int $categoryId, ?string $previousSlug = null): void
    {
        if ($previousSlug !== null && $previousSlug !== '') {
            self::deleteCategory($previousSlug);
        }

        $category = Database::fetch('SELECT slug FROM categories WHERE id = ?', [$categoryId]);
        if ($category && !empty($category['slug'])) {
            self::deleteCategory($category['slug']);
        }
    }

    public static function invalidatePostListings(): void
    {
        $postsPage = ContentManager::getPostsPage();
        if ($postsPage && !empty($postsPage['slug'])) {
            self::deletePage($postsPage['slug']);
        }
    }

    public static function invalidateCategoriesForPost(int $postId): void
    {
        $categories = ContentManager::getPostCategories($postId);
        foreach ($categories as $category) {
            if (!empty($category['slug'])) {
                self::deleteCategory($category['slug']);
            }
        }
    }

    public static function clearAll(): void
    {
        foreach (['pages', 'posts', 'categories', 'authors', 'search'] as $namespace) {
            $dir = self::cacheRoot() . '/' . $namespace;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.html') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        self::saveSearchIndex([]);
    }

    public static function getCacheRoot(): string
    {
        return ELEMENTARY_ROOT . '/cache';
    }

    private static function cacheRoot(): string
    {
        return self::getCacheRoot();
    }

    private static function pagesDir(): string
    {
        return self::cacheRoot() . '/pages';
    }

    private static function pathIn(string $namespace, string $slug): string
    {
        return self::cacheRoot() . '/' . $namespace . '/' . self::filenameForSlug($slug);
    }

    private static function filenameForSlug(string $slug): string
    {
        $slug = basename($slug);
        if ($slug === '' || $slug === '.' || $slug === '..') {
            throw new \InvalidArgumentException('Invalid cache slug.');
        }

        return $slug . '.html';
    }

    private static function read(string $path): ?string
    {
        if (!self::isEnabled() || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        return $content !== false ? $content : null;
    }

    private static function write(string $path, string $html): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::ensureDir(dirname($path));
        @file_put_contents($path, $html, LOCK_EX);
    }

    private static function unlink(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    // --- Search cache helpers ---

    private static function searchDir(): string
    {
        return self::cacheRoot() . '/search';
    }

    private static function searchHash(string $query): string
    {
        return md5($query);
    }

    private static function searchIndexPath(): string
    {
        return self::searchDir() . '/_index.json';
    }

    /**
     * Load the sidecar index mapping hash => [terms].
     */
    private static function loadSearchIndex(): array
    {
        $path = self::searchIndexPath();
        if (!is_file($path)) {
            return [];
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save the sidecar index.
     */
    private static function saveSearchIndex(array $index): void
    {
        $path = self::searchIndexPath();
        self::ensureDir(dirname($path));
        @file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Add or update an entry in the search index.
     */
    private static function addSearchIndexEntry(string $hash, array $terms): void
    {
        $index = self::loadSearchIndex();
        $index[$hash] = $terms;
        self::saveSearchIndex($index);
    }

    /**
     * Remove an entry from the search index.
     */
    private static function removeSearchIndexEntry(string $hash): void
    {
        $index = self::loadSearchIndex();
        if (isset($index[$hash])) {
            unset($index[$hash]);
            self::saveSearchIndex($index);
        }
    }
}
