<?php

declare(strict_types=1);

namespace Elementary;

class MenuManager
{
    /**
     * Get all menus.
     */
    public static function getMenus(): array
    {
        return Database::fetchAll('SELECT * FROM menus ORDER BY name ASC');
    }

    /**
     * Get a single menu by ID.
     */
    public static function getMenu(int $id): ?array
    {
        return Database::fetch('SELECT * FROM menus WHERE id = ?', [$id]);
    }

    /**
     * Get a menu by name.
     */
    public static function getMenuByName(string $name): ?array
    {
        return Database::fetch('SELECT * FROM menus WHERE name = ?', [$name]);
    }

    /**
     * Get all items for a menu, sorted by sort_order.
     */
    public static function getMenuItems(int $menuId): array
    {
        return Database::fetchAll(
            'SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC',
            [$menuId]
        );
    }

    /**
     * Get a single menu item by ID.
     */
    public static function getMenuItem(int $id): ?array
    {
        return Database::fetch('SELECT * FROM menu_items WHERE id = ?', [$id]);
    }

    /**
     * Get items organized as a tree (with children nested).
     */
    public static function getMenuItemsTree(int $menuId): array
    {
        $flat = self::getMenuItems($menuId);
        $tree = [];
        $map = [];

        foreach ($flat as $item) {
            $item['children'] = [];
            $map[$item['id']] = $item;
        }

        foreach ($map as $item) {
            $parentId = (int) $item['parent_id'];
            if ($parentId === 0 || !isset($map[$parentId])) {
                $tree[] = &$map[$item['id']];
            } else {
                $map[$parentId]['children'][] = &$map[$item['id']];
            }
        }

        return $tree;
    }

    /**
     * Create a new menu.
     */
    public static function createMenu(string $name): array
    {
        $existing = Database::fetch('SELECT id FROM menus WHERE name = ?', [$name]);
        if ($existing) {
            return ['success' => false, 'error' => 'A menu with that name already exists.'];
        }

        $now = date('Y-m-d H:i:s');
        $id = Database::insert('menus', [
            'name'       => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Logger::log('menu_created', Auth::user()['id'] ?? null, 'Menu: ' . $name);
        self::bumpPublicCache();
        return ['success' => true, 'id' => $id];
    }

    /**
     * Update a menu.
     */
    public static function updateMenu(int $id, string $name): array
    {
        $menu = self::getMenu($id);
        if (!$menu) {
            return ['success' => false, 'error' => 'Menu not found.'];
        }

        $existing = Database::fetch('SELECT id FROM menus WHERE name = ? AND id != ?', [$name, $id]);
        if ($existing) {
            return ['success' => false, 'error' => 'A menu with that name already exists.'];
        }

        Database::update('menus', [
            'name'       => $name,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Logger::log('menu_updated', Auth::user()['id'] ?? null, 'Menu ID: ' . $id);
        self::bumpPublicCache();
        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete a menu and all its items.
     */
    public static function deleteMenu(int $id): void
    {
        $menu = self::getMenu($id);
        Database::delete('menus', 'id = ?', [$id]);
        Logger::log('menu_deleted', Auth::user()['id'] ?? null, 'Menu ID: ' . $id . ' - ' . ($menu['name'] ?? 'Unknown'));
        self::bumpPublicCache();
    }

    /**
     * Resolve the URL for a menu item based on its type.
     */
    public static function resolveMenuItemUrl(string $type, ?int $typeId, string $storedUrl = ''): string
    {
        switch ($type) {
            case 'home':
                return rtrim(Config::get('site_url', ''), '/') . '/';
            case 'page':
                if ($typeId) {
                    $page = Database::fetch(
                        "SELECT slug FROM pages WHERE id = ? AND status = 'published' AND deleted_at IS NULL",
                        [$typeId]
                    );
                    if ($page && ($page['slug'] ?? '') !== '') {
                        return url($page['slug']);
                    }
                }
                break;
            case 'post':
                if ($typeId) {
                    $post = Database::fetch(
                        "SELECT slug FROM posts WHERE id = ? AND status = 'published' AND deleted_at IS NULL",
                        [$typeId]
                    );
                    if ($post && ($post['slug'] ?? '') !== '') {
                        return url($post['slug']);
                    }
                }
                break;
            case 'category':
                if ($typeId) {
                    $category = Database::fetch('SELECT slug FROM categories WHERE id = ?', [$typeId]);
                    if ($category && ($category['slug'] ?? '') !== '') {
                        return url($category['slug']);
                    }
                }
                break;
            case 'custom':
            case 'url':
                return Validator::sanitizeMenuUrl($storedUrl);
        }

        return Validator::sanitizeMenuUrl($storedUrl !== '' ? $storedUrl : '#');
    }

    /**
     * Extract type_id from form POST data based on item type.
     */
    public static function typeIdFromPost(string $type, array $post): ?int
    {
        $fieldMap = [
            'page'     => 'item_page_id',
            'post'     => 'item_post_id',
            'category' => 'item_category_id',
        ];

        if (!isset($fieldMap[$type])) {
            return null;
        }

        $value = $post[$fieldMap[$type]] ?? null;
        return !empty($value) ? (int) $value : null;
    }

    /**
     * Create a new menu item.
     */
    public static function createMenuItem(array $data): array
    {
        $type = $data['type'] ?? 'custom';
        $typeId = isset($data['type_id']) && $data['type_id'] !== null && $data['type_id'] !== ''
            ? (int) $data['type_id']
            : null;
        $now = date('Y-m-d H:i:s');
        $id = Database::insert('menu_items', [
            'menu_id'    => (int) $data['menu_id'],
            'parent_id'  => isset($data['parent_id']) && $data['parent_id'] > 0 ? (int) $data['parent_id'] : 0,
            'title'      => $data['title'],
            'url'        => self::resolveMenuItemUrl($type, $typeId, $data['url'] ?? ''),
            'type'       => $type,
            'type_id'    => $typeId,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Logger::log('menu_item_created', Auth::user()['id'] ?? null, 'Item ID: ' . $id);
        self::bumpPublicCache();
        return ['success' => true, 'id' => $id];
    }

    /**
     * Update a menu item.
     */
    public static function updateMenuItem(int $id, array $data): array
    {
        $item = self::getMenuItem($id);
        if (!$item) {
            return ['success' => false, 'error' => 'Menu item not found.'];
        }

        $type = $data['type'] ?? 'custom';
        $typeId = isset($data['type_id']) && $data['type_id'] !== null && $data['type_id'] !== ''
            ? (int) $data['type_id']
            : null;

        Database::update('menu_items', [
            'parent_id'  => isset($data['parent_id']) && $data['parent_id'] > 0 ? (int) $data['parent_id'] : 0,
            'title'      => $data['title'],
            'url'        => self::resolveMenuItemUrl($type, $typeId, $data['url'] ?? ''),
            'type'       => $type,
            'type_id'    => $typeId,
            'sort_order' => $data['sort_order'] ?? 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Logger::log('menu_item_updated', Auth::user()['id'] ?? null, 'Item ID: ' . $id);
        self::bumpPublicCache();
        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete a menu item and all its children.
     */
    public static function deleteMenuItem(int $id): void
    {
        // Delete children first (recursive via CASCADE on parent_id would need a FK, so we do it manually)
        $children = Database::fetchAll('SELECT id FROM menu_items WHERE parent_id = ?', [$id]);
        foreach ($children as $child) {
            self::deleteMenuItem((int) $child['id']);
        }

        Database::delete('menu_items', 'id = ?', [$id]);
        Logger::log('menu_item_deleted', Auth::user()['id'] ?? null, 'Item ID: ' . $id);
        self::bumpPublicCache();
    }

    /**
     * Save menu item order (from drag-and-drop reordering).
     */
    public static function saveMenuOrder(int $menuId, array $itemOrders): void
    {
        foreach ($itemOrders as $itemId => $orderData) {
            Database::update('menu_items', [
                'parent_id'  => isset($orderData['parent_id']) && $orderData['parent_id'] > 0 ? (int) $orderData['parent_id'] : 0,
                'sort_order' => (int) $orderData['sort_order'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ? AND menu_id = ?', [(int) $itemId, $menuId]);
        }

        Logger::log('menu_order_updated', Auth::user()['id'] ?? null, 'Menu ID: ' . $menuId);
        self::bumpPublicCache();
    }

    /**
     * Get available pages for menu item selection.
     */
    public static function getAvailablePages(): array
    {
        return Database::fetchAll(
            "SELECT id, title, slug FROM pages WHERE status = 'published' ORDER BY title ASC"
        );
    }

    /**
     * Get available posts for menu item selection.
     */
    public static function getAvailablePosts(): array
    {
        return Database::fetchAll(
            "SELECT id, title, slug FROM posts WHERE status = 'published' ORDER BY title ASC"
        );
    }

    /**
     * Get available categories for menu item selection.
     */
    public static function getAvailableCategories(): array
    {
        return Database::fetchAll('SELECT id, name AS title, slug FROM categories ORDER BY name ASC');
    }

    /**
     * Get the menu assigned to a location (from settings).
     */
    public static function getMenuForLocation(string $location): ?array
    {
        $assignedMenuName = setting('menu_location_' . $location, '');
        if (empty($assignedMenuName)) {
            return null;
        }
        return self::getMenuByName($assignedMenuName);
    }

    /**
     * Get the full menu (with items tree) for a location.
     */
    public static function getMenuTreeForLocation(string $location): ?array
    {
        $menu = self::getMenuForLocation($location);
        if (!$menu) {
            return null;
        }
        return [
            'menu'   => $menu,
            'items'  => self::getMenuItemsTree($menu['id']),
        ];
    }

    /**
     * Get all menu location assignments.
     */
    public static function getMenuLocationAssignments(array $locations): array
    {
        $assignments = [];
        foreach ($locations as $location) {
            $assignments[$location] = setting('menu_location_' . $location, '');
        }
        return $assignments;
    }

    /**
     * Save menu location assignment.
     */
    public static function saveMenuLocationAssignment(string $location, string $menuName): void
    {
        set_setting('menu_location_' . $location, $menuName);
        Logger::log('menu_location_updated', Auth::user()['id'] ?? null, "Location: $location -> Menu: $menuName");
        self::bumpPublicCache();
    }

    private static function bumpPublicCache(): void
    {
        HtmlCache::clearAll();
    }
}
