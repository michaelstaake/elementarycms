<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$action = $segments[1] ?? 'index';
$identifier = isset($segments[2]) ? rawurldecode($segments[2]) : null;
$id = null;

if ($identifier !== null && $action !== 'new') {
    $resolvedCategory = ContentManager::resolveCategoryAdminIdentifier($identifier);
    if (!$resolvedCategory) {
        throw new RuntimeException('Category not found', 404);
    }
    $id = (int) $resolvedCategory['id'];
}

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = ContentManager::deleteCategory($id);
    if ($result['success']) {
        flash('success', __t('category_deleted'));
    } else {
        flash('error', $result['error']);
    }
    redirect(admin_url('categories'));
}

if ($action === 'edit' || $action === 'new') {
    $category = $id ? Database::fetch('SELECT * FROM categories WHERE id = ?', [$id]) : null;
    if ($id && $category && $category['slug'] === 'uncategorized') {
        flash('error', __t('category_cannot_edit_uncategorized'));
        redirect(admin_url('categories'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'name'             => trim($_POST['name'] ?? ''),
            'description'      => $_POST['description'] ?? '',
            'featured_image'   => trim($_POST['featured_image'] ?? '') ?: null,
            'meta_keywords'    => $_POST['meta_keywords'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
        ];
        $result = ContentManager::saveCategory($data, $id);
        if ($result['success']) {
            flash('success', __t('category_saved'));
            redirect(admin_entity_url('categories', 'edit', $result['slug']));
        }
        flash('error', $result['error']);
        $category = array_merge($category ?? [], $data);
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('categories'), 'url' => admin_url('categories')],
        ['label' => $id ? __t('edit_category') : __t('new_category'), 'url' => '#']
    ]) ?>
    <?= admin_flash() ?>
    <form method="post" id="categoryForm">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card p-4">
                    <div class="mb-3">
                        <label class="form-label"><?= __t('name') ?></label>
                        <input type="text" name="name" class="form-control" required value="<?= esc($category['name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __t('description') ?></label>
                        <textarea name="description" class="form-control" rows="3"><?= esc($category['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __t('meta_keywords') ?></label>
                        <input type="text" name="meta_keywords" class="form-control" value="<?= esc($category['meta_keywords'] ?? '') ?>" placeholder="<?= __t('meta_keywords_placeholder') ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= __t('meta_description') ?></label>
                        <textarea name="meta_description" class="form-control" rows="2" placeholder="<?= __t('meta_description_placeholder') ?>"><?= esc($category['meta_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <button type="button" class="card-header py-3 d-flex justify-content-between align-items-center collapsed" data-bs-toggle="collapse" data-bs-target="#categoryFeaturedImageCollapse" aria-expanded="false" style="background: none; border: none; cursor: pointer; text-align: left; width: 100%;">
                        <span class="fw-semibold mb-0"><i class="bi bi-image me-2"></i><?= __t('featured_image') ?></span>
                        <i class="bi bi-chevron-down collapse-toggle-icon"></i>
                    </button>
                    <div id="categoryFeaturedImageCollapse" class="collapse">
                        <div class="card-body">
                            <div class="mb-3 text-center" id="categoryFeaturedImagePreview">
                                <?php if (!empty($category['featured_image'])): ?>
                                    <img src="<?= esc(file_url($category['featured_image'])) ?>" alt="" class="featured-image-preview-img mb-2">
                                    <input type="hidden" name="featured_image" value="<?= esc($category['featured_image']) ?>">
                                <?php else: ?>
                                    <span class="text-muted"><?= __t('no_featured_image') ?></span>
                                    <input type="hidden" name="featured_image" value="">
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryImageSelectorModal">
                                    <i class="bi bi-folder2-open"></i> <?= __t('select_image') ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryUploadImageModal">
                                    <i class="bi bi-cloud-upload"></i> <?= __t('upload_image') ?>
                                </button>
                                <?php if (!empty($category['featured_image'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('<?= __t('remove_featured_image') ?>')){document.getElementById('categoryFeaturedImagePreview').innerHTML='<span class=\'text-muted\'><?= __t('no_featured_image') ?></span><input type=\'hidden\' name=\'featured_image\' value=\'\';">}">
                                    <i class="bi bi-trash"></i> <?= __t('remove') ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
            <a href="<?= admin_url('categories') ?>" class="btn btn-outline-secondary"><?= __t('cancel') ?></a>
        </div>
    </form>

    <div class="modal fade" id="categoryImageSelectorModal" tabindex="-1" aria-labelledby="categoryImageSelectorLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryImageSelectorLabel"><?= __t('select_image') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3" id="categoryImageSelectorGrid">
                        <?php
                        $allFiles = FileManager::getFiles();
                        $hasImages = false;
                        foreach ($allFiles as $f):
                            $isImage = isset($f['mime_type']) && str_starts_with($f['mime_type'], 'image/');
                            if (!$isImage) {
                                continue;
                            }
                            $hasImages = true;
                            $fileUrl = file_url($f['filename']);
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100 category-image-option" onclick="selectCategoryImage(<?= json_encode($f['filename']) ?>, <?= json_encode($fileUrl) ?>)" style="cursor: pointer;">
                                <img src="<?= esc($fileUrl) ?>" alt="<?= esc($f['title']) ?>" class="img-fluid" style="height: 100px; object-fit: cover;">
                                <div class="card-body p-2 text-center">
                                    <small class="text-truncate d-block"><?= esc($f['title']) ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$hasImages): ?>
                        <div class="col-12 text-center text-muted py-4"><?= __t('no_files_uploaded') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="categoryUploadImageModal" tabindex="-1" aria-labelledby="categoryUploadImageLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryUploadImageLabel"><?= __t('upload_image') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="categoryUploadImageForm" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="categoryUploadInput"><?= __t('choose_image') ?></label>
                            <input type="file" name="upload" id="categoryUploadInput" class="form-control" accept="image/*" required>
                            <div class="form-text"><?= __t('image_types_allowed') ?></div>
                        </div>
                        <div class="progress mb-2" style="height: 25px; display: none;" id="categoryUploadProgressContainer">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="categoryUploadProgressBar" style="width: 0%"></div>
                        </div>
                        <div id="categoryUploadStatus" class="small"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="button" class="btn btn-dark" id="categoryUploadBtn"><?= __t('upload') ?></button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .featured-image-preview-img {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }
    </style>
    <script>
    (function () {
        window.selectCategoryImage = function(filename, url) {
            const preview = document.getElementById('categoryFeaturedImagePreview');
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.src = url;
            img.alt = '';
            img.className = 'featured-image-preview-img mb-2';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'featured_image';
            input.value = filename;
            preview.appendChild(img);
            preview.appendChild(input);
            bootstrap.Modal.getInstance(document.getElementById('categoryImageSelectorModal')).hide();
        };

        const categoryUploadBtn = document.getElementById('categoryUploadBtn');
        const categoryUploadInput = document.getElementById('categoryUploadInput');
        const categoryProgressContainer = document.getElementById('categoryUploadProgressContainer');
        const categoryProgressBar = document.getElementById('categoryUploadProgressBar');
        const categoryUploadStatus = document.getElementById('categoryUploadStatus');

        function uploadCategoryImage() {
            const formData = new FormData(document.getElementById('categoryUploadImageForm'));
            categoryProgressContainer.style.display = 'block';
            categoryUploadStatus.textContent = '';
            categoryUploadBtn.disabled = true;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?= admin_url('files/upload-ajax') ?>', true);

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    categoryProgressBar.style.width = percent + '%';
                    categoryProgressBar.textContent = percent + '%';
                }
            });

            xhr.addEventListener('load', function() {
                categoryUploadBtn.disabled = false;
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        categoryUploadStatus.className = 'text-success';
                        categoryUploadStatus.textContent = '<?= __t('file_uploaded') ?>';
                        selectCategoryImage(response.filename, response.url);
                        categoryUploadInput.value = '';
                        categoryProgressBar.style.width = '0%';
                        categoryProgressBar.textContent = '';
                        setTimeout(function() {
                            bootstrap.Modal.getInstance(document.getElementById('categoryUploadImageModal')).hide();
                        }, 500);
                    } else {
                        categoryUploadStatus.className = 'text-danger';
                        categoryUploadStatus.textContent = response.error || '<?= __t('upload_failed') ?>';
                    }
                } else {
                    categoryUploadStatus.className = 'text-danger';
                    categoryUploadStatus.textContent = '<?= __t('upload_failed') ?>';
                }
            });

            xhr.addEventListener('error', function() {
                categoryUploadBtn.disabled = false;
                categoryUploadStatus.className = 'text-danger';
                categoryUploadStatus.textContent = '<?= __t('upload_failed') ?>';
            });

            xhr.send(formData);
        }

        categoryUploadBtn.addEventListener('click', function() {
            if (!categoryUploadInput.files.length) {
                categoryUploadInput.focus();
                return;
            }
            uploadCategoryImage();
        });

        categoryUploadInput.addEventListener('change', function() {
            if (this.files.length) {
                uploadCategoryImage();
            }
        });
    })();
    </script>
    <?php
    admin_layout($id ? __t('edit_category') : __t('new_category'), ob_get_clean());
    return;
}

$categories = ContentManager::getCategories();

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('categories'), 'url' => admin_url('categories')]
]) ?>
<div class="d-flex justify-content-end mb-4">
    <a href="<?= admin_url('categories/new') ?>" class="btn btn-dark"><i class="bi bi-plus"></i> <?= __t('new_category') ?></a>
</div>
<?= admin_flash() ?>
<div class="card">
    <table class="table mb-0">
        <thead><tr><th><?= __t('name') ?></th><th><?= __t('slug') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= esc($c['name']) ?></td>
                <td><code><?= esc($c['slug']) ?></code></td>
                <td class="text-end">
                    <a href="<?= admin_url('posts/new') ?>?category_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><?= __t('new_post') ?></a>
                    <?php if ($c['slug'] !== 'uncategorized'): ?>
                        <a href="<?= admin_entity_url('categories', 'edit', $c['slug']) ?>" class="btn btn-sm btn-outline-dark"><?= __t('edit') ?></a>
                        <?= post_action_form(
                            admin_entity_url('categories', 'delete', $c['slug']),
                            __t('delete'),
                            __t('confirm_delete'),
                            'btn btn-sm btn-outline-danger'
                        ) ?>
                    <?php else: ?>
                        <span class="text-muted"><?= __t('default_category') ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
admin_layout(__t('categories'), ob_get_clean());
