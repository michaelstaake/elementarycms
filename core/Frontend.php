<?php

declare(strict_types=1);

namespace Elementary;

class Frontend
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function dispatch(): void
    {
        $segments = $this->router->getSegments();
        $template = new Template();

        if (empty($segments)) {
            $this->renderHome($template);
            return;
        }

        $slug = $segments[0];

        if (HtmlCache::isEnabled()) {
            $cached = HtmlCache::getPage($slug)
                ?? HtmlCache::getPost($slug)
                ?? HtmlCache::getCategory($slug);
            if ($cached !== null) {
                echo $cached;
                return;
            }
        }

        $page = Database::fetch(
            "SELECT p.*, COALESCE(u.display_name, u.username) as author_name FROM pages p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.slug = ? AND p.status = 'published' AND p.deleted_at IS NULL",
            [$slug]
        );
        if ($page) {
            $this->renderPage($template, $page);
            return;
        }

        $post = Database::fetch(
            "SELECT p.*, COALESCE(u.display_name, u.username) as author_name, u.username as author_username FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.slug = ? AND p.status = 'published' AND p.deleted_at IS NULL",
            [$slug]
        );
        if ($post) {
            $this->renderPost($template, $post);
            return;
        }

        // Check for /search page (search form & results)
        if ($slug === 'search') {
            $query = trim($_GET['q'] ?? '');
            if ($query !== '') {
                $this->renderSearchResults($template, $query);
                return;
            }
            $this->renderSearchForm($template);
            return;
        }

        // Check for /author/[username] pattern
        if ($slug === 'author' && isset($segments[1])) {
            $authorUsername = urldecode($segments[1]);
            $author = Database::fetch(
                "SELECT id, username, display_name, status FROM users WHERE username = ?",
                [$authorUsername]
            );
            if ($author) {
                $this->renderAuthor($template, $author);
                return;
            }
        }

        $category = Database::fetch(
            "SELECT * FROM categories WHERE slug = ?",
            [$slug]
        );
        if ($category) {
            $this->renderCategory($template, $category);
            return;
        }

        throw new \RuntimeException('Page not found', 404);
    }

    private function renderHome(Template $template): void
    {
        if (HtmlCache::isEnabled()) {
            $cached = HtmlCache::getHome();
            if ($cached !== null) {
                echo $cached;
                return;
            }
        }

        $homePageId = ContentManager::getHomePageId();
        if (!$homePageId) {
            throw new \RuntimeException('Page not found', 404);
        }

        $page = Database::fetch(
            "SELECT p.*, COALESCE(u.display_name, u.username) as author_name FROM pages p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.id = ? AND p.status = 'published' AND p.deleted_at IS NULL",
            [$homePageId]
        );
        if ($page) {
            $this->renderPage($template, $page, true);
            return;
        }

        throw new \RuntimeException('Page not found', 404);
    }

    private function renderPostsPage(Template $template, array $page, bool $isHome = false): void
    {
        if (HtmlCache::isEnabled()) {
            $cached = ($page['slug'] ?? '') !== ''
                ? HtmlCache::getPage($page['slug'])
                : HtmlCache::getHome();
            if ($cached !== null) {
                echo $cached;
                return;
            }
        }

        $posts = Database::fetchAll(
            "SELECT p.*, COALESCE(u.display_name, u.username) as author_name, u.username as author_username FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.status = 'published' AND p.deleted_at IS NULL
             ORDER BY p.published_at DESC
             LIMIT 20"
        );

        self::addFeaturedImageUrls($posts);
        self::addPostCategories($posts);

        $template->setData(array_merge([
            'site_name'        => Config::get('site_name'),
            'site_url'         => Config::get('site_url'),
            'page'             => $page,
            'posts'            => $posts,
            'title'            => $page['title'],
            'meta_keywords'    => $page['meta_keywords'] ?? '',
            'meta_description' => $page['meta_description'] ?? '',
        ], $this->homePageTemplateData($isHome, $page)));

        $shouldCache = HtmlCache::isEnabled() && ($page['status'] ?? '') === 'published';

        $this->renderCached($template, 'posts', $shouldCache, function (string $html) use ($page): void {
            if (($page['slug'] ?? '') !== '') {
                HtmlCache::putPage($page['slug'], $html);
            } else {
                HtmlCache::putHome($html);
            }
        });
    }

    private function renderPage(Template $template, array $page, bool $isHome = false): void
    {
        if (ContentManager::isPostsPage($page)) {
            $this->renderPostsPage($template, $page, $isHome);
            return;
        }

        $pageTemplate = $page['template'] ?? 'default';
        if ($pageTemplate === 'default') {
            $pageTemplate = 'page';
        } elseif ($pageTemplate !== 'page') {
            $pageTemplate = 'page-' . $pageTemplate;
        }

        $pageStructure = ContentManager::getPageStructure((int) $page['id']);

        if (!empty($page['featured_image'])) {
            $page['featured_image_url'] = file_url($page['featured_image']);
        }

        $template->setData(array_merge([
            'site_name'        => Config::get('site_name'),
            'site_url'         => Config::get('site_url'),
            'page'             => $page,
            'page_structure'   => $pageStructure,
            'title'            => $page['title'],
            'meta_keywords'    => $page['meta_keywords'] ?? '',
            'meta_description' => $page['meta_description'] ?? '',
        ], $this->homePageTemplateData($isHome, $page)));

        $shouldCache = HtmlCache::isEnabled() && ($page['status'] ?? '') === 'published';

        $this->renderCached($template, $pageTemplate, $shouldCache, function (string $html) use ($page, $isHome): void {
            HtmlCache::putPage($page['slug'], $html);
            if ($isHome || ContentManager::getHomePageId() === (int) $page['id']) {
                HtmlCache::putHome($html);
            }
        });
    }

    private function renderPost(Template $template, array $post): void
    {
        $categories = Database::fetchAll(
            "SELECT c.* FROM categories c
             INNER JOIN post_categories pc ON c.id = pc.category_id
             WHERE pc.post_id = ?",
            [$post['id']]
        );

        if (!empty($post['featured_image'])) {
            $post['featured_image_url'] = file_url($post['featured_image']);
        }

        $template->setData([
            'site_name'        => Config::get('site_name'),
            'site_url'         => Config::get('site_url'),
            'post'             => $post,
            'categories'       => $categories,
            'title'            => $post['title'],
            'meta_keywords'    => $post['meta_keywords'] ?? '',
            'meta_description' => $post['meta_description'] ?? '',
        ]);

        $shouldCache = HtmlCache::isEnabled() && ($post['status'] ?? '') === 'published';

        $this->renderCached($template, 'post', $shouldCache, function (string $html) use ($post): void {
            HtmlCache::putPost($post['slug'], $html);
        });
    }

    private function renderCategory(Template $template, array $category): void
    {
        $posts = Database::fetchAll(
            "SELECT p.*, COALESCE(u.display_name, u.username) as author_name, u.username as author_username FROM posts p
             INNER JOIN post_categories pc ON p.id = pc.post_id
             LEFT JOIN users u ON p.author_id = u.id
             WHERE pc.category_id = ? AND p.status = 'published' AND p.deleted_at IS NULL
             ORDER BY p.published_at DESC",
            [$category['id']]
        );

        self::addFeaturedImageUrls($posts);

        if (!empty($category['featured_image'])) {
            $category['featured_image_url'] = file_url($category['featured_image']);
        }

        $template->setData([
            'site_name'        => Config::get('site_name'),
            'site_url'         => Config::get('site_url'),
            'category'         => $category,
            'posts'            => $posts,
            'title'            => $category['name'],
            'meta_keywords'    => $category['meta_keywords'] ?? '',
            'meta_description' => $category['meta_description'] ?? '',
        ]);

        $this->renderCached($template, 'category', HtmlCache::isEnabled(), function (string $html) use ($category): void {
            HtmlCache::putCategory($category['slug'], $html);
        });
    }

    private function renderAuthor(Template $template, array $author): void
    {
        $authorName = $author['display_name'] ?? $author['username'];

        $posts = Database::fetchAll(
            "SELECT p.*, COALESCE(u.display_name, u.username) as author_name, u.username as author_username FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.author_id = ? AND p.status = 'published' AND p.deleted_at IS NULL
             ORDER BY p.published_at DESC",
            [$author['id']]
        );

        self::addFeaturedImageUrls($posts);
        self::addPostCategories($posts);

        $template->setData([
            'site_name'        => Config::get('site_name'),
            'site_url'         => Config::get('site_url'),
            'author'           => $author,
            'author_name'      => $authorName,
            'posts'            => $posts,
            'title'            => $authorName,
            'meta_keywords'    => '',
            'meta_description' => $authorName . ' - ' . Config::get('site_name'),
        ]);

        $this->renderCached($template, 'author', HtmlCache::isEnabled(), function (string $html) use ($author): void {
            HtmlCache::putAuthor($author['username'], $html);
        });
    }

    /**
     * @return array{is_home?: bool, hide_home_heading?: bool, document_title?: string}
     */
    private function homePageTemplateData(bool $isHome, array $page): array
    {
        if (!$isHome) {
            return [];
        }

        $siteName = (string) Config::get('site_name');
        $overrideTitle = (bool) setting('home_page_override_title', false);

        return [
            'is_home'           => true,
            'hide_home_heading' => (bool) setting('home_page_hide_heading', false),
            'document_title'    => $overrideTitle ? (string) ($page['title'] ?? $siteName) : $siteName,
        ];
    }

    private static function addFeaturedImageUrls(array &$items): void
    {
        foreach ($items as &$item) {
            if (!empty($item['featured_image'])) {
                $item['featured_image_url'] = file_url($item['featured_image']);
            }
        }
        unset($item);
    }

    private static function addPostCategories(array &$posts): void
    {
        foreach ($posts as &$post) {
            $post['categories'] = ContentManager::getPostCategories((int) $post['id']);
        }
        unset($post);
    }

    /**
     * Render a template, optionally buffering the output to store it in the HTML cache.
     * The $store callback receives the rendered HTML and persists it via the relevant
     * HtmlCache::put* method.
     */
    private function renderCached(Template $template, string $templateName, bool $shouldCache, callable $store): void
    {
        if (!$shouldCache) {
            $template->render($templateName);
            return;
        }

        ob_start();
        $template->render($templateName);
        $html = (string) ob_get_clean();
        $store($html);
        echo $html;
    }

    private function renderSearchForm(Template $template): void
    {
        $template->setData([
            'site_name'        => Config::get('site_name'),
            'site_url'         => Config::get('site_url'),
            'title'            => __t('search'),
            'meta_keywords'    => '',
            'meta_description' => __t('search') . ' - ' . Config::get('site_name'),
        ]);

        $template->render('search');
    }

    private function renderSearchResults(Template $template, string $query): void
    {
        $searchTerms = array_values(array_filter(array_map('trim', explode(' ', $query))));
        $searchTerms = array_map('\trim', array_map('strtolower', $searchTerms));
        $searchTerms = array_unique($searchTerms);
        $searchTerms = array_filter($searchTerms, fn($t) => mb_strlen($t) >= 2);

        // Check cache first
        if (HtmlCache::isEnabled()) {
            $cached = HtmlCache::getSearch($query);
            if ($cached !== null) {
                echo $cached;
                return;
            }
        }

        $posts = [];
        if (!empty($searchTerms)) {
            $conditions = [];
            $params = [];

            foreach ($searchTerms as $term) {
                $likeTerm = '%' . $term . '%';
                $conditions[] = "(LOWER(p.title) LIKE ? OR LOWER(p.content) LIKE ? OR LOWER(p.excerpt) LIKE ?)";
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
            }

            $whereClause = implode(' AND ', $conditions);

            $sql = "SELECT p.*, COALESCE(u.display_name, u.username) as author_name, u.username as author_username FROM posts p
                     LEFT JOIN users u ON p.author_id = u.id
                     WHERE p.status = 'published' AND p.deleted_at IS NULL
                     AND {$whereClause}
                     ORDER BY p.published_at DESC";

            $posts = Database::fetchAll($sql, $params);
            self::addFeaturedImageUrls($posts);
            self::addPostCategories($posts);
        }

        $template->setData([
            'site_name'        => Config::get('site_name'),
            'site_url'         => Config::get('site_url'),
            'search_query'     => $query,
            'posts'            => $posts,
            'result_count'     => count($posts),
            'title'            => __t('search_results'),
            'meta_keywords'    => '',
            'meta_description' => $query . ' - ' . Config::get('site_name'),
        ]);

        $this->renderCached($template, 'results', HtmlCache::isEnabled(), function (string $html) use ($query, $searchTerms): void {
            HtmlCache::putSearch($query, $html, $searchTerms);
        });
    }
}
