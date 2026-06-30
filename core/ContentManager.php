<?php

declare(strict_types=1);

namespace Elementary;

class ContentManager
{
    public const PAGE_TYPE_STANDARD = 'standard';
    public const PAGE_TYPE_POSTS = 'posts';
    public const POSTS_PAGE_ADMIN_SLUG = '_posts';

    public static function isPostsPage(array|int $page): bool
    {
        if (is_int($page)) {
            $page = self::getPage($page);
            if (!$page) {
                return false;
            }
        }

        return ($page['page_type'] ?? self::PAGE_TYPE_STANDARD) === self::PAGE_TYPE_POSTS;
    }

    public static function getPostsPage(): ?array
    {
        return Database::fetch(
            "SELECT * FROM pages WHERE page_type = ? AND deleted_at IS NULL",
            [self::PAGE_TYPE_POSTS]
        );
    }

    public static function getHomePageId(): ?int
    {
        $homePage = setting('home_page', '');
        $postsPage = self::getPostsPage();

        if ($homePage === 'none' || $homePage === '') {
            return $postsPage ? (int) $postsPage['id'] : null;
        }

        if (!ctype_digit((string) $homePage)) {
            return $postsPage ? (int) $postsPage['id'] : null;
        }

        return (int) $homePage;
    }

    public static function migrateLegacyHomePage(): void
    {
        $homePage = setting('home_page', '');
        if ($homePage !== 'none') {
            return;
        }

        $postsPage = self::getPostsPage();
        if (!$postsPage) {
            return;
        }

        set_setting('home_page', (string) $postsPage['id']);
        self::syncPostsPageSlug();
    }

    public static function isPostsPageHomepage(): bool
    {
        $postsPage = self::getPostsPage();
        if (!$postsPage) {
            return false;
        }

        return self::getHomePageId() === (int) $postsPage['id'];
    }

    public static function getPostsPageSlug(): string
    {
        return self::isPostsPageHomepage() ? '' : 'posts';
    }

    public static function syncPostsPageSlug(): void
    {
        $postsPage = self::getPostsPage();
        if (!$postsPage) {
            return;
        }

        $slug = self::getPostsPageSlug();
        if ($postsPage['slug'] !== $slug) {
            Database::update('pages', [
                'slug'       => $slug,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$postsPage['id']]);
        }
    }

    public static function ensurePostsPage(int $authorId): int
    {
        $existing = self::getPostsPage();
        if ($existing) {
            self::syncPostsPageSlug();
            return (int) $existing['id'];
        }

        $now = date('Y-m-d H:i:s');
        $slug = self::getPostsPageSlug();

        $newId = Database::insert('pages', [
            'title'            => 'Posts',
            'slug'             => $slug,
            'content'          => '',
            'template'         => 'posts',
            'page_type'        => self::PAGE_TYPE_POSTS,
            'meta_keywords'    => '',
            'meta_description' => '',
            'status'           => 'published',
            'author_id'        => $authorId,
            'published_at'     => $now,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        Database::insert('page_versions', [
            'page_id'        => $newId,
            'title'          => 'Posts',
            'content'        => '',
            'template'       => 'posts',
            'version_number' => 1,
            'is_draft'       => 0,
            'created_by'     => $authorId,
            'created_at'     => $now,
        ]);

        Logger::log('page_created', $authorId, 'Posts page ID: ' . $newId);
        return $newId;
    }

    public static function getPages(): array
    {
        return Database::fetchAll(
            'SELECT p.*, COALESCE(u.display_name, u.username) as author_name FROM pages p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.deleted_at IS NULL
             ORDER BY p.updated_at DESC'
        );
    }

    public static function getPage(int $id): ?array
    {
        return Database::fetch('SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public static function getPageBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM pages WHERE slug = ? AND deleted_at IS NULL', [$slug]);
    }

    public static function resolvePageAdminIdentifier(string $identifier): ?array
    {
        if ($identifier === self::POSTS_PAGE_ADMIN_SLUG) {
            return self::getPostsPage();
        }

        return self::getPageBySlug($identifier);
    }

    public static function getPageVersions(int $pageId): array
    {
        return Database::fetchAll(
            'SELECT pv.*, COALESCE(u.display_name, u.username) as author_name FROM page_versions pv
             LEFT JOIN users u ON pv.created_by = u.id
             WHERE pv.page_id = ? ORDER BY pv.version_number DESC',
            [$pageId]
        );
    }

    public static function savePage(array $data, ?int $id, int $userId): array
    {
        $isPostsPage = $id && self::isPostsPage($id);

        if ($isPostsPage) {
            $existing = self::getPage($id);
            if (!$existing) {
                return ['success' => false, 'error' => 'Page not found.'];
            }

            $data['slug'] = self::getPostsPageSlug();
            $data['template'] = 'posts';

            if (self::isPostsPageHomepage()) {
                $data['title'] = $existing['title'];
            }
        } else {
            $slugError = Validator::validateSlug($data['slug'], 'page');
            if ($slugError) {
                return ['success' => false, 'error' => $slugError];
            }
        }

        $existing = Database::fetch('SELECT id FROM pages WHERE slug = ? AND id != ? AND deleted_at IS NULL', [$data['slug'], $id ?? 0]);
        if ($existing) {
            return ['success' => false, 'error' => 'A page with this slug already exists.'];
        }

        $featuredImage = self::validateFeaturedImage($data['featured_image'] ?? null);
        if ($featuredImage === false) {
            return ['success' => false, 'error' => 'Featured image must be an image file.'];
        }
        $data['featured_image'] = $featuredImage;

        $now = date('Y-m-d H:i:s');
        $isDraft = ($data['status'] ?? 'draft') === 'draft';
        $publishedAt = !$isDraft ? ($data['published_at'] ?? $now) : null;

        if ($id) {
            $page = self::getPage($id);
            if (!$page) {
                return ['success' => false, 'error' => 'Page not found.'];
            }

            $previousSlug = $page['slug'] ?? null;

            $versionNum = (int) Database::fetch(
                'SELECT COALESCE(MAX(version_number), 0) + 1 as next FROM page_versions WHERE page_id = ?',
                [$id]
            )['next'];

            Database::insert('page_versions', [
                'page_id'        => $id,
                'title'          => $data['title'],
                'content'        => $data['content'],
                'template'       => $data['template'] ?? 'default',
                'version_number' => $versionNum,
                'is_draft'       => $isDraft ? 1 : 0,
                'created_by'     => $userId,
                'created_at'     => $now,
            ]);

            Database::update('pages', [
                'title'            => $data['title'],
                'slug'             => $data['slug'],
                'content'          => $data['content'],
                'featured_image'   => $data['featured_image'] ?? null,
                'template'         => $data['template'] ?? 'default',
                'meta_keywords'    => $data['meta_keywords'] ?? '',
                'meta_description' => $data['meta_description'] ?? '',
                'status'           => $data['status'] ?? 'draft',
                'published_at'     => $publishedAt,
                'updated_at'       => $now,
            ], 'id = ?', [$id]);

            Logger::log('page_updated', $userId, 'Page ID: ' . $id);
            PageCache::invalidateForPage($id, $previousSlug);
            return ['success' => true, 'id' => $id];
        }

        $newId = Database::insert('pages', [
            'title'            => $data['title'],
            'slug'             => $data['slug'],
            'content'          => $data['content'],
            'featured_image'   => $data['featured_image'] ?? null,
            'template'         => $data['template'] ?? 'default',
            'meta_keywords'    => $data['meta_keywords'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'status'           => $data['status'] ?? 'draft',
            'author_id'        => $userId,
            'published_at'     => $publishedAt,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        Database::insert('page_versions', [
            'page_id'        => $newId,
            'title'          => $data['title'],
            'content'        => $data['content'],
            'template'       => $data['template'] ?? 'default',
            'version_number' => 1,
            'is_draft'       => $isDraft ? 1 : 0,
            'created_by'     => $userId,
            'created_at'     => $now,
        ]);

        Logger::log('page_created', $userId, 'Page ID: ' . $newId);
        PageCache::invalidateForPage($newId);
        return ['success' => true, 'id' => $newId];
    }

    public static function deletePage(int $id): bool
    {
        if (self::isPostsPage($id)) {
            return false;
        }

        $page = self::getPage($id);
        if ($page) {
            PageCache::invalidateForPage($id, $page['slug'] ?? null);
        }

        $now = date('Y-m-d H:i:s');
        Database::update('pages', ['deleted_at' => $now], 'id = ?', [$id]);
        Logger::log('page_deleted', Auth::user()['id'] ?? null, 'Page ID: ' . $id);
        return true;
    }

    public static function restorePage(int $id): void
    {
        Database::update('pages', ['deleted_at' => null], 'id = ?', [$id]);
        PageCache::invalidateForPage($id);
        Logger::log('page_restored', Auth::user()['id'] ?? null, 'Page ID: ' . $id);
    }

    public static function permanentlyDeletePage(int $id): bool
    {
        if (self::isPostsPage($id)) {
            return false;
        }

        $page = Database::fetch('SELECT slug FROM pages WHERE id = ?', [$id]);
        if ($page) {
            PageCache::invalidateForPage($id, $page['slug'] ?? null);
        }

        Database::delete('pages', 'id = ?', [$id]);
        Logger::log('page_permanently_deleted', Auth::user()['id'] ?? null, 'Page ID: ' . $id);
        return true;
    }

    public static function emptyRecycleBin(): void
    {
        FileManager::permanentlyDeleteAllDeleted();
        Database::query("DELETE FROM pages WHERE deleted_at IS NOT NULL AND page_type != '" . self::PAGE_TYPE_POSTS . "'");
        Database::query('DELETE FROM posts WHERE deleted_at IS NOT NULL');
        PageCache::clearAll();
        Logger::log('recycle_bin_emptied', Auth::user()['id'] ?? null, 'All items permanently deleted.');
    }

    public static function restorePageVersion(int $pageId, int $versionId, int $userId): array
    {
        $version = Database::fetch('SELECT * FROM page_versions WHERE id = ? AND page_id = ?', [$versionId, $pageId]);
        if (!$version) {
            return ['success' => false, 'error' => 'Version not found.'];
        }

        return self::savePage([
            'title'    => $version['title'],
            'slug'     => self::getPage($pageId)['slug'],
            'content'  => $version['content'],
            'template' => $version['template'],
            'status'   => self::getPage($pageId)['status'],
        ], $pageId, $userId);
    }

    public static function getPosts(?int $authorId = null): array
    {
        if ($authorId) {
            return Database::fetchAll(
                'SELECT p.*, COALESCE(u.display_name, u.username) as author_name FROM posts p
                 LEFT JOIN users u ON p.author_id = u.id
                 WHERE p.author_id = ? AND p.deleted_at IS NULL ORDER BY p.updated_at DESC',
                [$authorId]
            );
        }
        return Database::fetchAll(
            'SELECT p.*, COALESCE(u.display_name, u.username) as author_name FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.deleted_at IS NULL ORDER BY p.updated_at DESC'
        );
    }

    public static function getPost(int $id): ?array
    {
        return Database::fetch('SELECT * FROM posts WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public static function getPostBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM posts WHERE slug = ? AND deleted_at IS NULL', [$slug]);
    }

    public static function resolvePostAdminIdentifier(string $identifier): ?array
    {
        return self::getPostBySlug($identifier);
    }

    public static function getPostCategories(int $postId): array
    {
        return Database::fetchAll(
            'SELECT c.* FROM categories c
             INNER JOIN post_categories pc ON c.id = pc.category_id
             WHERE pc.post_id = ?',
            [$postId]
        );
    }

    public static function getPostVersions(int $postId): array
    {
        return Database::fetchAll(
            'SELECT pv.*, COALESCE(u.display_name, u.username) as author_name FROM post_versions pv
             LEFT JOIN users u ON pv.created_by = u.id
             WHERE pv.post_id = ? ORDER BY pv.version_number DESC',
            [$postId]
        );
    }

    public static function savePost(array $data, ?int $id, int $userId): array
    {
        $data['content'] = HtmlSanitizer::sanitize($data['content'] ?? '');

        $slugError = Validator::validateSlug($data['slug'], 'post');
        if ($slugError) {
            return ['success' => false, 'error' => $slugError];
        }

        $existing = Database::fetch('SELECT id FROM posts WHERE slug = ? AND id != ? AND deleted_at IS NULL', [$data['slug'], $id ?? 0]);
        if ($existing) {
            return ['success' => false, 'error' => 'A post with this slug already exists.'];
        }

        $categories = $data['categories'] ?? [];
        if (empty($categories)) {
            $uncategorized = Database::fetch('SELECT id FROM categories WHERE slug = ?', ['uncategorized']);
            $categories = $uncategorized ? [$uncategorized['id']] : [];
        }

        $featuredImage = self::validateFeaturedImage($data['featured_image'] ?? null);
        if ($featuredImage === false) {
            return ['success' => false, 'error' => 'Featured image must be an image file.'];
        }
        $data['featured_image'] = $featuredImage;

        $now = date('Y-m-d H:i:s');
        $isDraft = ($data['status'] ?? 'draft') === 'draft';
        $publishedAt = !$isDraft ? ($data['published_at'] ?? $now) : null;

        if ($id) {
            $existingPost = self::getPost($id);
            $previousSlug = $existingPost['slug'] ?? null;
            $previousCategories = self::getPostCategories($id);

            $versionNum = (int) Database::fetch(
                'SELECT COALESCE(MAX(version_number), 0) + 1 as next FROM post_versions WHERE post_id = ?',
                [$id]
            )['next'];

            Database::insert('post_versions', [
                'post_id'        => $id,
                'title'          => $data['title'],
                'content'        => $data['content'],
                'excerpt'        => $data['excerpt'] ?? '',
                'version_number' => $versionNum,
                'is_draft'       => $isDraft ? 1 : 0,
                'created_by'     => $userId,
                'created_at'     => $now,
            ]);

            Database::update('posts', [
                'title'            => $data['title'],
                'slug'             => $data['slug'],
                'content'          => $data['content'],
                'excerpt'          => $data['excerpt'] ?? '',
                'featured_image'   => $data['featured_image'] ?? null,
                'meta_keywords'    => $data['meta_keywords'] ?? '',
                'meta_description' => $data['meta_description'] ?? '',
                'status'           => $data['status'] ?? 'draft',
                'published_at'     => $publishedAt,
                'updated_at'       => $now,
            ], 'id = ?', [$id]);

            self::syncPostCategories($id, $categories);
            self::syncPostTags($id, $data['tags'] ?? '');
            Logger::log('post_updated', $userId, 'Post ID: ' . $id);
            HtmlCache::invalidateForPost($id, $previousSlug);
            foreach ($previousCategories as $category) {
                if (!empty($category['slug'])) {
                    HtmlCache::deleteCategory($category['slug']);
                }
            }
            return ['success' => true, 'id' => $id];
        }

        $newId = Database::insert('posts', [
            'title'            => $data['title'],
            'slug'             => $data['slug'],
            'content'          => $data['content'],
            'excerpt'          => $data['excerpt'] ?? '',
            'featured_image'   => $data['featured_image'] ?? null,
            'meta_keywords'    => $data['meta_keywords'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'status'           => $data['status'] ?? 'draft',
            'author_id'        => $userId,
            'published_at'     => $publishedAt,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        Database::insert('post_versions', [
            'post_id'        => $newId,
            'title'          => $data['title'],
            'content'        => $data['content'],
            'excerpt'        => $data['excerpt'] ?? '',
            'version_number' => 1,
            'is_draft'       => $isDraft ? 1 : 0,
            'created_by'     => $userId,
            'created_at'     => $now,
        ]);

        self::syncPostCategories($newId, $categories);
        self::syncPostTags($newId, $data['tags'] ?? '');
        Logger::log('post_created', $userId, 'Post ID: ' . $newId);
        HtmlCache::invalidateForPost($newId);
        return ['success' => true, 'id' => $newId];
    }

    private static function syncPostCategories(int $postId, array $categoryIds): void
    {
        Database::delete('post_categories', 'post_id = ?', [$postId]);
        foreach ($categoryIds as $catId) {
            Database::insert('post_categories', [
                'post_id'     => $postId,
                'category_id' => (int) $catId,
            ]);
        }
    }

    public static function getTags(): array
    {
        return Database::fetchAll(
            'SELECT t.*, COUNT(pt.post_id) as post_count FROM tags t
             LEFT JOIN post_tags pt ON t.id = pt.tag_id
             GROUP BY t.id
             ORDER BY t.name ASC'
        );
    }

    public static function getTagBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM tags WHERE slug = ?', [$slug]);
    }

    public static function getTagByName(string $name): ?array
    {
        return Database::fetch('SELECT * FROM tags WHERE name = ?', [$name]);
    }

    public static function resolveTagAdminIdentifier(string $identifier): ?array
    {
        return self::getTagBySlug($identifier);
    }

    public static function getPostTags(int $postId): array
    {
        return Database::fetchAll(
            'SELECT t.* FROM tags t
             INNER JOIN post_tags pt ON t.id = pt.tag_id
             WHERE pt.post_id = ?',
            [$postId]
        );
    }

    public static function saveTag(array $data, ?int $id): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'error' => 'Tag name is required.'];
        }

        $slug = Validator::slug($name);
        $slugError = Validator::validateSlug($slug, 'tag');
        if ($slugError) {
            return ['success' => false, 'error' => $slugError];
        }

        $existing = Database::fetch('SELECT id FROM tags WHERE slug = ? AND id != ?', [$slug, $id ?? 0]);
        if ($existing) {
            return ['success' => false, 'error' => 'A tag with this name already exists.'];
        }

        $now = date('Y-m-d H:i:s');
        $userId = Auth::user()['id'] ?? null;

        if ($id) {
            Database::update('tags', [
                'name'       => $name,
                'slug'       => $slug,
                'updated_at' => $now,
            ], 'id = ?', [$id]);
            Logger::log('tag_updated', $userId, 'Tag ID: ' . $id);
            return ['success' => true, 'id' => $id, 'slug' => $slug];
        }

        $newId = Database::insert('tags', [
            'name'       => $name,
            'slug'       => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Logger::log('tag_created', $userId, 'Tag ID: ' . $newId);
        return ['success' => true, 'id' => $newId, 'slug' => $slug];
    }

    public static function deleteTag(int $id): array
    {
        $postCount = (int) Database::fetch('SELECT COUNT(*) as cnt FROM post_tags WHERE tag_id = ?', [$id])['cnt'];
        if ($postCount > 0) {
            return ['success' => false, 'error' => __t('tag_cannot_delete_has_posts')];
        }

        Database::delete('tags', 'id = ?', [$id]);
        Logger::log('tag_deleted', Auth::user()['id'] ?? null, 'Tag ID: ' . $id);
        return ['success' => true];
    }

    /**
     * Ensure a tag exists by name, creating it if necessary. Returns the tag ID.
     */
    public static function ensureTag(string $name): int
    {
        $name = trim($name);
        $existing = self::getTagByName($name);
        if ($existing) {
            return (int) $existing['id'];
        }

        $result = self::saveTag(['name' => $name], null);
        return $result['success'] ? (int) $result['id'] : 0;
    }

    private static function syncPostTags(int $postId, string $tagsString): void
    {
        Database::delete('post_tags', 'post_id = ?', [$postId]);

        if (trim($tagsString) === '') {
            return;
        }

        $tagNames = array_filter(array_map('trim', explode(',', $tagsString)));
        foreach ($tagNames as $tagName) {
            if ($tagName === '') {
                continue;
            }
            $tagId = self::ensureTag($tagName);
            if ($tagId) {
                Database::insert('post_tags', [
                    'post_id' => $postId,
                    'tag_id'  => $tagId,
                ]);
            }
        }
    }

    public static function deletePost(int $id): void
    {
        $post = Database::fetch('SELECT slug FROM posts WHERE id = ?', [$id]);
        if ($post) {
            HtmlCache::invalidateForPost($id, $post['slug'] ?? null);
        }

        $now = date('Y-m-d H:i:s');
        Database::update('posts', ['deleted_at' => $now], 'id = ?', [$id]);
        Logger::log('post_deleted', Auth::user()['id'] ?? null, 'Post ID: ' . $id);
    }

    public static function restorePost(int $id): void
    {
        Database::update('posts', ['deleted_at' => null], 'id = ?', [$id]);
        HtmlCache::invalidateForPost($id);
        Logger::log('post_restored', Auth::user()['id'] ?? null, 'Post ID: ' . $id);
    }

    public static function permanentlyDeletePost(int $id): void
    {
        $post = Database::fetch('SELECT slug FROM posts WHERE id = ?', [$id]);
        if ($post) {
            HtmlCache::invalidateForPost($id, $post['slug'] ?? null);
        }

        Database::delete('posts', 'id = ?', [$id]);
        Logger::log('post_permanently_deleted', Auth::user()['id'] ?? null, 'Post ID: ' . $id);
    }

    public static function restorePostVersion(int $postId, int $versionId, int $userId): array
    {
        $version = Database::fetch('SELECT * FROM post_versions WHERE id = ? AND post_id = ?', [$versionId, $postId]);
        if (!$version) {
            return ['success' => false, 'error' => 'Version not found.'];
        }

        $post = self::getPost($postId);
        return self::savePost([
            'title'      => $version['title'],
            'slug'       => $post['slug'],
            'content'    => $version['content'],
            'excerpt'    => $version['excerpt'],
            'status'     => $post['status'],
            'categories' => array_column(self::getPostCategories($postId), 'id'),
        ], $postId, $userId);
    }

    public static function getCategories(): array
    {
        return Database::fetchAll('SELECT * FROM categories ORDER BY name ASC');
    }

    public static function getCategoryBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public static function resolveCategoryAdminIdentifier(string $identifier): ?array
    {
        return self::getCategoryBySlug($identifier);
    }

    public static function saveCategory(array $data, ?int $id): array
    {
        // Prevent editing the default "Uncategorized" category
        if ($id) {
            $category = Database::fetch('SELECT slug FROM categories WHERE id = ?', [$id]);
            if ($category && $category['slug'] === 'uncategorized') {
                return ['success' => false, 'error' => 'The Uncategorized category cannot be edited.'];
            }
            $previousSlug = $category['slug'] ?? null;
        } else {
            $previousSlug = null;
        }

        $slug = Validator::slug($data['name']);
        $slugError = Validator::validateSlug($slug, 'category');
        if ($slugError) {
            return ['success' => false, 'error' => $slugError];
        }

        $existing = Database::fetch('SELECT id FROM categories WHERE slug = ? AND id != ?', [$slug, $id ?? 0]);
        if ($existing) {
            return ['success' => false, 'error' => 'A category with this slug already exists.'];
        }

        $featuredImage = self::validateFeaturedImage($data['featured_image'] ?? null);
        if ($featuredImage === false) {
            return ['success' => false, 'error' => 'Featured image must be an image file.'];
        }
        $data['featured_image'] = $featuredImage;

        $now = date('Y-m-d H:i:s');
        $userId = Auth::user()['id'] ?? null;

        if ($id) {
            Database::update('categories', [
                'name'             => $data['name'],
                'slug'             => $slug,
                'description'      => $data['description'] ?? '',
                'featured_image'   => $data['featured_image'] ?? null,
                'meta_keywords'    => $data['meta_keywords'] ?? '',
                'meta_description' => $data['meta_description'] ?? '',
                'updated_at'       => $now,
            ], 'id = ?', [$id]);
            Logger::log('category_updated', $userId, 'Category ID: ' . $id);
            HtmlCache::invalidateForCategory($id, $previousSlug);
            return ['success' => true, 'id' => $id, 'slug' => $slug];
        }

        $newId = Database::insert('categories', [
            'name'             => $data['name'],
            'slug'             => $slug,
            'description'      => $data['description'] ?? '',
            'featured_image'   => $data['featured_image'] ?? null,
            'meta_keywords'    => $data['meta_keywords'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        Logger::log('category_created', $userId, 'Category ID: ' . $newId);
        HtmlCache::invalidateForCategory($newId, null);
        return ['success' => true, 'id' => $newId, 'slug' => $slug];
    }

    public static function deleteCategory(int $id): array
    {
        // Prevent deletion of the default "Uncategorized" category
        $category = Database::fetch('SELECT * FROM categories WHERE id = ?', [$id]);
        if ($category && $category['slug'] === 'uncategorized') {
            return ['success' => false, 'error' => 'The Uncategorized category cannot be deleted.'];
        }

        // Prevent deletion of categories that have posts assigned
        $postCount = (int) Database::fetch('SELECT COUNT(*) as cnt FROM post_categories WHERE category_id = ?', [$id])['cnt'];
        if ($postCount > 0) {
            return ['success' => false, 'error' => 'This category cannot be deleted because it has posts assigned to it.'];
        }

        Database::delete('categories', 'id = ?', [$id]);
        HtmlCache::invalidateForCategory($id, $category['slug'] ?? null);
        Logger::log('category_deleted', Auth::user()['id'] ?? null, 'Category ID: ' . $id);
        return ['success' => true];
    }

    public static function getDeletedPages(): array
    {
        return Database::fetchAll(
            'SELECT p.*, COALESCE(u.display_name, u.username) as author_name FROM pages p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.deleted_at IS NOT NULL
             ORDER BY p.deleted_at DESC'
        );
    }

    public static function getDeletedPosts(): array
    {
        return Database::fetchAll(
            'SELECT p.*, COALESCE(u.display_name, u.username) as author_name FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.deleted_at IS NOT NULL
             ORDER BY p.deleted_at DESC'
        );
    }

    public static function getDeletedPage(int $id): ?array
    {
        return Database::fetch('SELECT * FROM pages WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
    }

    public static function getDeletedPost(int $id): ?array
    {
        return Database::fetch('SELECT * FROM posts WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
    }

    /**
     * Get the full page builder structure for a page.
     * Returns nested array: sections > rows > columns > blocks.
     */
    public static function getPageStructure(int $pageId): array
    {
        $sections = Database::fetchAll(
            'SELECT * FROM page_sections WHERE page_id = ? ORDER BY sort_order ASC',
            [$pageId]
        );

        $structure = [];
        foreach ($sections as $section) {
            $sectionId = (int) $section['id'];
            $rows = Database::fetchAll(
                'SELECT * FROM page_rows WHERE section_id = ? ORDER BY sort_order ASC',
                [$sectionId]
            );

            $sectionRows = [];
            foreach ($rows as $row) {
                $rowId = (int) $row['id'];
                $columns = Database::fetchAll(
                    'SELECT * FROM page_columns WHERE row_id = ? ORDER BY sort_order ASC',
                    [$rowId]
                );

                $sectionColumns = [];
                foreach ($columns as $column) {
                    $columnId = (int) $column['id'];
                    $blocks = Database::fetchAll(
                        'SELECT * FROM page_blocks WHERE column_id = ? ORDER BY sort_order ASC',
                        [$columnId]
                    );

                    $parsedBlocks = [];
                    foreach ($blocks as $block) {
                        $blockData = $block['block_data'] ? json_decode($block['block_data'], true) : null;
                        $parsedBlocks[] = [
                            'id'         => (int) $block['id'],
                            'sort_order' => (int) $block['sort_order'],
                            'css_class'  => $block['css_class'] ?? '',
                            'css_id'     => $block['css_id'] ?? '',
                            'inline_css' => $block['inline_css'] ?? '',
                            'block_type' => $block['block_type'],
                            'block_data' => $blockData,
                        ];
                    }

                    $sectionColumns[] = [
                        'id'         => (int) $column['id'],
                        'sort_order' => (int) $column['sort_order'],
                        'width'      => (float) $column['width'],
                        'css_class'  => $column['css_class'] ?? '',
                        'css_id'     => $column['css_id'] ?? '',
                        'inline_css' => $column['inline_css'] ?? '',
                        'blocks'     => $parsedBlocks,
                    ];
                }

                $sectionRows[] = [
                    'id'         => (int) $row['id'],
                    'sort_order' => (int) $row['sort_order'],
                    'css_class'  => $row['css_class'] ?? '',
                    'css_id'     => $row['css_id'] ?? '',
                    'inline_css' => $row['inline_css'] ?? '',
                    'columns'    => $sectionColumns,
                ];
            }

            $structure[] = [
                'id'         => $sectionId,
                'sort_order' => (int) $section['sort_order'],
                'css_class'  => $section['css_class'] ?? '',
                'css_id'     => $section['css_id'] ?? '',
                'inline_css' => $section['inline_css'] ?? '',
                'rows'       => $sectionRows,
            ];
        }

        return $structure;
    }

    /**
     * Save the full page builder structure from a JSON array.
     * Handles creating/updating/deleting sections, rows, columns, and blocks.
     */
    public static function savePageStructure(int $pageId, array $structure): void
    {
        $now = date('Y-m-d H:i:s');

        // Get existing IDs to detect deletions
        $existingSections = array_column(
            Database::fetchAll('SELECT id FROM page_sections WHERE page_id = ?', [$pageId]),
            'id'
        );

        $existingRows = [];
        if (!empty($existingSections)) {
            $existingRows = array_column(
                Database::fetchAll('SELECT id FROM page_rows WHERE section_id IN (' . implode(',', array_map('intval', $existingSections)) . ')', []),
                'id'
            );
        }

        $existingColumns = [];
        if (!empty($existingRows)) {
            $existingColumns = array_column(
                Database::fetchAll('SELECT id FROM page_columns WHERE row_id IN (' . implode(',', array_map('intval', $existingRows)) . ')', []),
                'id'
            );
        }

        $existingBlocks = [];
        if (!empty($existingColumns)) {
            $existingBlocks = array_column(
                Database::fetchAll('SELECT id FROM page_blocks WHERE column_id IN (' . implode(',', array_map('intval', $existingColumns)) . ')', []),
                'id'
            );
        }

        $keptSectionIds = [];
        $keptRowIds = [];
        $keptColumnIds = [];
        $keptBlockIds = [];

        foreach ($structure['sections'] ?? [] as $sectionData) {
            $sectionId = $sectionData['id'] ?? null;

            if ($sectionId) {
                Database::update('page_sections', [
                    'sort_order' => (int) $sectionData['sort_order'],
                    'css_class'  => trim($sectionData['css_class'] ?? ''),
                    'css_id'     => trim($sectionData['css_id'] ?? ''),
                    'inline_css' => trim($sectionData['inline_css'] ?? '') ?: null,
                    'updated_at' => $now,
                ], 'id = ?', [(int) $sectionId]);
                $keptSectionIds[] = (int) $sectionId;
            } else {
                $sectionId = Database::insert('page_sections', [
                    'page_id'    => $pageId,
                    'sort_order' => (int) $sectionData['sort_order'],
                    'css_class'  => trim($sectionData['css_class'] ?? ''),
                    'css_id'     => trim($sectionData['css_id'] ?? ''),
                    'inline_css' => trim($sectionData['inline_css'] ?? '') ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $keptSectionIds[] = $sectionId;
                $sectionData['id'] = $sectionId;
            }

            foreach ($sectionData['rows'] ?? [] as $rowData) {
                $rowId = $rowData['id'] ?? null;

                if ($rowId) {
                    Database::update('page_rows', [
                        'section_id' => $sectionId,
                        'sort_order' => (int) $rowData['sort_order'],
                        'css_class'  => trim($rowData['css_class'] ?? ''),
                        'css_id'     => trim($rowData['css_id'] ?? ''),
                        'inline_css' => trim($rowData['inline_css'] ?? '') ?: null,
                        'updated_at' => $now,
                    ], 'id = ?', [(int) $rowId]);
                    $keptRowIds[] = (int) $rowId;
                } else {
                    $rowId = Database::insert('page_rows', [
                        'section_id' => $sectionId,
                        'sort_order' => (int) $rowData['sort_order'],
                        'css_class'  => trim($rowData['css_class'] ?? ''),
                        'css_id'     => trim($rowData['css_id'] ?? ''),
                        'inline_css' => trim($rowData['inline_css'] ?? '') ?: null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $keptRowIds[] = $rowId;
                    $rowData['id'] = $rowId;
                }

                foreach ($rowData['columns'] ?? [] as $colData) {
                    $colId = $colData['id'] ?? null;

                    if ($colId) {
                        Database::update('page_columns', [
                            'row_id'     => $rowId,
                            'sort_order' => (int) $colData['sort_order'],
                            'width'      => (float) $colData['width'],
                            'css_class'  => trim($colData['css_class'] ?? ''),
                            'css_id'     => trim($colData['css_id'] ?? ''),
                            'inline_css' => trim($colData['inline_css'] ?? '') ?: null,
                            'updated_at' => $now,
                        ], 'id = ?', [(int) $colId]);
                        $keptColumnIds[] = (int) $colId;
                    } else {
                        $colId = Database::insert('page_columns', [
                            'row_id'     => $rowId,
                            'sort_order' => (int) $colData['sort_order'],
                            'width'      => (float) $colData['width'],
                            'css_class'  => trim($colData['css_class'] ?? ''),
                            'css_id'     => trim($colData['css_id'] ?? ''),
                            'inline_css' => trim($colData['inline_css'] ?? '') ?: null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $keptColumnIds[] = $colId;
                        $colData['id'] = $colId;
                    }

                    foreach ($colData['blocks'] ?? [] as $blockData) {
                        if (($blockData['block_type'] ?? '') === 'image') {
                            $data = $blockData['block_data'] ?? [];
                            $source = ($data['source'] ?? '') === 'url' || (empty($data['filename']) && !empty($data['url']))
                                ? 'url'
                                : 'file';

                            if ($source === 'url') {
                                $url = trim((string) ($data['url'] ?? ''));
                                if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                                    $url = '';
                                }
                                $blockData['block_data'] = [
                                    'source'   => 'url',
                                    'url'      => $url,
                                    'filename' => '',
                                ];
                            } else {
                                $imageFilename = $data['filename'] ?? null;
                                if ($imageFilename) {
                                    $validated = self::validateFeaturedImage($imageFilename);
                                    $imageFilename = $validated === false ? '' : $validated;
                                } else {
                                    $imageFilename = '';
                                }
                                $blockData['block_data'] = [
                                    'source'   => 'file',
                                    'filename' => $imageFilename,
                                    'url'      => '',
                                ];
                            }
                        }

                        if (($blockData['block_type'] ?? '') === 'video') {
                            $data = $blockData['block_data'] ?? [];
                            $source = ($data['source'] ?? '') === 'embed' || (empty($data['filename']) && !empty($data['url']))
                                ? 'embed'
                                : 'file';

                            if ($source === 'embed') {
                                $url = trim((string) ($data['url'] ?? ''));
                                if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                                    $url = '';
                                }
                                $blockData['block_data'] = [
                                    'source'   => 'embed',
                                    'url'      => $url,
                                    'filename' => '',
                                ];
                            } else {
                                $videoFilename = $data['filename'] ?? null;
                                if ($videoFilename) {
                                    $validated = self::validateVideoFile($videoFilename);
                                    $videoFilename = $validated === false ? '' : $validated;
                                } else {
                                    $videoFilename = '';
                                }
                                $blockData['block_data'] = [
                                    'source'   => 'file',
                                    'filename' => $videoFilename,
                                    'url'      => '',
                                ];
                            }
                        }

                        if (($blockData['block_type'] ?? '') === 'text') {
                            $text = (string) (($blockData['block_data'] ?? [])['text'] ?? '');
                            $blockData['block_data'] = [
                                'text' => HtmlSanitizer::sanitize($text),
                            ];
                        }

                        $blockId = $blockData['id'] ?? null;
                        $dataJson = json_encode($blockData['block_data'] ?? []);

                        if ($blockId) {
                            Database::update('page_blocks', [
                                'column_id'  => $colId,
                                'sort_order' => (int) $blockData['sort_order'],
                                'css_class'  => trim($blockData['css_class'] ?? ''),
                                'css_id'     => trim($blockData['css_id'] ?? ''),
                                'inline_css' => trim($blockData['inline_css'] ?? '') ?: null,
                                'block_type' => $blockData['block_type'],
                                'block_data' => $dataJson,
                                'updated_at' => $now,
                            ], 'id = ?', [(int) $blockId]);
                            $keptBlockIds[] = (int) $blockId;
                        } else {
                            $blockId = Database::insert('page_blocks', [
                                'column_id'  => $colId,
                                'sort_order' => (int) $blockData['sort_order'],
                                'css_class'  => trim($blockData['css_class'] ?? ''),
                                'css_id'     => trim($blockData['css_id'] ?? ''),
                                'inline_css' => trim($blockData['inline_css'] ?? '') ?: null,
                                'block_type' => $blockData['block_type'],
                                'block_data' => $dataJson,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                            $keptBlockIds[] = $blockId;
                        }
                    }
                }
            }
        }

        // Delete removed items (cascade handles children)
        $deletedSections = array_diff($existingSections, $keptSectionIds);
        foreach ($deletedSections as $sid) {
            Database::delete('page_sections', 'id = ?', [(int) $sid]);
        }

        PageCache::invalidateForPage($pageId);
    }

    /**
     * Delete all page builder structure for a page.
     * (Also handled by FK cascade when deleting the page itself.)
     */
    public static function deletePageStructure(int $pageId): void
    {
        Database::delete('page_sections', 'page_id = ?', [$pageId]);
    }

    /**
     * Generate a unique ID for new builder items (uses current timestamp + random).
     */
    public static function generateBuilderId(): int
    {
        return (int) (microtime(true) * 1000) % 2147483647;
    }

    /**
     * @return string|null|false Null when empty, false when invalid
     */
    private static function validateFeaturedImage(?string $filename): string|null|false
    {
        if ($filename === null || $filename === '') {
            return null;
        }

        $file = Database::fetch(
            'SELECT mime_type FROM files WHERE filename = ? AND deleted_at IS NULL',
            [$filename]
        );

        if (!$file || !str_starts_with($file['mime_type'] ?? '', 'image/')) {
            return false;
        }

        return $filename;
    }

    /**
     * @return string|null|false Null when empty, false when invalid
     */
    private static function validateVideoFile(?string $filename): string|null|false
    {
        if ($filename === null || $filename === '') {
            return null;
        }

        $file = Database::fetch(
            'SELECT mime_type FROM files WHERE filename = ? AND deleted_at IS NULL',
            [$filename]
        );

        if (!$file || !VideoEmbed::isVideoMimeType($file['mime_type'] ?? '')) {
            return false;
        }

        return $filename;
    }
}
