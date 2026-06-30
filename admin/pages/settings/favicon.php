<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\FileManager;
use Elementary\HtmlCache;

$currentFavicon = (string) setting('favicon_filename', '');

// Get image files from the library (svg, png, ico, gif, webp)
$imageFiles = Database::fetchAll(
    "SELECT * FROM files WHERE mime_type IN ('image/svg+xml', 'image/png', 'image/x-icon', 'image/gif', 'image/webp') AND deleted_at IS NULL ORDER BY created_at DESC"
);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax && verify_csrf()) {
    header('Content-Type: application/json');

    $field = $_POST['field'] ?? '';

    if ($field === 'favicon_filename') {
        $value = $_POST['value'] ?? '';

        if ($value === '') {
            // Clear the favicon selection (revert to default)
            set_setting('favicon_filename', '');
            $currentFavicon = '';
            HtmlCache::clearAll();
            Logger::log('settings_updated', Auth::user()['id'], 'Favicon cleared (reverted to default)');
            echo json_encode(['success' => true]);
            exit;
        }

        // Validate that the file exists and is an image
        $file = FileManager::getFileByAdminSlug(rawurldecode($value));
        if (!$file || !str_starts_with($file['mime_type'], 'image/')) {
            echo json_encode(['success' => false, 'error' => __t('favicon_invalid_file')]);
            exit;
        }

        set_setting('favicon_filename', $file['filename']);
        $currentFavicon = $file['filename'];
        HtmlCache::clearAll();
        Logger::log('settings_updated', Auth::user()['id'], 'Favicon set to: ' . $file['filename']);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid field']);
    exit;
}

// Find the currently selected file for display
$selectedFile = null;
if ($currentFavicon !== '') {
    $selectedFile = Database::fetch(
        "SELECT * FROM files WHERE filename = ? AND deleted_at IS NULL",
        [$currentFavicon]
    );
}

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('favicon'), 'url' => '#']
]) ?>

<div class="card p-4" style="max-width: 600px;">
    <h2 class="h5 mb-3"><?= __t('favicon') ?></h2>
    <p class="text-muted mb-4"><?= __t('favicon_desc') ?></p>

    <div class="alert alert-info mb-4" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <?= __t('favicon_tip') ?>
    </div>

    <?php if ($selectedFile): ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3">
                <div class="favicon-preview" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                    <img src="<?= esc(file_url($selectedFile['filename'])) ?>" alt="<?= __t('current_favicon') ?>" style="max-width: 32px; max-height: 32px;">
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= esc(__t('current_favicon')) ?></div>
                    <div class="text-muted small"><?= esc($selectedFile['title'] ?? $selectedFile['filename']) ?></div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-favicon">
                    <i class="bi bi-x-circle me-1"></i> <?= __t('remove') ?>
                </button>
            </div>
            <div id="favicon_status" class="form-text mt-2"></div>
        </div>
    </div>
    <?php else: ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3">
                <div class="favicon-preview" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: #1a1a1a; color: #f5f5f0; font-size: 1.5rem; font-weight: 700; border-radius: 4px;">
                    <?= esc(strtoupper(mb_substr(Config::get('site_name', 'E'), 0, 1))) ?>
                </div>
                <div>
                    <div class="fw-semibold"><?= esc(__t('current_favicon')) ?></div>
                    <div class="text-muted small"><?= __t('favicon_default') ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label" for="favicon_select"><?= __t('favicon_select') ?></label>
        <select id="favicon_select" class="form-select">
            <option value=""><?= __t('favicon_select_placeholder') ?></option>
            <?php foreach ($imageFiles as $file): ?>
            <option value="<?= rawurlencode(FileManager::getFileAdminSlug($file)) ?>" <?= ($file['filename'] === $currentFavicon) ? 'selected' : '' ?>>
                <?= esc($file['title'] ?? $file['filename']) ?>
                <?php if ($file['mime_type'] === 'image/svg+xml'): ?>
                (SVG)
                <?php elseif ($file['mime_type'] === 'image/png'): ?>
                (PNG)
                <?php elseif ($file['mime_type'] === 'image/x-icon'): ?>
                (ICO)
                <?php elseif ($file['mime_type'] === 'image/webp'): ?>
                (WebP)
                <?php elseif ($file['mime_type'] === 'image/gif'): ?>
                (GIF)
                <?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div id="favicon_select_status" class="form-text"></div>
        <div class="form-text">
            <a href="<?= admin_url('files') ?>" target="_blank"><?= __t('upload_new_file') ?></a>
        </div>
    </div>
</div>

<script>
(function() {
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const baseUrl = <?= json_encode(admin_url('settings/favicon')) ?>;
    const hasSelected = <?= $currentFavicon !== '' ? 'true' : 'false' ?>;

    function setStatus(field, state, message) {
        const statusEl = document.getElementById(field + '_status');
        if (!statusEl) return;
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
                setTimeout(function() { window.location.reload(); }, 800);
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

    const select = document.getElementById('favicon_select');
    select.addEventListener('change', function() {
        if (this.value === '') {
            // No selection - do nothing (use clear button instead)
            return;
        }
        ajaxSave('favicon_filename', this.value);
    });

    const clearBtn = document.getElementById('btn-clear-favicon');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            ajaxSave('favicon_filename', '');
        });
    }
})();
</script>

<?php
admin_layout(__t('favicon'), ob_get_clean());
