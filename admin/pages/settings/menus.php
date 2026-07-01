<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\MenuManager;
use Elementary\Template;

$menus = MenuManager::getMenus();
$availablePages = MenuManager::getAvailablePages();
$availablePosts = MenuManager::getAvailablePosts();
$availableCategories = MenuManager::getAvailableCategories();

$template = new Template();
$themeLocations = $template->getMenuLocations();
$menuAssignments = MenuManager::getMenuLocationAssignments(array_keys($themeLocations));

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['menu_action'] ?? '';

    switch ($action) {
        case 'create_menu':
            $result = MenuManager::createMenu($_POST['menu_name'] ?? '');
            if ($result['success']) {
                flash('success', __t('menu_saved'));
            } else {
                flash('error', $result['error'] ?? __t('menu_saved'));
            }
            redirect(admin_url('settings/menus'));
            break;

        case 'update_menu':
            $result = MenuManager::updateMenu((int) $_POST['menu_id'], $_POST['menu_name'] ?? '');
            if ($result['success']) {
                flash('success', __t('menu_saved'));
            } else {
                flash('error', $result['error'] ?? 'Error updating menu.');
            }
            redirect(admin_url('settings/menus'));
            break;

        case 'delete_menu':
            MenuManager::deleteMenu((int) $_POST['menu_id']);
            flash('success', __t('menu_deleted'));
            redirect(admin_url('settings/menus'));
            break;

        case 'create_item':
            $itemType = $_POST['item_type'] ?? 'url';
            $result = MenuManager::createMenuItem([
                'menu_id'   => (int) $_POST['menu_id'],
                'parent_id' => $_POST['parent_id'] ?? 0,
                'title'     => $_POST['item_title'] ?? '',
                'url'       => $_POST['item_url'] ?? '',
                'type'      => $itemType,
                'type_id'   => MenuManager::typeIdFromPost($itemType, $_POST),
                'sort_order' => $_POST['item_sort_order'] ?? 0,
            ]);
            if ($result['success']) {
                flash('success', __t('menu_item_added'));
            }
            redirect(admin_url('settings/menus'));
            break;

        case 'update_item':
            $itemType = $_POST['item_type'] ?? 'url';
            $result = MenuManager::updateMenuItem((int) $_POST['item_id'], [
                'parent_id' => $_POST['parent_id'] ?? 0,
                'title'     => $_POST['item_title'] ?? '',
                'url'       => $_POST['item_url'] ?? '',
                'type'      => $itemType,
                'type_id'   => MenuManager::typeIdFromPost($itemType, $_POST),
                'sort_order' => $_POST['item_sort_order'] ?? 0,
            ]);
            if ($result['success']) {
                flash('success', __t('menu_item_updated'));
            } else {
                flash('error', $result['error'] ?? 'Error updating item.');
            }
            redirect(admin_url('settings/menus'));
            break;

        case 'delete_item':
            MenuManager::deleteMenuItem((int) $_POST['item_id']);
            flash('success', __t('menu_item_deleted'));
            redirect(admin_url('settings/menus'));
            break;

        case 'save_order':
            $menuId = (int) $_POST['menu_id'];
            $orders = $_POST['item_order'] ?? [];
            MenuManager::saveMenuOrder($menuId, $orders);
            flash('success', __t('menu_order_saved'));
            redirect(admin_url('settings/menus'));
            break;

        case 'save_locations':
            foreach ($themeLocations as $locationKey => $locationLabel) {
                $menuName = $_POST['location_menu_' . $locationKey] ?? '';
                MenuManager::saveMenuLocationAssignment($locationKey, $menuName);
            }
            flash('success', __t('menu_locations_saved'));
            redirect(admin_url('settings/menus'));
            break;
    }
}

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('menus'), 'url' => '#']
]) ?>
<?= admin_flash() ?>

<!-- Hidden form for deleting menu items (must be outside other forms) -->
<form method="post" id="delete-item-form" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="menu_action" value="delete_item">
    <input type="hidden" name="item_id" id="delete-item-id" value="">
</form>

<!-- Menu Locations -->
<?php if (!empty($themeLocations)): ?>
<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= __t('menu_locations') ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="menu_action" value="save_locations">
            <div class="row">
                <?php foreach ($themeLocations as $locationKey => $locationLabel): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= esc($locationLabel) ?></label>
                    <select name="location_menu_<?= esc($locationKey) ?>" class="form-select">
                        <option value=""><?= __t('none') ?></option>
                        <?php foreach ($menus as $m): ?>
                        <option value="<?= esc($m['name']) ?>" <?= ($menuAssignments[$locationKey] ?? '') === $m['name'] ? 'selected' : '' ?>><?= esc($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info mb-4"><?= __t('no_menu_locations') ?></div>
<?php endif; ?>

<!-- Create New Menu -->
<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= __t('new_menu') ?></h2>
        <form method="post" class="row g-3" style="max-width: 500px;">
            <?= csrf_field() ?>
            <input type="hidden" name="menu_action" value="create_menu">
            <div class="col-sm-8">
                <input type="text" class="form-control" name="menu_name" placeholder="<?= __t('menu_name') ?>" required>
            </div>
            <div class="col-sm-4">
                <button type="submit" class="btn btn-dark w-100"><?= __t('create') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Menu List -->
<?php if (empty($menus)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <p class="text-muted"><?= __t('no_menus_yet') ?></p>
    </div>
</div>
<?php endif; ?>

<?php foreach ($menus as $menu): ?>
<?php
    $menuItems = MenuManager::getMenuItemsTree($menu['id']);
?>
<div class="card mb-4 menu-card" id="menu-<?= $menu['id'] ?>">
    <div class="card-body">
        <!-- Menu Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-0"><?= esc($menu['name']) ?></h2>
                <small class="text-muted"><?= count($menuItems) ?> <?= count($menuItems) === 1 ? __t('item') : __t('items') ?></small>
            </div>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleMenuItems(<?= $menu['id'] ?>)"><i class="bi bi-chevron-down"></i> <?= __t('toggle') ?></button>
                <button class="btn btn-sm btn-outline-secondary" onclick="showEditMenuForm(<?= $menu['id'] ?>, '<?= esc($menu['name']) ?>')"><i class="bi bi-pencil"></i> <?= __t('edit') ?></button>
                <form method="post" class="d-inline" onsubmit="return confirm('<?= __t('confirm_delete_menu') ?>')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="menu_action" value="delete_menu">
                    <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> <?= __t('delete') ?></button>
                </form>
            </div>
        </div>

        <!-- Edit Menu Name Form (hidden by default) -->
        <div class="edit-menu-form mb-3" id="edit-menu-form-<?= $menu['id'] ?>" style="display: none;">
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="menu_action" value="update_menu">
                <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="menu_name" value="<?= esc($menu['name']) ?>" required>
                </div>
                <div class="col-sm-4">
                    <button type="submit" class="btn btn-dark me-1"><?= __t('save') ?></button>
                    <button type="button" class="btn btn-secondary" onclick="hideEditMenuForm(<?= $menu['id'] ?>)"><?= __t('cancel') ?></button>
                </div>
            </form>
        </div>

        <!-- Menu Items -->
        <div class="menu-items-container" id="menu-items-<?= $menu['id'] ?>">
            <?php if (empty($menuItems)): ?>
            <p class="text-muted mb-3"><?= __t('no_items_in_menu') ?></p>
            <?php else: ?>
            <form method="post" id="menu-order-form-<?= $menu['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="menu_action" value="save_order">
                <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                <div class="menu-items-list" id="menu-items-list-<?= $menu['id'] ?>">
                    <?php
                    function renderMenuItem(array $item, array $allItems, int $depth = 0, int $menuId = 0): void {
                        $hasChildren = !empty($item['children']);
                        ?>
                        <div class="menu-item-row d-flex align-items-center py-2 px-3 mb-1 bg-white border rounded" data-item-id="<?= $item['id'] ?>" data-parent-id="<?= $item['parent_id'] ?>" data-sort-order="<?= $item['sort_order'] ?>" style="margin-left: <?= $depth * 30 ?>px;">
                            <i class="bi bi-grip-vertical me-2 text-muted" style="cursor: grab;"></i>
                            <div class="flex-grow-1">
                                <strong><?= esc($item['title']) ?></strong>
                                <?php if ($item['url']): ?>
                                <small class="text-muted ms-1">(<?= esc($item['url']) ?>)</small>
                                <?php endif; ?>
                                <input type="hidden" name="item_order[<?= $item['id'] ?>][sort_order]" value="<?= $item['sort_order'] ?>">
                                <input type="hidden" name="item_order[<?= $item['id'] ?>][parent_id]" value="<?= $item['parent_id'] ?>">
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" onclick="showAddItemForm(<?= $menuId ?>, <?= $item['id'] ?>)"><i class="bi bi-plus"></i> <?= __t('add_sub_item') ?></button>
                                <button type="button" class="btn btn-outline-secondary" onclick="showEditItemForm(<?= $menuId ?>, <?= $item['id'] ?>, '<?= esc($item['title']) ?>', '<?= esc($item['url']) ?>', <?= $item['parent_id'] ?>, '<?= $item['type'] ?>', <?= $item['type_id'] ?? 'null' ?>)"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-outline-danger" onclick="deleteMenuItem(<?= $item['id'] ?>)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <?php
                        if ($hasChildren) {
                            foreach ($item['children'] as $child) {
                                renderMenuItem($child, $allItems, $depth + 1, $menuId);
                            }
                        }
                    }
                    foreach ($menuItems as $topItem) {
                        renderMenuItem($topItem, $menuItems, 0, (int) $menu['id']);
                    }
                    ?>
                </div>
                <div class="mt-2">
                    <button type="submit" class="btn btn-sm btn-dark"><?= __t('save_order') ?></button>
                </div>
            </form>
            <?php endif; ?>

            <!-- Add Top-Level Item Form -->
            <div class="add-item-form mt-3" id="add-item-form-<?= $menu['id'] ?>" style="display: none;">
                <form method="post" class="border rounded p-3 bg-light">
                    <?= csrf_field() ?>
                    <input type="hidden" name="menu_action" value="create_item">
                    <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                    <input type="hidden" name="parent_id" id="add-parent-id-<?= $menu['id'] ?>" value="0">
                    <input type="hidden" name="item_sort_order" value="<?= count($menuItems) ?>">
                    <h3 class="h6 mb-3"><?= __t('add_menu_item') ?></h3>

                    <div class="mb-3">
                        <label class="form-label"><?= __t('menu_item_type') ?></label>
                        <select name="item_type" class="form-select" id="add-item-type-<?= $menu['id'] ?>" onchange="toggleItemTypeFields(<?= $menu['id'] ?>, 'add')">
                            <option value="home"><?= __t('menu_item_type_home') ?></option>
                            <option value="url"><?= __t('menu_item_type_url') ?></option>
                            <?php if (!empty($availablePages)): ?>
                            <option value="page"><?= __t('menu_item_type_page') ?></option>
                            <?php endif; ?>
                            <?php if (!empty($availablePosts)): ?>
                            <option value="post"><?= __t('menu_item_type_post') ?></option>
                            <?php endif; ?>
                            <?php if (!empty($availableCategories)): ?>
                            <option value="category"><?= __t('menu_item_type_category') ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __t('menu_item_title') ?></label>
                        <input type="text" class="form-control" name="item_title" required>
                    </div>

                    <div class="mb-3 item-url-field">
                        <label class="form-label"><?= __t('menu_item_url') ?></label>
                        <input type="url" class="form-control" name="item_url" placeholder="<?= __t('url_placeholder') ?>">
                        <div class="form-text"><?= __t('url_prefix_removed') ?></div>
                    </div>

                    <div class="mb-3 item-home-field d-none">
                        <div class="alert alert-info mb-0"><i class="bi bi-house-door me-1"></i><?= __t('menu_item_home_title') ?> — <?= __t('menu_item_home_desc') ?></div>
                    </div>

                    <div class="mb-3 item-page-field d-none">
                        <label class="form-label"><?= __t('menu_item_type_page') ?></label>
                        <select name="item_page_id" class="form-control" onchange="autoFillItemTitleAndUrl(<?= $menu['id'] ?>, 'add', 'page')">
                            <option value=""><?= __t('menu_item_select_page') ?></option>
                            <?php foreach ($availablePages as $p): ?>
                            <option value="<?= $p['id'] ?>" data-title="<?= esc($p['title']) ?>" data-slug="<?= esc($p['slug']) ?>"><?= esc($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 item-post-field d-none">
                        <label class="form-label"><?= __t('menu_item_type_post') ?></label>
                        <select name="item_post_id" class="form-control" onchange="autoFillItemTitleAndUrl(<?= $menu['id'] ?>, 'add', 'post')">
                            <option value=""><?= __t('menu_item_select_post') ?></option>
                            <?php foreach ($availablePosts as $p): ?>
                            <option value="<?= $p['id'] ?>" data-title="<?= esc($p['title']) ?>" data-slug="<?= esc($p['slug']) ?>"><?= esc($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 item-category-field d-none">
                        <label class="form-label"><?= __t('menu_item_type_category') ?></label>
                        <select name="item_category_id" class="form-control" onchange="autoFillItemTitleAndUrl(<?= $menu['id'] ?>, 'add', 'category')">
                            <option value=""><?= __t('menu_item_select_category') ?></option>
                            <?php foreach ($availableCategories as $c): ?>
                            <option value="<?= $c['id'] ?>" data-title="<?= esc($c['title']) ?>" data-slug="<?= esc($c['slug']) ?>"><?= esc($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __t('menu_item_parent') ?></label>
                        <select name="parent_id" class="form-control" id="add-parent-select-<?= $menu['id'] ?>">
                            <option value="0"><?= __t('menu_item_top_level') ?></option>
                            <?php foreach (MenuManager::getMenuItems($menu['id']) as $mi): ?>
                            <option value="<?= $mi['id'] ?>"><?= str_repeat('— ', 1) . esc($mi['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
                    <button type="button" class="btn btn-secondary" onclick="hideAddItemForm(<?= $menu['id'] ?>)"><?= __t('cancel') ?></button>
                </form>
            </div>

            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="showAddItemForm(<?= $menu['id'] ?>, 0)"><i class="bi bi-plus-lg me-1"></i><?= __t('add_menu_item') ?></button>
            </div>
        </div>

        <!-- Edit Item Form (hidden by default, injected via JS) -->
        <div class="edit-item-form mt-3" id="edit-item-form-<?= $menu['id'] ?>" style="display: none;">
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
// Build menu items map for JavaScript usage
const menuItemsMap = <?php
    $map = [];
    foreach ($menus as $m) {
        $map[$m['id']] = MenuManager::getMenuItems($m['id']);
    }
    echo json_encode($map);
?>;

function toggleMenuItems(menuId) {
    const container = document.getElementById('menu-items-' + menuId);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

function showEditMenuForm(menuId, currentName) {
    document.getElementById('edit-menu-form-' + menuId).style.display = 'block';
}

function hideEditMenuForm(menuId) {
    document.getElementById('edit-menu-form-' + menuId).style.display = 'none';
}

function showAddItemForm(menuId, parentId) {
    const form = document.getElementById('add-item-form-' + menuId);
    form.style.display = 'block';
    document.getElementById('add-parent-id-' + menuId).value = parentId;
    // Also update the parent select
    const select = document.getElementById('add-parent-select-' + menuId);
    if (select) select.value = parentId;
    // Apply correct field visibility based on current type selection
    toggleItemTypeFields(menuId, 'add');
}

function hideAddItemForm(menuId) {
    document.getElementById('add-item-form-' + menuId).style.display = 'none';
}

function showEditItemForm(menuId, itemId, title, url, parentId, type, typeId) {
    const container = document.getElementById('edit-item-form-' + menuId);
    const items = menuItemsMap[menuId] || [];

    let parentOptions = '<option value="0"><?= __t('menu_item_top_level') ?></option>';
    items.forEach(function(mi) {
        parentOptions += '<option value="' + mi.id + '">' + mi.title + '</option>';
    });

    container.innerHTML = `
        <form method="post" class="border rounded p-3 bg-light">
            <?= csrf_field() ?>
            <input type="hidden" name="menu_action" value="update_item">
            <input type="hidden" name="item_id" value="${itemId}">
            <h3 class="h6 mb-3"><?= __t('edit_menu_item') ?></h3>

            <div class="mb-3">
                <label class="form-label"><?= __t('menu_item_type') ?></label>
                <select name="item_type" class="form-select" id="edit-item-type-${itemId}" onchange="toggleItemTypeFields(${menuId}, 'edit', ${itemId})">
                    <option value="home" ${type === 'home' ? 'selected' : ''}><?= __t('menu_item_type_home') ?></option>
                    <option value="url" ${type === 'url' ? 'selected' : ''}><?= __t('menu_item_type_url') ?></option>
                    <?php if (!empty($availablePages)): ?>
                    <option value="page" ${type === 'page' ? 'selected' : ''}><?= __t('menu_item_type_page') ?></option>
                    <?php endif; ?>
                    <?php if (!empty($availablePosts)): ?>
                    <option value="post" ${type === 'post' ? 'selected' : ''}><?= __t('menu_item_type_post') ?></option>
                    <?php endif; ?>
                    <?php if (!empty($availableCategories)): ?>
                    <option value="category" ${type === 'category' ? 'selected' : ''}><?= __t('menu_item_type_category') ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= __t('menu_item_title') ?></label>
                <input type="text" class="form-control" name="item_title" value="${title}" required>
            </div>

            <div class="mb-3 item-url-field ${type !== 'url' ? 'd-none' : ''}">
                <label class="form-label"><?= __t('menu_item_url') ?></label>
                <input type="url" class="form-control" name="item_url" value="${url}" placeholder="<?= __t('url_placeholder') ?>">
            </div>

            <div class="mb-3 item-home-field ${type !== 'home' ? 'd-none' : ''}">
                <div class="alert alert-info mb-0"><i class="bi bi-house-door me-1"></i><?= __t('menu_item_home_title') ?> — <?= __t('menu_item_home_desc') ?></div>
            </div>

            <div class="mb-3 item-page-field ${type !== 'page' ? 'd-none' : ''}">
                <label class="form-label"><?= __t('menu_item_type_page') ?></label>
                <select name="item_page_id" class="form-control">
                    <option value=""><?= __t('menu_item_select_page') ?></option>
                    <?php foreach ($availablePages as $p): ?>
                    <option value="<?= $p['id'] ?>" data-title="<?= esc($p['title']) ?>" data-slug="<?= esc($p['slug']) ?>" ${typeId && typeId == <?= $p['id'] ?> ? 'selected' : ''}><?= esc($p['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3 item-post-field ${type !== 'post' ? 'd-none' : ''}">
                <label class="form-label"><?= __t('menu_item_type_post') ?></label>
                <select name="item_post_id" class="form-control">
                    <option value=""><?= __t('menu_item_select_post') ?></option>
                    <?php foreach ($availablePosts as $p): ?>
                    <option value="<?= $p['id'] ?>" data-title="<?= esc($p['title']) ?>" data-slug="<?= esc($p['slug']) ?>" ${typeId && typeId == <?= $p['id'] ?> ? 'selected' : ''}><?= esc($p['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3 item-category-field ${type !== 'category' ? 'd-none' : ''}">
                <label class="form-label"><?= __t('menu_item_type_category') ?></label>
                <select name="item_category_id" class="form-control">
                    <option value=""><?= __t('menu_item_select_category') ?></option>
                    <?php foreach ($availableCategories as $c): ?>
                    <option value="<?= $c['id'] ?>" data-title="<?= esc($c['title']) ?>" data-slug="<?= esc($c['slug']) ?>" ${typeId && typeId == <?= $c['id'] ?> ? 'selected' : ''}><?= esc($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= __t('menu_item_parent') ?></label>
                <select name="parent_id" class="form-control">
                    ${parentOptions}
                </select>
            </div>

            <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('edit-item-form-${menuId}').style.display='none'"><?= __t('cancel') ?></button>
        </form>
    `;
    container.style.display = 'block';
}

function toggleItemTypeFields(menuId, mode, itemId = null) {
    const prefix = mode === 'add' ? 'add-item-type-' + menuId : 'edit-item-type-' + itemId;
    const typeSelect = document.getElementById(prefix);
    const type = typeSelect.value;

    const container = mode === 'add'
        ? document.getElementById('add-item-form-' + menuId)
        : document.getElementById('edit-item-form-' + menuId);

    const urlField = container.querySelector('.item-url-field');
    const homeField = container.querySelector('.item-home-field');
    const pageField = container.querySelector('.item-page-field');
    const postField = container.querySelector('.item-post-field');
    const categoryField = container.querySelector('.item-category-field');

    // Hide all
    urlField.classList.add('d-none');
    if (homeField) homeField.classList.add('d-none');
    pageField.classList.add('d-none');
    postField.classList.add('d-none');
    categoryField.classList.add('d-none');

    if (type === 'home') {
        if (homeField) homeField.classList.remove('d-none');
        // Auto-fill title and URL for home
        const titleInput = container.querySelector('input[name="item_title"]');
        if (titleInput) titleInput.value = '<?= __t('menu_item_home_title') ?>';
    } else if (type === 'url') {
        urlField.classList.remove('d-none');
    } else if (type === 'page') {
        pageField.classList.remove('d-none');
    } else if (type === 'post') {
        postField.classList.remove('d-none');
    } else if (type === 'category') {
        categoryField.classList.remove('d-none');
    }
}

function autoFillItemTitleAndUrl(menuId, mode, type) {
    const prefix = mode === 'add' ? 'add-item-form-' + menuId : 'edit-item-form-' + menuId;
    const container = document.getElementById(prefix);
    const select = container.querySelector('.item-' + type + '-field select');
    const option = select.options[select.selectedIndex];
    const titleInput = container.querySelector('input[name="item_title"]');

    if (option.dataset.title) {
        titleInput.value = option.dataset.title;
    }
}

// Simple drag and drop for menu items
document.addEventListener('DOMContentLoaded', function() {
    const lists = document.querySelectorAll('.menu-items-list');
    lists.forEach(list => {
        let draggedItem = null;

        list.addEventListener('dragstart', function(e) {
            draggedItem = e.target.closest('.menu-item-row');
            if (draggedItem) {
                setTimeout(() => draggedItem.style.opacity = '0.4', 0);
            }
        });

        list.addEventListener('dragend', function(e) {
            if (draggedItem) {
                draggedItem.style.opacity = '1';
                draggedItem = null;
            }
            // Update sort_order values based on new DOM order
            updateSortOrder(list);
        });

        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(list, e.clientY);
            if (draggedItem) {
                if (afterElement == null) {
                    list.appendChild(draggedItem);
                } else {
                    list.insertBefore(draggedItem, afterElement);
                }
            }
        });

        // Make rows draggable
        list.querySelectorAll('.menu-item-row').forEach(row => {
            row.setAttribute('draggable', 'true');
        });
    });
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.menu-item-row:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function updateSortOrder(list) {
    const rows = list.querySelectorAll('.menu-item-row');
    rows.forEach((row, index) => {
        const sortInput = row.querySelector('input[name*="[sort_order]"]');
        if (sortInput) {
            sortInput.value = index;
        }
    });
}

function deleteMenuItem(itemId) {
    if (!confirm('<?= addslashes(__t('confirm_delete_menu_item')) ?>')) return;
    document.getElementById('delete-item-id').value = itemId;
    document.getElementById('delete-item-form').submit();
}
</script>

<style>
.menu-item-row { cursor: move; transition: background-color 0.15s; }
.menu-item-row:hover { background-color: #f8f8f5; }
.menu-item-row .bi-grip-vertical { cursor: grab; font-size: 1.1rem; }
.edit-menu-form, .edit-item-form { animation: fadeIn 0.2s ease-in; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
<?php
admin_layout(__t('menus'), ob_get_clean());
