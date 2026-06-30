<?php

declare(strict_types=1);

namespace Elementary;

class Template
{
    private string $themePath;
    private array $data = [];
    private ?string $currentTemplate = null;

    public function __construct()
    {
        $theme = Config::get('theme', '2026');
        $this->themePath = ELEMENTARY_ROOT . '/theme/' . $theme;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getThemeInfo(): array
    {
        $info = require $this->themePath . '/index.php';
        return is_array($info) ? $info : [];
    }

    public function getAvailablePageTemplates(): array
    {
        $templates = ['default' => 'Default'];
        $files = glob($this->themePath . '/page-*.php') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            $slug = substr($name, 5);
            $templates[$slug] = ucwords(str_replace('-', ' ', $slug));
        }
        return $templates;
    }

    public function render(string $template, array $data = []): void
    {
        $this->currentTemplate = $template;
        $this->data = array_merge($this->data, $data);

        Hooks::doAction('before_render', $template, $this->data);

        $file = $this->resolveTemplate($template);
        if (!file_exists($file)) {
            $file = $this->themePath . '/template.php';
        }

        extract($this->data, EXTR_SKIP);
        include $file;

        Hooks::doAction('after_render', $template, $this->data);
    }

    private function resolveTemplate(string $template): string
    {
        $map = [
            'post'     => 'post.php',
            'page'     => 'page.php',
            'category' => 'category.php',
            'author'   => 'author.php',
            'posts'    => 'posts.php',
            'search'   => 'search.php',
            'results'  => 'results.php',
            'home'     => 'template.php',
            'default'  => 'template.php',
        ];

        if (str_starts_with($template, 'page-')) {
            $file = $this->themePath . '/' . $template . '.php';
            if (file_exists($file)) {
                return $file;
            }
        }

        $filename = $map[$template] ?? 'template.php';
        $file = $this->themePath . '/' . $filename;

        if (!file_exists($file)) {
            return $this->themePath . '/template.php';
        }

        return $file;
    }

    public function getThemePath(): string
    {
        return $this->themePath;
    }

    public function themeUrl(string $path = ''): string
    {
        $theme = Config::get('theme', '2026');
        return url('theme/' . $theme . '/' . ltrim($path, '/'));
    }

    /**
     * Get menu locations defined by the current theme.
     * Themes define locations in index.php under 'menu_locations' key.
     */
    public function getMenuLocations(): array
    {
        $info = $this->getThemeInfo();
        return $info['menu_locations'] ?? [];
    }

    /**
     * Get a menu for a specific location as a renderable structure.
     */
    public function getMenu(string $location): ?array
    {
        return MenuManager::getMenuTreeForLocation($location);
    }
}
