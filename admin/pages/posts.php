<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$action = $segments[1] ?? 'index';
$identifier = isset($segments[2]) ? rawurldecode($segments[2]) : null;
$authorFilter = Auth::canManageAllPosts() ? null : Auth::user()['id'];
$id = null;

if ($identifier !== null && $action !== 'new') {
    $resolvedPost = ContentManager::resolvePostAdminIdentifier($identifier);
    if (!$resolvedPost) {
        throw new RuntimeException('Post not found', 404);
    }
    $id = (int) $resolvedPost['id'];
}

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = ContentManager::getPost($id);
    if ($post && (Auth::canManageAllPosts() || (int) $post['author_id'] === (int) Auth::user()['id'])) {
        ContentManager::deletePost($id);
        flash('success', __t('post_deleted'));
    }
    redirect(admin_url('posts'));
}

if ($action === 'versions' && $id) {
    $post = ContentManager::getPost($id);
    if (!$post || (!Auth::canManageAllPosts() && (int) $post['author_id'] !== (int) Auth::user()['id'])) {
        throw new RuntimeException('Forbidden', 403);
    }
    $postSlug = $post['slug'];
    $versions = ContentManager::getPostVersions($id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
        $result = ContentManager::restorePostVersion($id, (int) $_POST['restore'], Auth::user()['id']);
        flash($result['success'] ? 'success' : 'error', $result['success'] ? __t('version_restored') : $result['error']);
        redirect(admin_entity_url('posts', 'versions', $postSlug));
    }

    // Build a map so each version can find its predecessor for inline diff
    $versionsMap = [];
    foreach ($versions as $v) {
        $versionsMap[(int) $v['version_number']] = $v;
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('posts'), 'url' => admin_url('posts')],
        ['label' => esc($post['title']), 'url' => admin_entity_url('posts', 'edit', $postSlug)],
        ['label' => __t('versions'), 'url' => '#']
    ]) ?>
    <?= admin_flash() ?>

    <!-- Versions Accordion -->
    <div class="card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?= __t('versions') ?></span>
            <span class="badge bg-secondary"><?= count($versions) ?></span>
        </div>
        <div class="accordion accordion-flush" id="postVersionsAccordion">
        <?php foreach ($versions as $idx => $v):
            $vn = (int) $v['version_number'];
            $prev = $versionsMap[$vn - 1] ?? null;
            $collapseId = 'versionCollapse' . $v['id'];
            $hasDiff = $prev ? ($v['title'] !== $prev['title'] || $v['content'] !== $prev['content'] || ($v['excerpt'] ?? '') !== ($prev['excerpt'] ?? '')) : true;
        ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="versionHeader<?= $v['id'] ?>">
                    <button class="accordion-button collapsed version-accordion-btn" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
                        <div class="row align-items-center w-100">
                            <div class="col-auto">
                                <strong><?= $vn ?></strong>
                                <?= $v['is_draft'] ? ' <span class="badge bg-secondary">' . __t('draft') . '</span>' : '' ?>
                            </div>
                            <div class="col-6"><?= esc($v['title']) ?></div>
                            <div class="col-3"><?= esc($v['author_name'] ?? '') ?></div>
                            <div class="col-3"><?= format_date($v['created_at'], Auth::getUserTimezone()) ?></div>
                        </div>
                    </button>
                </h2>
                <div id="<?= $collapseId ?>" class="accordion-collapse collapse" aria-labelledby="versionHeader<?= $v['id'] ?>" data-bs-parent="#postVersionsAccordion">
                    <div class="accordion-body">
                        <!-- Actions Row -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">
                                <i class="bi bi-code-slash me-1"></i>
                                <?= $prev ? __t('diff_vs_previous') : __t('diff_initial_version') ?>
                                (v<?= $vn ?><?= $prev ? ' ← v' . (int) $prev['version_number'] : '' ?>)
                            </span>
                            <?= post_action_form(
                                admin_entity_url('posts', 'versions', $postSlug),
                                __t('restore'),
                                __t('confirm_restore'),
                                'btn btn-sm btn-dark',
                                ['restore' => (string) $v['id']]
                            ) ?>
                        </div>

                        <?php if (!$hasDiff): ?>
                        <div class="text-center text-muted py-3"><?= __t('diff_no_changes') ?></div>
                        <?php else: ?>
                            <!-- Title Diff -->
                            <?php if ($prev && $prev['title'] !== $v['title']): ?>
                            <div class="mb-3">
                                <h6 class="fw-semibold mb-2"><i class="bi bi-type-h2 me-1"></i><?= __t('diff_title_changes') ?></h6>
                                <div class="diff-container">
                                    <table class="diff-table"><tbody>
                                        <tr class="diff-row diff-row-remove">
                                            <td class="diff-line-num diff-line-num-removed">1</td>
                                            <td class="diff-line-num"></td>
                                            <td class="diff-line-content diff-line-content-removed"><span class="diff-line-prefix">-</span><?= esc($prev['title']) ?></td>
                                        </tr>
                                        <tr class="diff-row diff-row-add">
                                            <td class="diff-line-num"></td>
                                            <td class="diff-line-num diff-line-num-added">1</td>
                                            <td class="diff-line-content diff-line-content-added"><span class="diff-line-prefix">+</span><?= esc($v['title']) ?></td>
                                        </tr>
                                    </tbody></table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Excerpt Diff -->
                            <?php if ($prev && ($v['excerpt'] ?? '') !== ($prev['excerpt'] ?? '')): ?>
                            <div class="mb-3">
                                <h6 class="fw-semibold mb-2"><i class="bi bi-text-paragraph me-1"></i><?= __t('diff_excerpt_changes') ?></h6>
                                <?= diff_html($prev['excerpt'] ?? '', $v['excerpt'] ?? '') ?>
                            </div>
                            <?php endif; ?>

                            <!-- Content Diff -->
                            <?php if ($prev && $prev['content'] !== $v['content']): ?>
                            <div class="mb-3">
                                <h6 class="fw-semibold mb-2"><i class="bi bi-file-richtext me-1"></i><?= __t('diff_content_changes') ?></h6>
                                <?= diff_html($prev['content'], $v['content']) ?>
                            </div>
                            <?php endif; ?>

                            <!-- Initial version: show content as-is -->
                            <?php if (!$prev): ?>
                            <div class="mb-3">
                                <h6 class="fw-semibold mb-2"><i class="bi bi-file-richtext me-1"></i><?= __t('diff_content_changes') ?></h6>
                                <div class="diff-container">
                                    <table class="diff-table"><tbody>
                                    <?php
                                    $contentLines = explode("\n", $v['content']);
                                    if (count($contentLines) > 0 && $contentLines[count($contentLines) - 1] === '') array_pop($contentLines);
                                    foreach ($contentLines as $ln => $line):
                                        $num = $ln + 1;
                                    ?>
                                        <tr class="diff-row diff-row-add">
                                            <td class="diff-line-num"></td>
                                            <td class="diff-line-num diff-line-num-added"><?= $num ?></td>
                                            <td class="diff-line-content diff-line-content-added"><span class="diff-line-prefix">+</span><?= esc($line) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody></table>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($versions)): ?>
    <div class="card mt-3">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-clock-history" style="font-size: 2rem;"></i>
            <p class="mt-3 mb-0"><?= __t('no_previous_version') ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php
    admin_layout(__t('versions'), ob_get_clean());
    return;
}

if ($action === 'edit' || $action === 'new') {
    $post = $id ? ContentManager::getPost($id) : null;
    if ($post && !Auth::canManageAllPosts() && (int) $post['author_id'] !== (int) Auth::user()['id']) {
        throw new RuntimeException('Forbidden', 403);
    }
    $categories = ContentManager::getCategories();
    $postCategories = $id ? array_column(ContentManager::getPostCategories($id), 'id') : [];
    $postTags = $id ? array_column(ContentManager::getPostTags($id), 'name') : [];
    $postTagsString = $id ? implode(', ', $postTags) : '';
    if ($action === 'new' && isset($_GET['category_id'])) {
        $postCategories = [(int) $_GET['category_id']];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => Validator::slug($_POST['slug'] ?: $_POST['title'] ?? ''),
            'content'          => $_POST['content'] ?? '',
            'excerpt'          => $_POST['excerpt'] ?? '',
            'featured_image'   => trim($_POST['featured_image'] ?? '') ?: null,
            'meta_keywords'    => $_POST['meta_keywords'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'status'           => $_POST['status'] ?? 'draft',
            'categories'       => $_POST['categories'] ?? [],
            'tags'             => $_POST['tags'] ?? '',
        ];
        $result = ContentManager::savePost($data, $id, Auth::user()['id']);
        if ($result['success']) {
            $postId = $result['id'];
            flash('success', __t('post_saved'));
            $savedPost = ContentManager::getPost($postId);
            redirect(admin_entity_url('posts', 'edit', $savedPost['slug']));
        }
        flash('error', $result['error']);
        $post = array_merge($post ?? [], $data);
        $postCategories = $data['categories'];
        $postTagsString = $data['tags'] ?? '';
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('posts'), 'url' => admin_url('posts')],
        ['label' => $id ? esc($post['title'] ?? '') : __t('new_post'), 'url' => '#']
    ]) ?>
    <?= admin_flash() ?>
    <form method="post" id="postForm">
        <?= csrf_field() ?>

        <!-- Title & Save Row -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-9">
                        <input type="text" name="title" id="postTitle" class="form-control form-control-lg" required placeholder="<?= esc(__t('title')) ?>" value="<?= esc($post['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-dark btn-lg w-100"><?= __t('save') ?></button>
                        <small id="postDraftStatus" class="text-muted d-none d-block mt-1 text-center"></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collapsible Side Panels -->
        <div class="row mb-3">
            <!-- Post Settings Panel -->
            <div class="col-md-4">
                <div class="card">
                    <button type="button" class="card-header py-3 d-flex justify-content-between align-items-center collapsed" data-bs-toggle="collapse" data-bs-target="#postSettingsCollapse" aria-expanded="false" style="background: none; border: none; cursor: pointer; text-align: left; width: 100%;">
                        <span class="fw-semibold mb-0"><i class="bi bi-gear me-2"></i><?= __t('post_settings') ?></span>
                        <i class="bi bi-chevron-down collapse-toggle-icon"></i>
                    </button>
                    <div id="postSettingsCollapse" class="collapse">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><?= __t('slug') ?></label>
                                <input type="text" name="slug" class="form-control" value="<?= esc($post['slug'] ?? '') ?>" placeholder="<?= __t('auto_generated') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __t('status') ?></label>
                                <select name="status" class="form-select">
                                    <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>><?= __t('draft') ?></option>
                                    <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= __t('published') ?></option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __t('categories') ?></label>
                                <?php foreach ($categories as $cat): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" id="cat<?= $cat['id'] ?>" <?= in_array($cat['id'], $postCategories) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="cat<?= $cat['id'] ?>"><?= esc($cat['name']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mb-0">
                                <label class="form-label"><?= __t('tags') ?></label>
                                <input type="text" name="tags" class="form-control" value="<?= esc($postTagsString) ?>" placeholder="<?= __t('tags_placeholder') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Featured Image Panel -->
            <div class="col-md-4">
                <div class="card">
                    <button type="button" class="card-header py-3 d-flex justify-content-between align-items-center collapsed" data-bs-toggle="collapse" data-bs-target="#postFeaturedImageCollapse" aria-expanded="false" style="background: none; border: none; cursor: pointer; text-align: left; width: 100%;">
                        <span class="fw-semibold mb-0"><i class="bi bi-image me-2"></i><?= __t('featured_image') ?></span>
                        <i class="bi bi-chevron-down collapse-toggle-icon"></i>
                    </button>
                    <div id="postFeaturedImageCollapse" class="collapse">
                        <div class="card-body">
                            <div class="mb-3 text-center" id="postFeaturedImagePreview">
                                <?php if (!empty($post['featured_image'])): ?>
                                    <img src="<?= esc(file_url($post['featured_image'])) ?>" alt="" class="featured-image-preview-img mb-2">
                                    <input type="hidden" name="featured_image" value="<?= esc($post['featured_image']) ?>">
                                <?php else: ?>
                                    <span class="text-muted"><?= __t('no_featured_image') ?></span>
                                    <input type="hidden" name="featured_image" value="">
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#postImageSelectorModal">
                                    <i class="bi bi-folder2-open"></i> <?= __t('select_image') ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#postUploadImageModal">
                                    <i class="bi bi-cloud-upload"></i> <?= __t('upload_image') ?>
                                </button>
                                <?php if (!empty($post['featured_image'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('<?= __t('remove_featured_image') ?>')){document.getElementById('postFeaturedImagePreview').innerHTML='<span class=\'text-muted\'><?= __t('no_featured_image') ?></span><input type=\'hidden\' name=\'featured_image\' value=\'\';">}">
                                    <i class="bi bi-trash"></i> <?= __t('remove') ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO Settings Panel -->
            <div class="col-md-4">
                <div class="card">
                    <button type="button" class="card-header py-3 d-flex justify-content-between align-items-center collapsed" data-bs-toggle="collapse" data-bs-target="#postSeoSettingsCollapse" aria-expanded="false" style="background: none; border: none; cursor: pointer; text-align: left; width: 100%;">
                        <span class="fw-semibold mb-0"><i class="bi bi-search me-2"></i><?= __t('seo_settings') ?></span>
                        <i class="bi bi-chevron-down collapse-toggle-icon"></i>
                    </button>
                    <div id="postSeoSettingsCollapse" class="collapse">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><?= __t('meta_keywords') ?></label>
                                <input type="text" name="meta_keywords" class="form-control" value="<?= esc($post['meta_keywords'] ?? '') ?>" placeholder="<?= __t('meta_keywords_placeholder') ?>">
                            </div>
                            <div class="mb-0">
                                <label class="form-label"><?= __t('meta_description') ?></label>
                                <textarea name="meta_description" class="form-control" rows="3" placeholder="<?= __t('meta_description_placeholder') ?>"><?= esc($post['meta_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Excerpt Row -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-0">
                    <label class="form-label"><?= __t('excerpt') ?></label>
                    <textarea name="excerpt" class="form-control" rows="2" placeholder="<?= esc(__t('excerpt_placeholder') ?? 'Brief summary of this post') ?>"><?= esc($post['excerpt'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-0">
                    <label class="form-label"><?= __t('content') ?></label>
                    <textarea name="content" id="postContent" class="form-control" rows="15"><?= esc($post['content'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </form>

        <!-- Image Selector Modal -->
        <div class="modal fade" id="postImageSelectorModal" tabindex="-1" aria-labelledby="postImageSelectorLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="postImageSelectorLabel"><?= __t('select_image') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3" id="postImageSelectorGrid">
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
                                <div class="card h-100 post-image-option" onclick="selectPostImage(<?= json_encode($f['filename']) ?>, <?= json_encode($fileUrl) ?>)" style="cursor: pointer;">
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

        <!-- Upload Image Modal -->
        <div class="modal fade" id="postUploadImageModal" tabindex="-1" aria-labelledby="postUploadImageLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="postUploadImageLabel"><?= __t('upload_image') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="postUploadImageForm" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="postUploadInput"><?= __t('choose_image') ?></label>
                                <input type="file" name="upload" id="postUploadInput" class="form-control" accept="image/*">
                                <div class="form-text"><?= __t('image_types_allowed') ?></div>
                            </div>
                            <div class="progress mb-2" style="height: 25px; display: none;" id="postUploadProgressContainer">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="postUploadProgressBar" style="width: 0%"></div>
                            </div>
                            <div id="postUploadStatus" class="small"></div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                        <button type="button" class="btn btn-dark" id="postUploadBtn"><?= __t('upload') ?></button>
                    </div>
                </div>
            </div>
        </div>

    <script src="<?= asset('jquery/jquery.min.js') ?>"></script>
    <script src="<?= asset('summernote/summernote-bs5.min.js') ?>"></script>
    <script src="<?= asset('admin/admin-autosave.js') ?>"></script>
    <script>
    // Unsaved changes tracking
    window.postFormDirty = false;

    $(function () {
        $('#postContent').summernote({
            height: 360,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'table', 'hr']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            onContentChange: function() {
                window.postFormDirty = true;
            }
        });

        const form = document.getElementById('postForm');
        let isSubmitting = false;

        form.addEventListener('submit', (e) => {
            $('#postContent').val($('#postContent').summernote('code'));

            const title = document.getElementById('postTitle').value.trim();
            if (!title) {
                e.preventDefault();
                document.getElementById('postTitle').focus();
                return false;
            }
            // Clear unsaved changes flag on successful validation
            window.postFormDirty = false;
        });

        // Track changes on all form inputs
        form.addEventListener('input', () => { window.postFormDirty = true; });
        form.addEventListener('change', () => { window.postFormDirty = true; });

        // Warn before leaving the page with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (window.postFormDirty && !isSubmitting) {
                e.preventDefault();
                e.returnValue = '<?= __t('unsaved_changes') ?>';
                return '<?= __t('unsaved_changes') ?>';
            }
        });

        // Mark as submitting when save button is clicked (before any events fire)
        form.querySelector('button[type="submit"]').addEventListener('click', () => {
            isSubmitting = true;
        });

        // Image selection
        window.selectPostImage = function(filename, url) {
            const preview = document.getElementById('postFeaturedImagePreview');
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
            bootstrap.Modal.getInstance(document.getElementById('postImageSelectorModal')).hide();
        };

        function setPostFeaturedImage(filename, url) {
            selectPostImage(filename, url);
        }

        // Image upload
        var postUploadBtn = document.getElementById('postUploadBtn');
        var postUploadInput = document.getElementById('postUploadInput');
        var postProgressContainer = document.getElementById('postUploadProgressContainer');
        var postProgressBar = document.getElementById('postUploadProgressBar');
        var postUploadStatus = document.getElementById('postUploadStatus');

        postUploadBtn.addEventListener('click', function() {
            if (!postUploadInput.files.length) {
                postUploadInput.focus();
                return;
            }
            uploadPostImage();
        });

        postUploadInput.addEventListener('change', function() {
            if (this.files.length) {
                uploadPostImage();
            }
        });

        function uploadPostImage() {
            var formData = new FormData(document.getElementById('postUploadImageForm'));
            postProgressContainer.style.display = 'block';
            postUploadStatus.textContent = '';
            postUploadBtn.disabled = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?= admin_url('files/upload-ajax') ?>', true);

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    postProgressBar.style.width = percent + '%';
                    postProgressBar.textContent = percent + '%';
                }
            });

            xhr.addEventListener('load', function() {
                postUploadBtn.disabled = false;
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        postUploadStatus.className = 'text-success';
                        postUploadStatus.textContent = '<?= __t('file_uploaded') ?>';
                        setPostFeaturedImage(response.filename, response.url);
                        postUploadInput.value = '';
                        postProgressBar.style.width = '0%';
                        postProgressBar.textContent = '';
                        setTimeout(function() {
                            bootstrap.Modal.getInstance(document.getElementById('postUploadImageModal')).hide();
                        }, 500);
                    } else {
                        postUploadStatus.className = 'text-danger';
                        postUploadStatus.textContent = response.error || '<?= __t('upload_failed') ?>';
                    }
                } else {
                    postUploadStatus.className = 'text-danger';
                    postUploadStatus.textContent = '<?= __t('upload_failed') ?>';
                }
                postProgressContainer.style.display = 'none';
            });

            xhr.addEventListener('error', function() {
                postUploadBtn.disabled = false;
                postUploadStatus.className = 'text-danger';
                postUploadStatus.textContent = '<?= __t('upload_failed') ?>';
                postProgressContainer.style.display = 'none';
            });

            xhr.send(formData);
        }

        AdminAutosave.init({
            entityType: 'post',
            entityId: <?= json_encode($id ? (string) $id : 'new') ?>,
            formSelector: '#postForm',
            serverUpdatedAt: <?= json_encode($post['updated_at'] ?? $post['created_at'] ?? null) ?>,
            statusSelector: '#postDraftStatus',
            keepaliveUrl: <?= json_encode(admin_url('session/keepalive')) ?>,
            strings: {
                title: <?= json_encode(__t('draft_restore_title')) ?>,
                message: <?= json_encode(__t('draft_restore_message')) ?>,
                restore: <?= json_encode(__t('draft_restore')) ?>,
                discard: <?= json_encode(__t('draft_discard')) ?>,
                savedLocally: <?= json_encode(__t('draft_saved_locally')) ?>,
            },
            beforeCollect: function() {
                $('#postContent').val($('#postContent').summernote('code'));
            },
            afterRestore: function(fields) {
                if (fields.content !== undefined) {
                    $('#postContent').summernote('code', fields.content);
                }
            },
            onDirty: function() {
                window.postFormDirty = true;
            },
            onInit: function(api) {
                $('#postContent').on('summernote.change', api.scheduleSave);
            },
        });
    });
    </script>

        <style>
        .collapse-toggle-icon { transition: transform 0.2s ease; transform: rotate(180deg); }
        .card-header.collapsed .collapse-toggle-icon { transform: rotate(0deg); }
        .note-editor.note-frame { border-color: var(--border); }
        .note-toolbar { background: #fff; border-color: var(--border); }
        .note-editing-area { background: #fff; }
        .featured-image-preview-img {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .post-image-option:hover {
            box-shadow: 0 0 0 2px var(--bs-primary);
        }
    </style>

    <!-- Delete & Versions Row -->
    <?php if ($id): ?>
    <?php $postSlug = $post['slug']; ?>
    <div class="card mt-3">
        <div class="card-body py-3">
            <div class="d-flex gap-2">
                <a href="<?= admin_entity_url('posts', 'versions', $postSlug) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> <?= __t('versions') ?></a>
                <?= post_action_form(
                    admin_entity_url('posts', 'delete', $postSlug),
                    __t('delete'),
                    __t('confirm_delete'),
                    'btn btn-outline-danger btn-sm',
                    [],
                    'bi bi-trash'
                ) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
    $summernoteCss = '<link href="' . asset('summernote/summernote-bs5.min.css') . '" rel="stylesheet">';
    admin_layout(
        $id ? ($post['title'] ?? __t('new_post')) : __t('new_post'),
        ob_get_clean(),
        false,
        $summernoteCss
    );
    return;
}

$posts = ContentManager::getPosts($authorFilter);

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('posts'), 'url' => admin_url('posts')]
]) ?>
<div class="d-flex justify-content-end mb-4">
    <div>
        <?php if (Auth::canManageCategories()): ?>
        <a href="<?= admin_url('categories') ?>" class="btn btn-outline-dark me-2"><i class="bi bi-folder2"></i> <?= __t('categories') ?></a>
        <a href="<?= admin_url('tags') ?>" class="btn btn-outline-dark me-2"><i class="bi bi-tags"></i> <?= __t('tags') ?></a>
        <?php endif; ?>
        <a href="<?= admin_url('posts/new') ?>" class="btn btn-dark"><i class="bi bi-plus"></i> <?= __t('new_post') ?></a>
    </div>
</div>
<?= admin_flash() ?>

<style>
    .post-title {
        font-weight: 500;
    }
    .post-slug {
        font-size: 0.82em;
        color: #6c757d;
    }
    .post-date {
        font-weight: 500;
    }
    .post-user {
        font-size: 0.82em;
        color: #6c757d;
    }
    .post-row {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    .post-row:hover {
        background-color: #f8f9fa !important;
    }
    .post-row:hover td {
        color: unset;
    }
    .post-preview-cell {
        width: 64px;
        padding: 8px !important;
    }
    .post-preview-img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }
    .post-preview-icon {
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
</style>

<div class="card">
    <?php if (empty($posts)): ?>
    <div class="p-4 text-center text-muted"><?= __t('no_posts_yet') ?></div>
    <?php else: ?>
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th style="width: 64px;"></th>
                <th><?= __t('post') ?></th>
                <th><?= __t('tags') ?></th>
                <th><?= __t('status') ?></th>
                <th><?= __t('created') ?></th>
                <th><?= __t('updated') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <?php $postTags = ContentManager::getPostTags($p['id']); ?>
            <?php $hasFeatured = !empty($p['featured_image']); ?>
            <tr class="post-row" onclick="window.location.href='<?= esc(admin_entity_url('posts', 'edit', $p['slug'])) ?>'">
                <td class="post-preview-cell text-center">
                    <?php if ($hasFeatured): ?>
                        <img src="<?= esc(file_url($p['featured_image'])) ?>" alt="" class="post-preview-img" loading="lazy">
                    <?php else: ?>
                        <span class="post-preview-icon">
                            <i class="bi bi-file-earmark-post fs-4"></i>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="post-title"><?= esc($p['title']) ?></div>
                    <div class="post-slug"><?= esc($p['slug']) ?></div>
                </td>
                <td>
                    <?php if (empty($postTags)): ?>
                        <span class="text-muted"><?= __t('none') ?></span>
                    <?php else: ?>
                        <?php foreach ($postTags as $i => $tag): ?>
                            <span class="badge bg-secondary me-1"><?= esc($tag['name']) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $p['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>"><?= esc($p['status']) ?></span></td>
                <td>
                    <div class="post-date"><?= format_date($p['created_at'], Auth::getUserTimezone()) ?></div>
                    <div class="post-user"><?= esc($p['author_name'] ?? '') ?></div>
                </td>
                <td>
                    <?php if ($p['updated_at'] && $p['updated_at'] !== $p['created_at']): ?>
                        <div class="post-date"><?= format_date($p['updated_at'], Auth::getUserTimezone()) ?></div>
                        <div class="post-user"><?= esc($p['author_name'] ?? '') ?></div>
                    <?php else: ?>
                        <span class="text-muted"><?= __t('never') ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
admin_layout(__t('posts'), ob_get_clean());
