<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\Auth;
use Elementary\Config;
use Elementary\FileManager;

$action = $segments[1] ?? 'index';
$identifier = isset($segments[2]) ? rawurldecode($segments[2]) : null;
$id = null;

if ($identifier !== null && $action !== 'new') {
    $resolvedFile = FileManager::getFileByAdminSlug($identifier);
    if (!$resolvedFile) {
        throw new RuntimeException('File not found', 404);
    }
    $id = (int) $resolvedFile['id'];
}

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    FileManager::deleteFile($id);
    flash('success', __t('file_deleted'));
    redirect(admin_url('files'));
}

if ($action === 'edit' && $id) {
    $file = FileManager::getFile($id);
    if (!$file) {
        flash('error', __t('file_not_found'));
        redirect(admin_url('files'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'title'       => trim($_POST['title'] ?? ''),
            'alt_text'    => $_POST['alt_text'] ?? '',
            'description' => $_POST['description'] ?? '',
        ];
        $result = FileManager::saveFile($data, $id);
        if ($result['success']) {
            flash('success', __t('file_saved'));
            redirect(admin_url('files'));
        }
        flash('error', $result['error']);
        $file = array_merge($file, $data);
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('files'), 'url' => admin_url('files')],
        ['label' => __t('edit_file'), 'url' => '#'],
    ]) ?>
    <?= admin_flash() ?>
    <form method="post" class="card p-4" style="max-width: 600px;">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label"><?= __t('filename') ?></label>
            <input type="text" class="form-control" value="<?= esc($file['filename']) ?>" readonly disabled>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('file_url') ?></label>
            <input type="text" class="form-control" value="<?= esc(file_url($file['filename'])) ?>" readonly disabled>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('title') ?></label>
            <input type="text" name="title" class="form-control" required value="<?= esc($file['title'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('alt_text') ?></label>
            <input type="text" name="alt_text" class="form-control" value="<?= esc($file['alt_text'] ?? '') ?>" placeholder="<?= __t('alt_text_placeholder') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('description') ?></label>
            <textarea name="description" class="form-control" rows="3"><?= esc($file['description'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
        <a href="<?= esc(file_url($file['filename'])) ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener"><?= __t('view') ?></a>
        <?= post_action_form(
            admin_entity_url('files', 'delete', FileManager::getFileAdminSlug($file)),
            __t('delete'),
            __t('confirm_delete'),
            'btn btn-outline-danger'
        ) ?>
        <a href="<?= admin_url('files') ?>" class="btn btn-outline-secondary"><?= __t('cancel') ?></a>
    </form>
    <?php
    admin_layout(__t('edit_file'), ob_get_clean());
    return;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['upload'])) {
    $user = Auth::user();
    $result = FileManager::upload($_FILES['upload'], (int) $user['id']);
    if ($isAjax) {
        header('Content-Type: application/json');
        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => __t('file_uploaded')]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
        exit;
    }
    if ($result['success']) {
        flash('success', __t('file_uploaded'));
    } else {
        flash('error', $result['error']);
    }
    redirect(admin_url('files'));
}

$files = FileManager::getFiles();
$allowedTypes = Config::get('file_types', ['png', 'jpg', 'jpeg', 'pdf']);
$maxSize = Config::get('file_size', '256M');
$acceptTypes = !empty($allowedTypes) ? '.' . implode(',.', array_map('strtolower', (array) $allowedTypes)) : '';

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('files'), 'url' => admin_url('files')],
]) ?>
<div class="d-flex justify-content-end mb-4">
    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-upload"></i> <?= __t('upload') ?>
    </button>
</div>
<?= admin_flash() ?>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel"><?= __t('upload_file') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="uploadInput"><?= __t('choose_file') ?></label>
                        <input type="file" name="upload" id="uploadInput" class="form-control" required<?= $acceptTypes ? ' accept="' . esc($acceptTypes) . '"' : '' ?>>
                        <div class="form-text">
                            <?php if (!empty($allowedTypes)): ?>
                                <?= __t('allowed_file_types', ['types' => implode(', ', (array) $allowedTypes)]) ?>
                            <?php else: ?>
                                <?= __t('all_file_types_allowed') ?>
                            <?php endif; ?>
                            <?php if ($maxSize !== '' && $maxSize !== null): ?>
                                · <?= __t('max_file_size', ['size' => $maxSize]) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 25px; display: none;" id="uploadProgressContainer">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="uploadProgressBar" style="width: 0%"></div>
                    </div>
                    <div id="uploadStatus" class="small"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                <button type="button" class="btn btn-dark" id="uploadBtn"><?= __t('upload') ?></button>
            </div>
        </div>
    </div>
</div>

<style>
    .file-preview-cell {
        width: 64px;
        padding: 8px !important;
    }
    .file-preview-img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }
    .file-preview-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        color: #6c757d;
    }
    .file-title {
        font-weight: 500;
    }
    .file-filename {
        font-size: 0.82em;
        color: #6c757d;
    }
    .file-date {
        font-weight: 500;
    }
    .file-user {
        font-size: 0.82em;
        color: #6c757d;
    }
    .file-size {
        font-size: 0.82em;
        color: #6c757d;
    }
    .file-row {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    .file-row:hover {
        background-color: #f8f9fa !important;
    }
    .file-row:hover td {
        color: unset;
    }
</style>

<div class="card">
    <?php if (empty($files)): ?>
    <div class="p-4 text-center text-muted"><?= __t('no_files_yet') ?></div>
    <?php else: ?>
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th style="width: 64px;"></th>
                <th><?= __t('file') ?></th>
                <th><?= __t('uploaded') ?></th>
                <th><?= __t('size') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($files as $f): ?>
            <?php
            $isImage = isset($f['mime_type']) && str_starts_with($f['mime_type'], 'image/');
            $fileUrl = file_url($f['filename']);
            ?>
            <tr class="file-row" onclick="window.location.href='<?= esc(admin_entity_url('files', 'edit', admin_file_slug($f['filename']))) ?>'">
                <td class="file-preview-cell text-center">
                    <?php if ($isImage): ?>
                        <img src="<?= esc($fileUrl) ?>" alt="<?= esc($f['title']) ?>" class="file-preview-img" loading="lazy">
                    <?php else: ?>
                        <span class="file-preview-icon">
                            <i class="bi bi-file-earmark fs-4"></i>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="file-title"><?= esc($f['title']) ?></div>
                    <div class="file-filename"><?= esc($f['filename']) ?></div>
                </td>
                <td>
                    <div class="file-date"><?= format_date($f['created_at'], Auth::getUserTimezone()) ?></div>
                    <div class="file-user"><?= esc($f['uploader_name'] ?? '') ?></div>
                </td>
                <td>
                    <div class="file-size"><?= esc(FileManager::formatFileSize((int) $f['size'])) ?></div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
(function() {
    var uploadBtn = document.getElementById('uploadBtn');
    var uploadForm = document.getElementById('uploadForm');
    var uploadInput = document.getElementById('uploadInput');
    var progressContainer = document.getElementById('uploadProgressContainer');
    var progressBar = document.getElementById('uploadProgressBar');
    var uploadStatus = document.getElementById('uploadStatus');
    var modalEl = document.getElementById('uploadModal');

    uploadBtn.addEventListener('click', function() {
        if (!uploadInput.files.length) {
            uploadInput.focus();
            return;
        }
        uploadFile();
    });

    // Also allow pressing Enter on the file input to trigger upload
    uploadInput.addEventListener('change', function() {
        if (this.files.length) {
            uploadFile();
        }
    });

    // Reset modal on close
    modalEl.addEventListener('hidden.bs.modal', function() {
        uploadForm.reset();
        progressContainer.style.display = 'none';
        progressBar.style.width = '0%';
        uploadStatus.innerHTML = '';
        uploadBtn.disabled = false;
        uploadBtn.textContent = '<?= __t('upload') ?>';
    });

    function uploadFile() {
        var file = uploadInput.files[0];
        if (!file) return;

        uploadBtn.disabled = true;
        uploadBtn.textContent = '...';
        uploadStatus.innerHTML = '';
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.classList.add('progress-bar-animated');

        var formData = new FormData();
        formData.append('upload', file);
        formData.append('csrf_token', uploadForm.querySelector('input[name="csrf_token"]').value);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
            }
        });

        xhr.addEventListener('load', function() {
            progressContainer.style.display = 'none';
            progressBar.classList.remove('progress-bar-animated');

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        uploadStatus.innerHTML = '<div class="text-success">' + response.message + '</div>';
                        // Close modal after a short delay, then reload page
                        setTimeout(function() {
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            modal.hide();
                            window.location.reload();
                        }, 800);
                    } else {
                        uploadStatus.innerHTML = '<div class="text-danger">' + (response.error || 'Upload failed') + '</div>';
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = '<?= __t('upload') ?>';
                    }
                } catch(e) {
                    uploadStatus.innerHTML = '<div class="text-danger">Upload failed.</div>';
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = '<?= __t('upload') ?>';
                }
            } else {
                uploadStatus.innerHTML = '<div class="text-danger">Server error (' + xhr.status + ').</div>';
                uploadBtn.disabled = false;
                uploadBtn.textContent = '<?= __t('upload') ?>';
            }
        });

        xhr.addEventListener('error', function() {
            progressContainer.style.display = 'none';
            uploadStatus.innerHTML = '<div class="text-danger">Network error.</div>';
            uploadBtn.disabled = false;
            uploadBtn.textContent = '<?= __t('upload') ?>';
        });

        xhr.send(formData);
    }
})();
</script>
<?php
admin_layout(__t('files'), ob_get_clean());
