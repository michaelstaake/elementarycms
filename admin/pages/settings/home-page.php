<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$pages = ContentManager::getPages();
$currentHome = (string) ContentManager::getHomePageId();
$hideHeading = (bool) setting('home_page_hide_heading', false);
$overrideTitle = (bool) setting('home_page_override_title', false);

$publishedPageIds = array_map(
    static fn(array $p): string => (string) $p['id'],
    array_filter($pages, static fn(array $p): bool => $p['status'] === 'published')
);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax && verify_csrf()) {
    header('Content-Type: application/json');

    $field = $_POST['field'] ?? '';

    if ($field === 'home_page') {
        $homePage = (string) ($_POST['value'] ?? '');
        if (!in_array($homePage, $publishedPageIds, true)) {
            echo json_encode(['success' => false, 'error' => __t('home_page_invalid')]);
            exit;
        }
        set_setting('home_page', $homePage);
        ContentManager::syncPostsPageSlug();
        Logger::log('settings_updated', Auth::user()['id'], 'Home page set to: ' . $homePage);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($field === 'home_page_hide_heading') {
        $enabled = ($_POST['value'] ?? '') === '1';
        set_setting('home_page_hide_heading', $enabled);
        $hideHeading = $enabled;
        Logger::log('settings_updated', Auth::user()['id'], 'Home page hide heading ' . ($enabled ? 'enabled' : 'disabled'));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($field === 'home_page_override_title') {
        $enabled = ($_POST['value'] ?? '') === '1';
        set_setting('home_page_override_title', $enabled);
        $overrideTitle = $enabled;
        Logger::log('settings_updated', Auth::user()['id'], 'Home page title override ' . ($enabled ? 'enabled' : 'disabled'));
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid field']);
    exit;
}

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('home_page'), 'url' => '#']
]) ?>

<div class="card p-4" style="max-width: 600px;">
    <div class="mb-3">
        <label class="form-label" for="home_page_select"><?= __t('home_page_select') ?></label>
        <select id="home_page_select" class="form-select" required>
            <?php foreach ($pages as $p): ?>
                <?php if ($p['status'] === 'published'): ?>
                <option value="<?= $p['id'] ?>" <?= $currentHome === (string) $p['id'] ? 'selected' : '' ?>><?= esc($p['title']) ?><?= ContentManager::isPostsPage($p) ? ' (' . __t('posts_page_label') . ')' : '' ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <div id="home_page_status" class="form-text"></div>
        <div class="form-text"><?= __t('home_page_help') ?></div>
    </div>

    <div class="mb-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="home_page_hide_heading" value="1" <?= $hideHeading ? 'checked' : '' ?>>
            <label class="form-check-label" for="home_page_hide_heading"><?= __t('home_page_hide_heading') ?></label>
        </div>
        <div id="home_page_hide_heading_status" class="form-text"></div>
        <div class="form-text"><?= __t('home_page_hide_heading_help') ?></div>
    </div>

    <div class="mb-0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="home_page_override_title" value="1" <?= $overrideTitle ? 'checked' : '' ?>>
            <label class="form-check-label" for="home_page_override_title"><?= __t('home_page_override_title') ?></label>
        </div>
        <div id="home_page_override_title_status" class="form-text"></div>
        <div class="form-text"><?= __t('home_page_override_title_help') ?></div>
    </div>
</div>

<script>
(function() {
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const baseUrl = <?= json_encode(admin_url('settings/home-page')) ?>;

    function setStatus(field, state, message) {
        const statusEl = document.getElementById(field + '_status');
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message || '';
        statusEl.className = 'form-text' + (state ? ' text-' + state : '');
    }

    function ajaxSave(field, value) {
        setStatus(field, 'muted', 'Saving...');

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('field', field);
        formData.append('value', value);

        return fetch(baseUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                setStatus(field, 'success', 'Saved');
            } else {
                setStatus(field, 'danger', data.error || 'Error saving');
            }
            setTimeout(function() { setStatus(field, '', ''); }, 3000);
            return data;
        })
        .catch(function() {
            setStatus(field, 'danger', 'Error saving');
            setTimeout(function() { setStatus(field, '', ''); }, 3000);
        });
    }

    const homePageSelect = document.getElementById('home_page_select');
    homePageSelect.dataset.original = homePageSelect.value;
    homePageSelect.addEventListener('blur', function() {
        if (this.value !== this.dataset.original) {
            ajaxSave('home_page', this.value).then(function(data) {
                if (data && data.success) {
                    homePageSelect.dataset.original = homePageSelect.value;
                }
            });
        }
    });

    document.getElementById('home_page_hide_heading').addEventListener('change', function() {
        ajaxSave('home_page_hide_heading', this.checked ? '1' : '0');
    });

    document.getElementById('home_page_override_title').addEventListener('change', function() {
        ajaxSave('home_page_override_title', this.checked ? '1' : '0');
    });
})();
</script>
<?php
admin_layout(__t('home_page'), ob_get_clean());
