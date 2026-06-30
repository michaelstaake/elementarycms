<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$action = $segments[1] ?? 'index';
$identifier = isset($segments[2]) ? rawurldecode($segments[2]) : null;
$id = null;

if ($identifier !== null && $action !== 'new') {
    $resolvedPage = ContentManager::resolvePageAdminIdentifier($identifier);
    if (!$resolvedPage) {
        throw new RuntimeException('Page not found', 404);
    }
    $id = (int) $resolvedPage['id'];
}
$templateEngine = new Template();
$templates = $templateEngine->getAvailablePageTemplates();

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ContentManager::deletePage($id)) {
        flash('success', __t('page_deleted'));
    } else {
        flash('error', __t('protected_page'));
    }
    redirect(admin_url('pages'));
}

if ($action === 'versions' && $id) {
    $page = ContentManager::getPage($id);
    if (!$page) {
        throw new RuntimeException('Page not found', 404);
    }
    $pageSlug = admin_page_slug($page);
    $versions = ContentManager::getPageVersions($id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
        $result = ContentManager::restorePageVersion($id, (int) $_POST['restore'], Auth::user()['id']);
        flash($result['success'] ? 'success' : 'error', $result['success'] ? __t('version_restored') : $result['error']);
        redirect(admin_entity_url('pages', 'versions', $pageSlug));
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('pages'), 'url' => admin_url('pages')],
        ['label' => __t('versions'), 'url' => '#']
    ]) ?>
    <?= admin_flash() ?>
    <div class="card">
        <table class="table mb-0">
            <thead><tr><th>#</th><th><?= __t('title') ?></th><th><?= __t('author') ?></th><th><?= __t('date') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($versions as $v): ?>
                <tr>
                    <td><?= (int) $v['version_number'] ?><?= $v['is_draft'] ? ' (' . __t('draft') . ')' : '' ?></td>
                    <td><?= esc($v['title']) ?></td>
                    <td><?= esc($v['author_name'] ?? '') ?></td>
                    <td><?= format_date($v['created_at'], Auth::getUserTimezone()) ?></td>
                    <td class="text-end">
                        <a href="<?= admin_entity_url('pages', 'versions', $pageSlug) . '?view=' . $v['id'] ?>" class="btn btn-sm btn-outline-dark"><?= __t('view') ?></a>
                        <?= post_action_form(
                            admin_entity_url('pages', 'versions', $pageSlug),
                            __t('restore'),
                            __t('confirm_restore'),
                            'btn btn-sm btn-dark',
                            ['restore' => (string) $v['id']]
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    if (isset($_GET['view'])) {
        $viewVersion = Database::fetch('SELECT * FROM page_versions WHERE id = ? AND page_id = ?', [(int) $_GET['view'], $id]);
        $prevVersion = Database::fetch(
            'SELECT * FROM page_versions WHERE page_id = ? AND version_number < ? ORDER BY version_number DESC LIMIT 1',
            [$id, $viewVersion['version_number'] ?? 0]
        );
        if ($viewVersion) {
            echo '<div class="card mt-4 p-4"><h2 class="h5">' . __t('diff') . '</h2><pre class="mt-3">';
            echo diff_html($prevVersion['content'] ?? '', $viewVersion['content']);
            echo '</pre></div>';
        }
    }
    admin_layout(__t('versions'), ob_get_clean());
    return;
}

if ($action === 'edit' || $action === 'new') {
    $page = $id ? ContentManager::getPage($id) : null;
    $isPostsPage = $page && ContentManager::isPostsPage($page);
    $postsPageIsHome = $isPostsPage && ContentManager::isPostsPageHomepage();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => Validator::slug($_POST['slug'] ?: $_POST['title'] ?? ''),
            'content'          => '', // content is now stored in builder structure
            'featured_image'   => trim($_POST['featured_image'] ?? '') ?: null,
            'template'         => $_POST['template'] ?? 'default',
            'meta_keywords'    => $_POST['meta_keywords'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'status'           => $_POST['status'] ?? 'draft',
        ];

        // Parse builder structure from POST
        $builderJson = $_POST['builder_structure'] ?? '[]';
        $builderData = json_decode($builderJson, true);
        if (!is_array($builderData)) {
            $builderData = [];
        }

        $result = ContentManager::savePage($data, $id, Auth::user()['id']);
        if ($result['success']) {
            $savedId = $result['id'];
            if (!$isPostsPage) {
                ContentManager::savePageStructure($savedId, $builderData);
            }
            flash('success', __t('page_saved'));
            $savedPage = ContentManager::getPage($savedId);
            redirect(admin_entity_url('pages', 'edit', admin_page_slug($savedPage)));
        }
        flash('error', $result['error']);
        $page = array_merge($page ?? [], $data);
        // Preserve builder data on error
        $builderStructure = $builderData;
    } else {
        // Load existing builder structure, or provide default for new pages
        if ($id) {
            $builderStructure = ContentManager::getPageStructure($id);
        } else {
            $builderStructure = [
                'sections' => [
                    [
                        'id' => null,
                        'sort_order' => 0,
                        'css_class' => '',
                        'css_id' => '',
                        'inline_css' => null,
                        'rows' => [
                            [
                                'id' => null,
                                'sort_order' => 0,
                                'css_class' => '',
                                'css_id' => '',
                                'inline_css' => null,
                                'columns' => [
                                    [
                                        'id' => null,
                                        'sort_order' => 0,
                                        'width' => 100,
                                        'css_class' => '',
                                        'css_id' => '',
                                        'inline_css' => null,
                                        'blocks' => [
                                            [
                                                'id' => null,
                                                'sort_order' => 0,
                                                'block_type' => 'text',
                                                'block_data' => [
                                                    'text' => __t('new_page_default_text')
                                                ],
                                                'css_class' => '',
                                                'css_id' => '',
                                                'inline_css' => null,
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('pages'), 'url' => admin_url('pages')],
        ['label' => $id ? esc($page['title'] ?? '') : __t('new_page'), 'url' => '#']
    ]) ?>
    <?= admin_flash() ?>
    <form method="post" id="pageForm">
        <?= csrf_field() ?>
        <input type="hidden" name="builder_structure" id="builderStructureInput">

        <!-- Title & Save Row -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-9">
                        <input type="text" name="title" id="pageTitle" class="form-control form-control-lg" required placeholder="<?= esc(__t('title')) ?>" value="<?= esc($page['title'] ?? '') ?>"<?= $postsPageIsHome ? ' readonly' : '' ?>>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-dark btn-lg w-100"><?= __t('save') ?></button>
                        <small id="pageDraftStatus" class="text-muted d-none d-block mt-1 text-center"></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collapsible Side Panels -->
        <div class="row mb-3">
            <!-- Page Settings Panel -->
            <div class="col-md-4">
                <div class="card">
                    <button type="button" class="card-header py-3 d-flex justify-content-between align-items-center collapsed" data-bs-toggle="collapse" data-bs-target="#pageSettingsCollapse" aria-expanded="false" style="background: none; border: none; cursor: pointer; text-align: left; width: 100%;">
                        <span class="fw-semibold mb-0"><i class="bi bi-gear me-2"></i><?= __t('page_settings') ?? 'Page Settings' ?></span>
                        <i class="bi bi-chevron-down collapse-toggle-icon"></i>
                    </button>
                    <div id="pageSettingsCollapse" class="collapse">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><?= __t('slug') ?></label>
                                <?php if ($isPostsPage): ?>
                                    <input type="text" class="form-control" value="<?= esc($postsPageIsHome ? '/' : 'posts') ?>" readonly>
                                    <input type="hidden" name="slug" value="<?= esc($page['slug'] ?? '') ?>">
                                <?php else: ?>
                                    <input type="text" name="slug" class="form-control" value="<?= esc($page['slug'] ?? '') ?>" placeholder="<?= __t('auto_generated') ?>">
                                <?php endif; ?>
                            </div>
                            <?php if (!$isPostsPage): ?>
                            <div class="mb-3">
                                <label class="form-label"><?= __t('template') ?></label>
                                <select name="template" class="form-select">
                                    <?php foreach ($templates as $key => $label): ?>
                                        <option value="<?= esc($key) ?>" <?= ($page['template'] ?? 'default') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="template" value="posts">
                            <?php endif; ?>
                            <div class="mb-0">
                                <label class="form-label"><?= __t('status') ?></label>
                                <select name="status" class="form-select">
                                    <option value="draft" <?= ($page['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>><?= __t('draft') ?></option>
                                    <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= __t('published') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Featured Image Panel -->
            <div class="col-md-4">
                <div class="card">
                    <button type="button" class="card-header py-3 d-flex justify-content-between align-items-center collapsed" data-bs-toggle="collapse" data-bs-target="#pageFeaturedImageCollapse" aria-expanded="false" style="background: none; border: none; cursor: pointer; text-align: left; width: 100%;">
                        <span class="fw-semibold mb-0"><i class="bi bi-image me-2"></i><?= __t('featured_image') ?></span>
                        <i class="bi bi-chevron-down collapse-toggle-icon"></i>
                    </button>
                    <div id="pageFeaturedImageCollapse" class="collapse">
                        <div class="card-body">
                            <div class="mb-3 text-center" id="pageFeaturedImagePreview">
                                <?php if (!empty($page['featured_image'])): ?>
                                    <img src="<?= esc(file_url($page['featured_image'])) ?>" alt="" class="featured-image-preview-img mb-2">
                                    <input type="hidden" name="featured_image" value="<?= esc($page['featured_image']) ?>">
                                <?php else: ?>
                                    <span class="text-muted"><?= __t('no_featured_image') ?></span>
                                    <input type="hidden" name="featured_image" value="">
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#pageImageSelectorModal" onclick="pageImageSelectContext = { type: 'featured' }">
                                    <i class="bi bi-folder2-open"></i> <?= __t('select_image') ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#pageUploadImageModal" onclick="pageUploadContext = { type: 'featured' }">
                                    <i class="bi bi-cloud-upload"></i> <?= __t('upload_image') ?>
                                </button>
                                <?php if (!empty($page['featured_image'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('<?= __t('remove_featured_image') ?>')){document.getElementById('pageFeaturedImagePreview').innerHTML='<span class=\'text-muted\'><?= __t('no_featured_image') ?></span><input type=\'hidden\' name=\'featured_image\' value=\'\';">}">
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
                    <button type="button" class="card-header py-3 d-flex justify-content-between align-items-center collapsed" data-bs-toggle="collapse" data-bs-target="#seoSettingsCollapse" aria-expanded="false" style="background: none; border: none; cursor: pointer; text-align: left; width: 100%;">
                        <span class="fw-semibold mb-0"><i class="bi bi-search me-2"></i><?= __t('seo_settings') ?? 'SEO Settings' ?></span>
                        <i class="bi bi-chevron-down collapse-toggle-icon"></i>
                    </button>
                    <div id="seoSettingsCollapse" class="collapse">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><?= __t('meta_keywords') ?></label>
                                <input type="text" name="meta_keywords" class="form-control" value="<?= esc($page['meta_keywords'] ?? '') ?>" placeholder="<?= __t('meta_keywords_placeholder') ?>">
                            </div>
                            <div class="mb-0">
                                <label class="form-label"><?= __t('meta_description') ?></label>
                                <textarea name="meta_description" class="form-control" rows="3" placeholder="<?= __t('meta_description_placeholder') ?>"><?= esc($page['meta_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isPostsPage): ?>
        <div class="card">
            <div class="card-body">
                <p class="mb-0 text-muted"><?= __t('posts_page_info') ?></p>
            </div>
        </div>
        <?php else: ?>
        <!-- Page Builder -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#builderEditor" role="tab">
                            <i class="bi bi-layout-grid"></i> <?= __t('builder_editor') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#builderCode" role="tab">
                            <i class="bi bi-code-slash"></i> <?= __t('builder_code') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#builderPreview" role="tab">
                            <i class="bi bi-eye"></i> <?= __t('builder_preview') ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Editor Tab -->
                    <div class="tab-pane fade show active" id="builderEditor" role="tabpanel">
                        <div id="pageBuilder">
                            <div id="sectionsContainer"></div>
                            <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="PageBuilder.addSection()">
                                <i class="bi bi-plus-lg"></i> <?= __t('add_section') ?>
                            </button>
                        </div>
                    </div>

                    <!-- Code Tab -->
                    <div class="tab-pane fade" id="builderCode" role="tabpanel">
                        <p class="text-muted mb-3"><?= __t('code_view_help') ?></p>
                        <textarea class="form-control" id="codeEditor" rows="25" style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.85rem;"></textarea>
                    </div>

                    <!-- Preview Tab -->
                    <div class="tab-pane fade" id="builderPreview" role="tabpanel">
                        <p class="text-muted mb-3"><?= __t('preview_view_help') ?></p>
                        <div id="previewContainer" class="border rounded p-4 bg-white"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </form>

    <!-- Image Selector Modal -->
    <div class="modal fade" id="pageImageSelectorModal" tabindex="-1" aria-labelledby="pageImageSelectorLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pageImageSelectorLabel"><?= __t('select_image') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3" id="pageImageSelectorGrid">
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
                            <div class="card h-100 page-image-option" onclick="selectPageImage(<?= esc(json_encode($f['filename'])) ?>, <?= esc(json_encode($fileUrl)) ?>)" style="cursor: pointer;">
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
    <div class="modal fade" id="pageUploadImageModal" tabindex="-1" aria-labelledby="pageUploadImageLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pageUploadImageLabel"><?= __t('upload_image') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="pageUploadImageForm" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="media_type" value="image">
                        <div class="mb-3">
                            <label class="form-label" for="pageUploadInput"><?= __t('choose_image') ?></label>
                            <input type="file" name="upload" id="pageUploadInput" class="form-control" accept="image/*" required>
                            <div class="form-text"><?= __t('image_types_allowed') ?></div>
                        </div>
                        <div class="progress mb-2" style="height: 25px; display: none;" id="pageUploadProgressContainer">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="pageUploadProgressBar" style="width: 0%"></div>
                        </div>
                        <div id="pageUploadStatus" class="small"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="button" class="btn btn-dark" id="pageUploadBtn"><?= __t('upload') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image URL Modal -->
    <div class="modal fade" id="blockImageUrlModal" tabindex="-1" aria-labelledby="blockImageUrlLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="blockImageUrlLabel"><?= __t('image_from_url') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="blockImageUrlInput"><?= __t('image_url') ?></label>
                    <input type="url" class="form-control" id="blockImageUrlInput" placeholder="<?= esc(__t('image_url_placeholder')) ?>">
                    <div id="blockImageUrlError" class="text-danger small mt-2" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="button" class="btn btn-dark" id="blockImageUrlBtn"><?= __t('save') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Selector Modal -->
    <div class="modal fade" id="pageVideoSelectorModal" tabindex="-1" aria-labelledby="pageVideoSelectorLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pageVideoSelectorLabel"><?= __t('select_video') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3" id="pageVideoSelectorGrid">
                        <?php
                        $hasVideos = false;
                        foreach ($allFiles ?? FileManager::getFiles() as $f):
                            $isVideo = isset($f['mime_type']) && str_starts_with($f['mime_type'], 'video/');
                            if (!$isVideo) {
                                continue;
                            }
                            $hasVideos = true;
                            $fileUrl = file_url($f['filename']);
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100 page-video-option" onclick="selectPageVideo(<?= esc(json_encode($f['filename'])) ?>, <?= esc(json_encode($fileUrl)) ?>)" style="cursor: pointer;">
                                <div class="card-body p-3 text-center">
                                    <i class="bi bi-camera-video fs-1 text-muted"></i>
                                    <small class="text-truncate d-block mt-2"><?= esc($f['title']) ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$hasVideos): ?>
                        <div class="col-12 text-center text-muted py-4"><?= __t('no_videos_uploaded') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Video Modal -->
    <div class="modal fade" id="pageUploadVideoModal" tabindex="-1" aria-labelledby="pageUploadVideoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pageUploadVideoLabel"><?= __t('upload_video') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="pageUploadVideoForm" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="media_type" value="video">
                        <div class="mb-3">
                            <label class="form-label" for="pageUploadVideoInput"><?= __t('choose_video') ?></label>
                            <input type="file" name="upload" id="pageUploadVideoInput" class="form-control" accept="video/*" required>
                            <div class="form-text"><?= __t('video_types_allowed') ?></div>
                        </div>
                        <div class="progress mb-2" style="height: 25px; display: none;" id="pageUploadVideoProgressContainer">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="pageUploadVideoProgressBar" style="width: 0%"></div>
                        </div>
                        <div id="pageUploadVideoStatus" class="small"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="button" class="btn btn-dark" id="pageUploadVideoBtn"><?= __t('upload') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Embed URL Modal -->
    <div class="modal fade" id="blockVideoEmbedModal" tabindex="-1" aria-labelledby="blockVideoEmbedLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="blockVideoEmbedLabel"><?= __t('video_embed_url') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="blockVideoEmbedInput"><?= __t('video_embed_url_label') ?></label>
                    <input type="url" class="form-control" id="blockVideoEmbedInput" placeholder="<?= esc(__t('video_embed_url_placeholder')) ?>">
                    <div id="blockVideoEmbedError" class="text-danger small mt-2" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="button" class="btn btn-dark" id="blockVideoEmbedBtn"><?= __t('save') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Element Settings Modal -->
    <div class="modal fade" id="elementSettingsModal" tabindex="-1" aria-labelledby="elementSettingsLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="elementSettingsLabel"><?= __t('element_settings') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="elementWidthGroup" style="display: none;">
                        <label class="form-label" for="elementWidth"><?= __t('column_width') ?></label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="range" class="form-range flex-grow-1" id="elementWidth" min="1" max="99" step="1" oninput="document.getElementById('elementWidthValue').textContent = this.value + '%'">
                            <span class="small text-muted" id="elementWidthValue" style="min-width: 40px;">50%</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="elementCssClass"><?= __t('css_class') ?></label>
                        <input type="text" class="form-control" id="elementCssClass" placeholder="my-class another-class">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="elementCssId"><?= __t('css_id') ?></label>
                        <input type="text" class="form-control" id="elementCssId" placeholder="my-element-id">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="elementInlineCss"><?= __t('inline_css') ?></label>
                        <textarea class="form-control font-monospace" id="elementInlineCss" rows="4" placeholder="padding: 1rem; background-color: #f5f5f5;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="button" class="btn btn-dark" id="elementSettingsBtn"><?= __t('save') ?></button>
                </div>
            </div>
        </div>
    </div>

    <?php
    $imageFiles = [];
    $videoFiles = [];
    foreach (FileManager::getFiles() as $f) {
        if (isset($f['mime_type']) && str_starts_with($f['mime_type'], 'image/')) {
            $imageFiles[$f['filename']] = [
                'url'   => file_url($f['filename']),
                'title' => $f['title'],
            ];
        } elseif (isset($f['mime_type']) && str_starts_with($f['mime_type'], 'video/')) {
            $videoFiles[$f['filename']] = [
                'url'   => file_url($f['filename']),
                'title' => $f['title'],
            ];
        }
    }
    ?>
    <script src="<?= asset('jquery/jquery.min.js') ?>"></script>
    <script src="<?= asset('summernote/summernote-bs5.min.js') ?>"></script>
    <script src="<?= asset('admin/admin-autosave.js') ?>"></script>
    <script>
    // Page Builder Configuration (safe JSON encoding of all PHP values)
    const PageBuilderConfig = <?= json_encode([
        'sections' => $builderStructure,
        'imageFiles' => $imageFiles,
        'videoFiles' => $videoFiles,
        'confirm_delete_section' => __t('confirm_delete_section'),
        'confirm_delete_row' => __t('confirm_delete_row'),
        'confirm_delete_column' => __t('confirm_delete_column'),
        'confirm_delete_block' => __t('confirm_delete_block'),
        'builder_empty' => __t('builder_empty'),
        'empty_section' => __t('empty_section'),
        'empty_row' => __t('empty_row'),
        'empty_column' => __t('empty_column'),
        'section' => __t('section'),
        'row' => __t('row'),
        'column' => __t('column'),
        'text_block' => __t('text_block'),
        'image_block' => __t('image_block'),
        'add_text_block' => __t('add_text_block'),
        'add_image_block' => __t('add_image_block'),
        'image_from_files' => __t('image_from_files'),
        'image_upload' => __t('image_upload'),
        'image_from_url' => __t('image_from_url'),
        'no_image_selected' => __t('no_image_selected'),
        'select_image' => __t('select_image'),
        'remove_image' => __t('remove_image'),
        'invalid_image_url' => __t('invalid_image_url'),
        'video_block' => __t('video_block'),
        'add_video_block' => __t('add_video_block'),
        'video_from_files' => __t('video_from_files'),
        'video_upload' => __t('video_upload'),
        'video_embed_url' => __t('video_embed_url'),
        'no_video_selected' => __t('no_video_selected'),
        'remove_video' => __t('remove_video'),
        'invalid_video_url' => __t('invalid_video_url'),
        'text_placeholder' => esc(__t('text_placeholder')),
        'move_up' => __t('move_up'),
        'move_down' => __t('move_down'),
        'move_left' => __t('move_left'),
        'move_right' => __t('move_right'),
        'delete' => __t('delete'),
        'add_row' => __t('add_row'),
        'add_column' => __t('add_column'),
        'add_block' => __t('add_block'),
        'element_settings' => __t('element_settings'),
        'element_settings_section' => __t('element_settings_section'),
        'element_settings_row' => __t('element_settings_row'),
        'element_settings_column' => __t('element_settings_column'),
        'element_settings_block' => __t('element_settings_block'),
        'column_width' => __t('column_width'),
        'unsaved_changes' => __t('unsaved_changes'),
    ]) ?>;

    // Unsaved changes tracking
    window.pageFormDirty = false;

    // Page Builder Application
    const PageBuilder = {
        structure: { sections: PageBuilderConfig.sections },
        elementSettingsContext: null,
        MIN_COLUMN_WIDTH: 5,

        init() {
            try {
                console.log('PageBuilder.init() called');
                this.render();
                this.updateCodeView();
                this.updatePreview();
                this.setupFormSubmit();
                this.setupTabEvents();
                this.initDragAndDrop();
                this.setupUnsavedChangesWarning();
                console.log('PageBuilder initialized successfully');
            } catch(e) {
                console.error('PageBuilder init error:', e);
            }
        },

        setupUnsavedChangesWarning() {
            const form = document.getElementById('pageForm');
            // Track changes on all form inputs
            form.addEventListener('input', () => this.markDirty());
            form.addEventListener('change', () => this.markDirty());

            // Track changes in the code editor textarea specifically
            const codeEditor = document.getElementById('codeEditor');
            if (codeEditor) {
                codeEditor.addEventListener('input', () => this.markDirty());
            }

            // Warn before leaving the page with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (window.pageFormDirty) {
                    e.preventDefault();
                    e.returnValue = PageBuilderConfig.unsaved_changes;
                    return PageBuilderConfig.unsaved_changes;
                }
            });
        },

        /* Drag and Drop State */
        dragContext: null,

        initDragAndDrop() {
            const container = document.getElementById('sectionsContainer');
            if (!container) return;

            // Use event delegation for drag events
            container.addEventListener('dragstart', (e) => this.handleDragStart(e));
            container.addEventListener('dragover', (e) => this.handleDragOver(e));
            container.addEventListener('dragleave', (e) => this.handleDragLeave(e));
            container.addEventListener('drop', (e) => this.handleDrop(e));
            container.addEventListener('dragend', (e) => this.handleDragEnd(e));
        },

        handleDragStart(e) {
            const draggable = e.target.closest('[data-drag-type]');
            if (!draggable) {
                e.preventDefault();
                return;
            }

            // Only allow dragging from the grip handle
            if (!e.target.closest('.builder-drag-handle')) {
                e.preventDefault();
                return;
            }

            const type = draggable.dataset.dragType;
            const path = JSON.parse(draggable.dataset.dragPath);

            this.dragContext = { type, path };
            draggable.classList.add('builder-dragging');

            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', JSON.stringify({ type, path }));
        },

        handleDragOver(e) {
            e.preventDefault();
            if (!this.dragContext) return;

            // Clear all drag-over indicators first
            document.querySelectorAll('.builder-drag-over-top, .builder-drag-over-bottom, .builder-drag-over-left, .builder-drag-over-right').forEach(el => {
                el.classList.remove('builder-drag-over-top', 'builder-drag-over-bottom', 'builder-drag-over-left', 'builder-drag-over-right');
            });

            const target = e.target.closest('[data-drag-type]');
            if (!target || !target.dataset.dragType) return;

            const targetType = target.dataset.dragType;

            // Only allow dropping on same-type elements
            if (targetType !== this.dragContext.type) return;

            const targetPath = JSON.parse(target.dataset.dragPath);

            // Don't allow dropping on self
            if (JSON.stringify(targetPath) === JSON.stringify(this.dragContext.path)) return;

            // Don't allow dropping inside descendants (prevent circular drops)
            if (this.isDescendantPath(this.dragContext.path, targetPath, targetType)) return;

            e.dataTransfer.dropEffect = 'move';

            // Determine drop position based on element type
            const rect = target.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            const midX = rect.left + rect.width / 2;

            if (targetType === 'column') {
                // For columns, use horizontal positioning
                if (e.clientX < midX) {
                    target.classList.add('builder-drag-over-left');
                } else {
                    target.classList.add('builder-drag-over-right');
                }
            } else {
                // For sections, rows, blocks, use vertical positioning
                if (e.clientY < midY) {
                    target.classList.add('builder-drag-over-top');
                } else {
                    target.classList.add('builder-drag-over-bottom');
                }
            }
        },

        handleDragLeave(e) {
            // Remove drag-over indicators from the element being left
            const target = e.target.closest('[data-drag-type]');
            if (target) {
                target.classList.remove('builder-drag-over-top', 'builder-drag-over-bottom', 'builder-drag-over-left', 'builder-drag-over-right');
            }
        },

        handleDrop(e) {
            e.preventDefault();
            if (!this.dragContext) return;

            // Clear all drag-over indicators
            document.querySelectorAll('.builder-drag-over-top, .builder-drag-over-bottom, .builder-drag-over-left, .builder-drag-over-right').forEach(el => {
                el.classList.remove('builder-drag-over-top', 'builder-drag-over-bottom', 'builder-drag-over-left', 'builder-drag-over-right');
            });

            const target = e.target.closest('[data-drag-type]');
            if (!target || !target.dataset.dragType) {
                this.dragContext = null;
                return;
            }

            const targetType = target.dataset.dragType;
            if (targetType !== this.dragContext.type) {
                this.dragContext = null;
                return;
            }

            const targetPath = JSON.parse(target.dataset.dragPath);
            if (JSON.stringify(targetPath) === JSON.stringify(this.dragContext.path)) {
                this.dragContext = null;
                return;
            }

            // Determine if we should insert before or after the target
            const rect = target.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            const midX = rect.left + rect.width / 2;
            const insertBefore = (targetType === 'column') ? (e.clientX < midX) : (e.clientY < midY);

            // Perform the move
            this.executeDragMove(this.dragContext.type, this.dragContext.path, targetPath, insertBefore);
            this.dragContext = null;
        },

        handleDragEnd(e) {
            // Clean up all visual indicators
            document.querySelectorAll('.builder-dragging, .builder-drag-over-top, .builder-drag-over-bottom, .builder-drag-over-left, .builder-drag-over-right').forEach(el => {
                el.classList.remove('builder-dragging', 'builder-drag-over-top', 'builder-drag-over-bottom', 'builder-drag-over-left', 'builder-drag-over-right');
            });
            this.dragContext = null;
        },

        isDescendantPath(sourcePath, targetPath, type) {
            // Check if target is a descendant of the source (to prevent circular drops)
            // For sections: source is [si], target is [si] - check if same section
            // For rows: source is [si, ri], target is [si, ri] - check if same section
            // For columns: source is [si, ri, ci], target is [si, ri, ci]
            // For blocks: source is [si, ri, ci, bi], target is [si, ri, ci, bi]
            if (type === 'section') return false; // Sections can't contain sections
            if (type === 'row') {
                return sourcePath[0] === targetPath[0]; // Same section
            }
            if (type === 'column') {
                return sourcePath[0] === targetPath[0] && sourcePath[1] === targetPath[1]; // Same row
            }
            if (type === 'block') {
                return sourcePath[0] === targetPath[0] && sourcePath[1] === targetPath[1] && sourcePath[2] === targetPath[2]; // Same column
            }
            return false;
        },

        executeDragMove(type, sourcePath, targetPath, insertBefore) {
            let sourceArray, sourceItem, sourceIndex, targetIndex;

            if (type === 'section') {
                sourceArray = this.structure.sections;
                sourceIndex = sourcePath[0];
                sourceItem = sourceArray[sourceIndex];
                targetIndex = targetPath[0];
            } else if (type === 'row') {
                sourceArray = this.structure.sections[sourcePath[0]].rows;
                sourceIndex = sourcePath[1];
                sourceItem = sourceArray[sourceIndex];
                targetIndex = targetPath[1];
            } else if (type === 'column') {
                sourceArray = this.structure.sections[sourcePath[0]].rows[sourcePath[1]].columns;
                sourceIndex = sourcePath[2];
                sourceItem = sourceArray[sourceIndex];
                targetIndex = targetPath[2];
            } else if (type === 'block') {
                sourceArray = this.structure.sections[sourcePath[0]].rows[sourcePath[1]].columns[sourcePath[2]].blocks;
                sourceIndex = sourcePath[3];
                sourceItem = sourceArray[sourceIndex];
                targetIndex = targetPath[3];
            }

            // Remove from source
            sourceArray.splice(sourceIndex, 1);

            // Adjust target index if source was before target
            if (sourceIndex < targetIndex) {
                targetIndex--;
            }

            // Insert at target position
            if (insertBefore) {
                sourceArray.splice(targetIndex, 0, sourceItem);
            } else {
                sourceArray.splice(targetIndex + 1, 0, sourceItem);
            }

            this.reindex();
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        setupFormSubmit() {
            const form = document.getElementById('pageForm');
            form.addEventListener('submit', (e) => {
                const title = document.getElementById('pageTitle').value.trim();
                if (!title) {
                    e.preventDefault();
                    document.getElementById('pageTitle').focus();
                    return false;
                }
                this.syncStructure();
                // Clear unsaved changes flag on submit
                window.pageFormDirty = false;
            });
        },

        markDirty() {
            window.pageFormDirty = true;
        },

        setupTabEvents() {
            const navTabs = document.querySelector('.nav-tabs');
            if (!navTabs) return;
            // When switching tabs, update the appropriate view
            navTabs.addEventListener('shown.bs.tab', (e) => {
                const targetId = e.target.getAttribute('href')?.replace('#', '');
                if (targetId === 'builderCode') {
                    this.updateCodeView();
                } else if (targetId === 'builderPreview') {
                    this.updatePreview();
                } else if (targetId === 'builderEditor' && e.relatedTarget?.id === 'builderCode') {
                    try {
                        const parsed = JSON.parse(document.getElementById('codeEditor').value);
                        this.structure = { sections: parsed.sections || [] };
                        this.render();
                        this.updatePreview();
                    } catch(err) {
                        // Ignore parse errors - keep current structure
                    }
                }
            });
        },

        syncStructure() {
            this.syncEditors();
            document.getElementById('builderStructureInput').value = JSON.stringify({ sections: this.structure.sections || [] });
        },

        updateCodeView() {
            this.syncStructure();
            const json = JSON.stringify({ sections: this.structure.sections || [] }, null, 2);
            document.getElementById('codeEditor').value = json;
        },

        updatePreview() {
            this.syncStructure();
            const container = document.getElementById('previewContainer');
            let html = '';
            for (const section of (this.structure.sections || [])) {
                html += '<section' + this.buildElementAttrs(section) + '>';
                for (const row of (section.rows || [])) {
                    html += '<div' + this.buildElementAttrs(row, '', 'display:flex;gap:16px;margin-bottom:0;') + '>';
                    for (const col of (row.columns || [])) {
                        const flex = col.width === 100 ? '1' : String(col.width);
                        html += '<div' + this.buildElementAttrs(col, '', 'flex:' + flex + ';min-width:0;') + '>';
                        for (const block of (col.blocks || [])) {
                            if (block.block_type === 'text') {
                                const text = block.block_data?.text || '';
                                html += '<div' + this.buildElementAttrs(block, '', 'margin-bottom:8px;') + '>' + text.replace(/\n/g, '<br>') + '</div>';
                            } else if (block.block_type === 'image') {
                                const imageUrl = this.getImageBlockUrl(block);
                                if (imageUrl) {
                                    const safeUrl = imageUrl.replace(/"/g, '&quot;');
                                    html += '<div' + this.buildElementAttrs(block, '', 'margin-bottom:8px;') + '><img src="' + safeUrl + '" alt="" style="max-width:100%;height:auto;border-radius:6px;"></div>';
                                }
                            } else if (block.block_type === 'video') {
                                const videoHtml = this.getVideoBlockPreviewHtml(block);
                                if (videoHtml) {
                                    html += '<div' + this.buildElementAttrs(block) + '>' + videoHtml.replace('style="margin-bottom:8px;"', 'style="margin-bottom:8px;"') + '</div>';
                                }
                            }
                        }
                        html += '</div>';
                    }
                    html += '</div>';
                }
                html += '</section>';
            }
            container.innerHTML = html || '<p class="text-muted">' + PageBuilderConfig.builder_empty + '</p>';
        },

        uid() {
            return Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
        },

        addSection() {
            console.log('addSection called');
            if (!this.structure.sections) this.structure.sections = [];
            this.structure.sections.push({
                id: null,
                sort_order: this.structure.sections.length,
                rows: []
            });
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        deleteSection(sectionIndex) {
            if (confirm(PageBuilderConfig.confirm_delete_section)) {
                this.structure.sections.splice(sectionIndex, 1);
                this.reindex();
                this.render();
                this.updateCodeView();
                this.updatePreview();
            }
        },

        moveSection(sectionIndex, direction) {
            const newIndex = sectionIndex + direction;
            if (newIndex < 0 || newIndex >= this.structure.sections.length) return;
            const sections = this.structure.sections;
            [sections[sectionIndex], sections[newIndex]] = [sections[newIndex], sections[sectionIndex]];
            this.render();
        },

        addRow(sectionIndex, columnCount) {
            const section = this.structure.sections[sectionIndex];
            if (!section.rows) section.rows = [];
            const columns = [];
            const count = columnCount || 1;
            for (let i = 0; i < count; i++) {
                columns.push({
                    id: null,
                    sort_order: i,
                    width: Math.round(100 / count),
                    blocks: []
                });
            }
            // Adjust last column to sum to 100
            if (count > 0) {
                const currentSum = columns.slice(0, -1).reduce((s, c) => s + c.width, 0);
                columns[count - 1].width = 100 - currentSum;
            }
            section.rows.push({
                id: null,
                sort_order: section.rows.length,
                columns: columns
            });
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        deleteRow(sectionIndex, rowIndex) {
            if (confirm(PageBuilderConfig.confirm_delete_row)) {
                this.structure.sections[sectionIndex].rows.splice(rowIndex, 1);
                this.reindex();
                this.render();
                this.updateCodeView();
                this.updatePreview();
            }
        },

        moveRow(sectionIndex, rowIndex, direction) {
            const rows = this.structure.sections[sectionIndex].rows;
            const newIndex = rowIndex + direction;
            if (newIndex < 0 || newIndex >= rows.length) return;
            [rows[rowIndex], rows[newIndex]] = [rows[newIndex], rows[rowIndex]];
            this.render();
        },

        addColumn(sectionIndex, rowIndex) {
            this.addColumns(sectionIndex, rowIndex, 1);
        },

        setRowColumns(sectionIndex, rowIndex, count) {
            const row = this.structure.sections[sectionIndex].rows[rowIndex];
            const oldColumns = row.columns || [];
            const newColumns = [];
            // Collect all blocks from existing columns
            const allBlocks = [];
            oldColumns.forEach(col => {
                (col.blocks || []).forEach(block => allBlocks.push(block));
            });
            // Distribute blocks across new columns
            for (let i = 0; i < count; i++) {
                const colBlocks = [];
                for (let j = i; j < allBlocks.length; j += count) {
                    colBlocks.push(allBlocks[j]);
                }
                newColumns.push({
                    id: null,
                    sort_order: i,
                    width: Math.round(100 / count),
                    blocks: colBlocks
                });
            }
            // Adjust last column width to sum to 100
            if (count > 0) {
                const currentSum = newColumns.slice(0, -1).reduce((s, c) => s + c.width, 0);
                newColumns[count - 1].width = 100 - currentSum;
            }
            row.columns = newColumns;
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        deleteColumn(sectionIndex, rowIndex, colIndex) {
            if (confirm(PageBuilderConfig.confirm_delete_column)) {
                const row = this.structure.sections[sectionIndex].rows[rowIndex];
                row.columns.splice(colIndex, 1);
                // Redistribute widths
                if (row.columns.length > 0) {
                    const total = row.columns.length;
                    row.columns.forEach((col, i) => {
                        col.width = Math.round(100 / total);
                    });
                    const currentSum = row.columns.slice(0, -1).reduce((s, c) => s + c.width, 0);
                    row.columns[total - 1].width = 100 - currentSum;
                }
                this.reindex();
                this.render();
                this.updateCodeView();
                this.updatePreview();
            }
        },

        applyColumnWidth(sectionIndex, rowIndex, colIndex, width) {
            const row = this.structure.sections[sectionIndex].rows[rowIndex];
            const col = row.columns[colIndex];
            const total = row.columns.length;
            // Redistribute remaining width proportionally among other columns
            const otherWidth = 100 - width;
            const otherColsWidth = row.columns.filter((_, i) => i !== colIndex).reduce((s, c) => s + c.width, 0);
            row.columns.forEach((c, i) => {
                if (i !== colIndex) {
                    c.width = otherColsWidth > 0 ? Math.round((c.width / otherColsWidth) * otherWidth * 100) / 100 : Math.round(100 / (total - 1) * 100) / 100;
                }
            });
            col.width = width;
            // Adjust last other column to ensure sum is exactly 100
            const currentSum = row.columns.reduce((s, c) => s + c.width, 0);
            const diff = 100 - currentSum;
            const lastOtherIndex = row.columns.length - 1 === colIndex ? row.columns.length - 2 : row.columns.length - 1;
            row.columns[lastOtherIndex].width = Math.round((row.columns[lastOtherIndex].width + diff) * 100) / 100;
        },

        moveColumn(sectionIndex, rowIndex, colIndex, direction) {
            const columns = this.structure.sections[sectionIndex].rows[rowIndex].columns;
            const newIndex = colIndex + direction;
            if (newIndex < 0 || newIndex >= columns.length) return;
            [columns[colIndex], columns[newIndex]] = [columns[newIndex], columns[colIndex]];
            this.render();
        },

        addBlock(sectionIndex, rowIndex, colIndex, blockType) {
            const col = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex];
            if (!col.blocks) col.blocks = [];
            col.blocks.push({
                id: null,
                sort_order: col.blocks.length,
                block_type: 'text',
                block_data: { text: '' }
            });
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        addImageBlock(sectionIndex, rowIndex, colIndex, sourceType) {
            const col = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex];
            if (!col.blocks) col.blocks = [];
            col.blocks.push({
                id: null,
                sort_order: col.blocks.length,
                block_type: 'image',
                block_data: { source: sourceType === 'url' ? 'url' : 'file', filename: '', url: '' }
            });
            const blockIndex = col.blocks.length - 1;
            this.render();
            this.updateCodeView();
            this.updatePreview();

            if (sourceType === 'file') {
                this.openImageSelector(sectionIndex, rowIndex, colIndex, blockIndex);
            } else if (sourceType === 'upload') {
                this.openImageUpload(sectionIndex, rowIndex, colIndex, blockIndex);
            } else if (sourceType === 'url') {
                this.openImageUrlModal(sectionIndex, rowIndex, colIndex, blockIndex);
            }
        },

        getImageBlockUrl(block) {
            const data = block.block_data || {};
            if ((data.source === 'url' || (!data.filename && data.url)) && data.url) {
                return data.url;
            }
            if (data.filename) {
                const fileInfo = PageBuilderConfig.imageFiles[data.filename];
                return fileInfo ? fileInfo.url : '';
            }
            return '';
        },

        updateBlockText(sectionIndex, rowIndex, colIndex, blockIndex, value) {
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            block.block_data.text = value;
        },

        updateBlockImageFile(sectionIndex, rowIndex, colIndex, blockIndex, filename, fileUrl) {
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            block.block_data = { source: 'file', filename: filename, url: '' };
            if (fileUrl) {
                PageBuilderConfig.imageFiles[filename] = PageBuilderConfig.imageFiles[filename] || { url: fileUrl, title: filename };
            }
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        updateBlockImageUrl(sectionIndex, rowIndex, colIndex, blockIndex, url) {
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            block.block_data = { source: 'url', filename: '', url: url };
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        removeBlockImage(sectionIndex, rowIndex, colIndex, blockIndex) {
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            block.block_data = { source: 'file', filename: '', url: '' };
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        openImageSelector(sectionIndex, rowIndex, colIndex, blockIndex) {
            pageImageSelectContext = { type: 'block', si: sectionIndex, ri: rowIndex, ci: colIndex, bi: blockIndex };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pageImageSelectorModal')).show();
        },

        openImageUpload(sectionIndex, rowIndex, colIndex, blockIndex) {
            pageUploadContext = { type: 'block', si: sectionIndex, ri: rowIndex, ci: colIndex, bi: blockIndex };
            document.getElementById('pageUploadInput').value = '';
            document.getElementById('pageUploadStatus').textContent = '';
            document.getElementById('pageUploadProgressBar').style.width = '0%';
            document.getElementById('pageUploadProgressBar').textContent = '';
            document.getElementById('pageUploadProgressContainer').style.display = 'none';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pageUploadImageModal')).show();
        },

        openImageUrlModal(sectionIndex, rowIndex, colIndex, blockIndex) {
            blockImageUrlContext = { si: sectionIndex, ri: rowIndex, ci: colIndex, bi: blockIndex };
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            const currentUrl = block.block_data?.source === 'url' ? (block.block_data.url || '') : '';
            document.getElementById('blockImageUrlInput').value = currentUrl;
            document.getElementById('blockImageUrlError').style.display = 'none';
            document.getElementById('blockImageUrlError').textContent = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('blockImageUrlModal')).show();
            setTimeout(() => document.getElementById('blockImageUrlInput').focus(), 300);
        },

        addVideoBlock(sectionIndex, rowIndex, colIndex, sourceType) {
            const col = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex];
            if (!col.blocks) col.blocks = [];
            col.blocks.push({
                id: null,
                sort_order: col.blocks.length,
                block_type: 'video',
                block_data: { source: sourceType === 'embed' ? 'embed' : 'file', filename: '', url: '' }
            });
            const blockIndex = col.blocks.length - 1;
            this.render();
            this.updateCodeView();
            this.updatePreview();

            if (sourceType === 'file') {
                this.openVideoSelector(sectionIndex, rowIndex, colIndex, blockIndex);
            } else if (sourceType === 'upload') {
                this.openVideoUpload(sectionIndex, rowIndex, colIndex, blockIndex);
            } else if (sourceType === 'embed') {
                this.openVideoEmbedModal(sectionIndex, rowIndex, colIndex, blockIndex);
            }
        },

        getVideoBlockUrl(block) {
            const data = block.block_data || {};
            if ((data.source === 'embed' || (!data.filename && data.url)) && data.url) {
                return data.url;
            }
            if (data.filename) {
                const fileInfo = PageBuilderConfig.videoFiles[data.filename];
                return fileInfo ? fileInfo.url : '';
            }
            return '';
        },

        parseVideoEmbed(url) {
            if (!url) return null;
            try {
                const parsed = new URL(url);
                const host = parsed.hostname.toLowerCase();
                const path = parsed.pathname;

                if (host.includes('youtube.com') || host.includes('youtu.be')) {
                    let id = '';
                    if (host.includes('youtu.be')) {
                        id = path.replace(/^\//, '');
                    } else if (path.includes('/embed/')) {
                        id = path.split('/embed/')[1].split(/[/?]/)[0];
                    } else {
                        id = parsed.searchParams.get('v') || '';
                    }
                    if (id) {
                        return { type: 'iframe', src: 'https://www.youtube.com/embed/' + id };
                    }
                }

                if (host.includes('vimeo.com')) {
                    const match = path.match(/\/(\d+)/);
                    if (match) {
                        return { type: 'iframe', src: 'https://player.vimeo.com/video/' + match[1] };
                    }
                }

                if (/\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i.test(path)) {
                    return { type: 'video', src: url };
                }
            } catch (e) {
                return null;
            }
            return null;
        },

        getVideoBlockPreviewHtml(block) {
            const data = block.block_data || {};
            let html = '<div style="margin-bottom:8px;">';

            if ((data.source === 'embed' || (!data.filename && data.url)) && data.url) {
                const embed = this.parseVideoEmbed(data.url);
                const safeUrl = data.url.replace(/"/g, '&quot;');
                if (embed && embed.type === 'iframe') {
                    const safeSrc = embed.src.replace(/"/g, '&quot;');
                    html += '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px;background:#000;">';
                    html += '<iframe src="' + safeSrc + '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen></iframe>';
                    html += '</div>';
                } else if (embed && embed.type === 'video') {
                    html += '<video controls style="max-width:100%;border-radius:6px;" src="' + safeUrl + '"></video>';
                }
            } else if (data.filename) {
                const fileInfo = PageBuilderConfig.videoFiles[data.filename];
                if (fileInfo) {
                    const safeUrl = fileInfo.url.replace(/"/g, '&quot;');
                    html += '<video controls style="max-width:100%;border-radius:6px;" src="' + safeUrl + '"></video>';
                }
            }

            html += '</div>';
            return html;
        },

        updateBlockVideoFile(sectionIndex, rowIndex, colIndex, blockIndex, filename, fileUrl) {
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            block.block_data = { source: 'file', filename: filename, url: '' };
            if (fileUrl) {
                PageBuilderConfig.videoFiles[filename] = PageBuilderConfig.videoFiles[filename] || { url: fileUrl, title: filename };
            }
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        updateBlockVideoEmbed(sectionIndex, rowIndex, colIndex, blockIndex, url) {
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            block.block_data = { source: 'embed', filename: '', url: url };
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        removeBlockVideo(sectionIndex, rowIndex, colIndex, blockIndex) {
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            block.block_data = { source: 'file', filename: '', url: '' };
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        openVideoSelector(sectionIndex, rowIndex, colIndex, blockIndex) {
            pageVideoSelectContext = { si: sectionIndex, ri: rowIndex, ci: colIndex, bi: blockIndex };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pageVideoSelectorModal')).show();
        },

        openVideoUpload(sectionIndex, rowIndex, colIndex, blockIndex) {
            pageVideoUploadContext = { si: sectionIndex, ri: rowIndex, ci: colIndex, bi: blockIndex };
            document.getElementById('pageUploadVideoInput').value = '';
            document.getElementById('pageUploadVideoStatus').textContent = '';
            document.getElementById('pageUploadVideoProgressBar').style.width = '0%';
            document.getElementById('pageUploadVideoProgressBar').textContent = '';
            document.getElementById('pageUploadVideoProgressContainer').style.display = 'none';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pageUploadVideoModal')).show();
        },

        openVideoEmbedModal(sectionIndex, rowIndex, colIndex, blockIndex) {
            blockVideoEmbedContext = { si: sectionIndex, ri: rowIndex, ci: colIndex, bi: blockIndex };
            const block = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks[blockIndex];
            const currentUrl = block.block_data?.source === 'embed' ? (block.block_data.url || '') : '';
            document.getElementById('blockVideoEmbedInput').value = currentUrl;
            document.getElementById('blockVideoEmbedError').style.display = 'none';
            document.getElementById('blockVideoEmbedError').textContent = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('blockVideoEmbedModal')).show();
            setTimeout(() => document.getElementById('blockVideoEmbedInput').focus(), 300);
        },

        deleteBlock(sectionIndex, rowIndex, colIndex, blockIndex) {
            if (confirm(PageBuilderConfig.confirm_delete_block)) {
                this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks.splice(blockIndex, 1);
                this.reindex();
                this.render();
                this.updateCodeView();
                this.updatePreview();
            }
        },

        moveBlock(sectionIndex, rowIndex, colIndex, blockIndex, direction) {
            const blocks = this.structure.sections[sectionIndex].rows[rowIndex].columns[colIndex].blocks;
            const newIndex = blockIndex + direction;
            if (newIndex < 0 || newIndex >= blocks.length) return;
            [blocks[blockIndex], blocks[newIndex]] = [blocks[newIndex], blocks[blockIndex]];
            this.render();
        },

        reindex() {
            for (let si = 0; si < this.structure.sections.length; si++) {
                const section = this.structure.sections[si];
                section.sort_order = si;
                for (let ri = 0; ri < (section.rows || []).length; ri++) {
                    section.rows[ri].sort_order = ri;
                    for (let ci = 0; ci < (section.rows[ri].columns || []).length; ci++) {
                        section.rows[ri].columns[ci].sort_order = ci;
                        for (let bi = 0; bi < (section.rows[ri].columns[ci].blocks || []).length; bi++) {
                            section.rows[ri].columns[ci].blocks[bi].sort_order = bi;
                        }
                    }
                }
            }
        },

        buildElementAttrs(el, baseClass = '', baseStyle = '') {
            let className = baseClass;
            if (el.css_class) {
                className = className ? className + ' ' + el.css_class : el.css_class;
            }
            let style = baseStyle;
            if (el.inline_css) {
                style = style ? style.replace(/;?\s*$/, '; ') + el.inline_css : el.inline_css;
            }
            let attrs = '';
            if (className) {
                attrs += ' class="' + className.replace(/"/g, '&quot;') + '"';
            }
            if (el.css_id) {
                attrs += ' id="' + String(el.css_id).replace(/"/g, '&quot;') + '"';
            }
            if (style) {
                attrs += ' style="' + style.replace(/"/g, '&quot;') + '"';
            }
            return attrs;
        },

        renderSettingsBtn(type, si, ri, ci, bi) {
            const title = PageBuilderConfig.element_settings;
            const args = [si];
            if (ri !== undefined && ri !== null) args.push(ri);
            if (ci !== undefined && ci !== null) args.push(ci);
            if (bi !== undefined && bi !== null) args.push(bi);
            return `<button type="button" class="btn btn-outline-secondary btn-sm" onclick="PageBuilder.openElementSettings('${type}', ${args.join(', ')})" title="${title}"><i class="bi bi-gear"></i></button>`;
        },

        getElement(type, si, ri, ci, bi) {
            if (type === 'section') return this.structure.sections[si];
            if (type === 'row') return this.structure.sections[si].rows[ri];
            if (type === 'column') return this.structure.sections[si].rows[ri].columns[ci];
            if (type === 'block') return this.structure.sections[si].rows[ri].columns[ci].blocks[bi];
            return null;
        },

        openElementSettings(type, si, ri, ci, bi) {
            const el = this.getElement(type, si, ri, ci, bi);
            if (!el) return;
            this.elementSettingsContext = { type, si, ri, ci, bi };
            const titles = {
                section: PageBuilderConfig.element_settings_section,
                row: PageBuilderConfig.element_settings_row,
                column: PageBuilderConfig.element_settings_column,
                block: PageBuilderConfig.element_settings_block,
            };
            document.getElementById('elementSettingsLabel').textContent = titles[type] || PageBuilderConfig.element_settings;
            document.getElementById('elementCssClass').value = el.css_class || '';
            document.getElementById('elementCssId').value = el.css_id || '';
            document.getElementById('elementInlineCss').value = el.inline_css || '';

            const widthGroup = document.getElementById('elementWidthGroup');
            const colCount = type === 'column' ? this.structure.sections[si].rows[ri].columns.length : 0;
            if (type === 'column' && colCount > 1) {
                // Fixed floor per column (not 100/colCount) so the slider always has a usable
                // range instead of min and max collapsing to the same value at low column counts.
                const minWidth = this.MIN_COLUMN_WIDTH;
                const maxWidth = 100 - (colCount - 1) * minWidth;
                const widthInput = document.getElementById('elementWidth');
                widthInput.min = minWidth;
                widthInput.max = maxWidth;
                widthInput.value = Math.round(el.width);
                document.getElementById('elementWidthValue').textContent = Math.round(el.width) + '%';
                widthGroup.style.display = '';
            } else {
                widthGroup.style.display = 'none';
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('elementSettingsModal')).show();
        },

        saveElementSettings() {
            const ctx = this.elementSettingsContext;
            if (!ctx) return;
            const el = this.getElement(ctx.type, ctx.si, ctx.ri, ctx.ci, ctx.bi);
            if (!el) return;
            el.css_class = document.getElementById('elementCssClass').value.trim();
            el.css_id = document.getElementById('elementCssId').value.trim();
            el.inline_css = document.getElementById('elementInlineCss').value.trim();
            if (ctx.type === 'column') {
                const row = this.structure.sections[ctx.si].rows[ctx.ri];
                if (row.columns.length > 1) {
                    this.applyColumnWidth(ctx.si, ctx.ri, ctx.ci, Number(document.getElementById('elementWidth').value));
                }
            }
            bootstrap.Modal.getInstance(document.getElementById('elementSettingsModal')).hide();
            this.elementSettingsContext = null;
            this.render();
            this.updateCodeView();
            this.updatePreview();
        },

        render() {
            const container = document.getElementById('sectionsContainer');
            if (!container) {
                console.error('sectionsContainer not found');
                return;
            }
            if (!this.structure.sections || this.structure.sections.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-layout-three-text fs-1 d-block mb-2"></i>' + PageBuilderConfig.builder_empty + '</div>';
                return;
            }

            let html = '';
            this.structure.sections.forEach((section, si) => {
                html += this.renderSection(section, si);
            });
            container.innerHTML = html;

            // Destroy any existing editors, then initialize new ones after DOM settles
            this.destroyEditors(container);
            requestAnimationFrame(() => this.initEditors(container));
        },

        renderSection(section, si) {
            const rowsHtml = (section.rows || []).map((row, ri) => this.renderRow(row, si, ri)).join('');
            const emptyText = PageBuilderConfig.empty_section;
            const sectionLabel = PageBuilderConfig.section;
            const settingsLabel = PageBuilderConfig.element_settings;
            const deleteLabel = PageBuilderConfig.delete;
            const addRowLabel = PageBuilderConfig.add_row;
            const columnLabel = PageBuilderConfig.column;
            const columnsLabel = columnLabel + 's';
            const dragPath = JSON.stringify([si]);
            return `
                <div class="builder-section border rounded p-3 mb-3 bg-white drag-target" data-section="${si}" draggable="true" data-drag-type="section" data-drag-path="${dragPath}">
                    <div class="builder-section-header d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="fw-semibold">
                            <i class="bi bi-grip-vertical builder-drag-handle" title="Drag to reorder"></i>
                            <i class="bi bi-layout-split"></i> ${sectionLabel} ${si + 1}
                        </span>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.openElementSettings('section', ${si}); return false;"><i class="bi bi-gear me-2"></i>${settingsLabel}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="PageBuilder.deleteSection(${si}); return false;"><i class="bi bi-trash me-2"></i>${deleteLabel}</a></li>
                            </ul>
                        </div>
                    </div>
                    ${rowsHtml || '<p class="text-muted text-center mb-0">' + emptyText + '</p>'}
                    <div class="btn-group mt-2" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="PageBuilder.addRow(${si}, 1)">
                            <i class="bi bi-plus"></i> ${addRowLabel} (1 ${columnLabel})
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="PageBuilder.addRow(${si}, 2)">
                            <i class="bi bi-plus"></i> ${addRowLabel} (2 ${columnsLabel})
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="PageBuilder.addRow(${si}, 3)">
                            <i class="bi bi-plus"></i> ${addRowLabel} (3 ${columnsLabel})
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="PageBuilder.addRow(${si}, 4)">
                            <i class="bi bi-plus"></i> ${addRowLabel} (4 ${columnsLabel})
                        </button>
                    </div>
                </div>
            `;
        },

        renderRow(row, si, ri) {
            const colsHtml = (row.columns || []).map((col, ci) => this.renderColumn(col, si, ri, ci)).join('');
            const rowLabel = PageBuilderConfig.row;
            const columnLabel = PageBuilderConfig.column;
            const settingsLabel = PageBuilderConfig.element_settings;
            const addColTitle = PageBuilderConfig.add_column;
            const deleteLabel = PageBuilderConfig.delete;
            const emptyRowText = PageBuilderConfig.empty_row;
            const dragPath = JSON.stringify([si, ri]);
            return `
                <div class="builder-row border rounded p-2 mb-2 bg-light drag-target" data-row="${ri}" draggable="true" data-drag-type="row" data-drag-path="${dragPath}">
                    <div class="builder-row-header d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            <i class="bi bi-grip-vertical builder-drag-handle" title="Drag to reorder"></i>
                            ${rowLabel} ${ri + 1}
                        </small>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.openElementSettings('row', ${si}, ${ri}); return false;"><i class="bi bi-gear me-2"></i>${settingsLabel}</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.addColumn(${si}, ${ri}); return false;"><i class="bi bi-plus-square me-2"></i>${addColTitle}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="PageBuilder.deleteRow(${si}, ${ri}); return false;"><i class="bi bi-trash me-2"></i>${deleteLabel}</a></li>
                            </ul>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-layout-split"></i> ${row.columns.length} ${columnLabel}s
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.setRowColumns(${si}, ${ri}, 1); return false;">1 ${columnLabel}</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.setRowColumns(${si}, ${ri}, 2); return false;">2 ${columnLabel}s</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.setRowColumns(${si}, ${ri}, 3); return false;">3 ${columnLabel}s</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.setRowColumns(${si}, ${ri}, 4); return false;">4 ${columnLabel}s</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="builder-row-columns d-flex gap-2">
                        ${colsHtml || '<p class="text-muted text-center mb-0 small">' + emptyRowText + '</p>'}
                    </div>
                </div>
            `;
        },

        renderColumn(col, si, ri, ci) {
            const blocksHtml = (col.blocks || []).map((block, bi) => this.renderBlock(block, si, ri, ci, bi)).join('');
            const columnLabel = PageBuilderConfig.column;
            const settingsLabel = PageBuilderConfig.element_settings;
            const deleteLabel = PageBuilderConfig.delete;
            const emptyColText = PageBuilderConfig.empty_column;
            const addTextBlockLabel = PageBuilderConfig.add_text_block;
            const addImageBlockLabel = PageBuilderConfig.add_image_block;
            const imageFromFilesLabel = PageBuilderConfig.image_from_files;
            const imageUploadLabel = PageBuilderConfig.image_upload;
            const imageFromUrlLabel = PageBuilderConfig.image_from_url;
            const addVideoBlockLabel = PageBuilderConfig.add_video_block;
            const videoFromFilesLabel = PageBuilderConfig.video_from_files;
            const videoUploadLabel = PageBuilderConfig.video_upload;
            const videoEmbedUrlLabel = PageBuilderConfig.video_embed_url;
            const dragPath = JSON.stringify([si, ri, ci]);
            const row = this.structure.sections[si].rows[ri];
            const colCount = row.columns.length;
            return `
                <div class="builder-column border rounded p-2 bg-white drag-target" style="flex: ${col.width}; min-width: 0;" draggable="true" data-drag-type="column" data-drag-path="${dragPath}">
                    <div class="builder-column-header d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            <i class="bi bi-grip-vertical builder-drag-handle" title="Drag to reorder"></i>
                            ${columnLabel} ${ci + 1}${colCount > 1 ? ` <span class="badge text-bg-light border">${Math.round(col.width)}%</span>` : ''}
                        </small>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.openElementSettings('column', ${si}, ${ri}, ${ci}); return false;"><i class="bi bi-gear me-2"></i>${settingsLabel}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="PageBuilder.deleteColumn(${si}, ${ri}, ${ci}); return false;"><i class="bi bi-trash me-2"></i>${deleteLabel}</a></li>
                            </ul>
                        </div>
                    </div>
                    ${blocksHtml || '<p class="text-muted text-center mb-0 small">' + emptyColText + '</p>'}
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" onclick="PageBuilder.addBlock(${si}, ${ri}, ${ci}, 'text')">
                            <i class="bi bi-fonts"></i> ${addTextBlockLabel}
                        </button>
                        <div class="btn-group flex-grow-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle w-100" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                <i class="bi bi-image"></i> ${addImageBlockLabel}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end w-100">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.addImageBlock(${si}, ${ri}, ${ci}, 'file'); return false;"><i class="bi bi-folder2-open me-2"></i>${imageFromFilesLabel}</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.addImageBlock(${si}, ${ri}, ${ci}, 'upload'); return false;"><i class="bi bi-cloud-upload me-2"></i>${imageUploadLabel}</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.addImageBlock(${si}, ${ri}, ${ci}, 'url'); return false;"><i class="bi bi-link-45deg me-2"></i>${imageFromUrlLabel}</a></li>
                            </ul>
                        </div>
                        <div class="btn-group flex-grow-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle w-100" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                <i class="bi bi-camera-video"></i> ${addVideoBlockLabel}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end w-100">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.addVideoBlock(${si}, ${ri}, ${ci}, 'file'); return false;"><i class="bi bi-folder2-open me-2"></i>${videoFromFilesLabel}</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.addVideoBlock(${si}, ${ri}, ${ci}, 'upload'); return false;"><i class="bi bi-cloud-upload me-2"></i>${videoUploadLabel}</a></li>
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.addVideoBlock(${si}, ${ri}, ${ci}, 'embed'); return false;"><i class="bi bi-link-45deg me-2"></i>${videoEmbedUrlLabel}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;
        },

        renderBlock(block, si, ri, ci, bi) {
            if (block.block_type === 'image') {
                return this.renderImageBlock(block, si, ri, ci, bi);
            }
            if (block.block_type === 'video') {
                return this.renderVideoBlock(block, si, ri, ci, bi);
            }
            return this.renderTextBlock(block, si, ri, ci, bi);
        },

        renderTextBlock(block, si, ri, ci, bi) {
            const text = block.block_data?.text || '';
            const textBlockLabel = PageBuilderConfig.text_block;
            const settingsLabel = PageBuilderConfig.element_settings;
            const deleteLabel = PageBuilderConfig.delete;
            const textPlaceholder = PageBuilderConfig.text_placeholder;
            const dragPath = JSON.stringify([si, ri, ci, bi]);
            const editorId = 'blockTextEditor_' + si + '_' + ri + '_' + ci + '_' + bi;
            return `
                <div class="builder-block border rounded p-2 mb-2 bg-light drag-target" draggable="true" data-drag-type="block" data-drag-path="${dragPath}">
                    <div class="builder-block-header d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            <i class="bi bi-grip-vertical builder-drag-handle" title="Drag to reorder"></i>
                            ${textBlockLabel}
                        </small>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.openElementSettings('block', ${si}, ${ri}, ${ci}, ${bi}); return false;"><i class="bi bi-gear me-2"></i>${settingsLabel}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="PageBuilder.deleteBlock(${si}, ${ri}, ${ci}, ${bi}); return false;"><i class="bi bi-trash me-2"></i>${deleteLabel}</a></li>
                            </ul>
                        </div>
                    </div>
                    <div id="${editorId}" class="block-text-editor" data-keys="${si},${ri},${ci},${bi}" data-initial-content="${encodeURIComponent(text)}"></div>
                </div>
            `;
        },

        destroyEditors(container) {
            const scope = container || document;
            // Destroy any Summernote instances within the scope
            $(scope).find('.note-editor').each(function() {
                // The note-editor wrapper contains the original element
                const noteId = $(this).attr('id');
                if (noteId) {
                    const original = $(scope).find('#' + noteId.replace('-note', ''));
                    if (original.length && original.data('summernote')) {
                        original.summernote('destroy');
                    }
                }
            });
            // Also try destroying on any .block-text-editor elements that still have summernote data
            $(scope).find('.block-text-editor').each(function() {
                if ($(this).data('summernote')) {
                    try { $(this).summernote('destroy'); } catch(e) {}
                }
            });
        },

        initEditors(container) {
            const self = this;
            const scope = container || document;
            $(scope).find('.block-text-editor').each(function() {
                const $el = $(this);
                // Skip if already initialized
                if ($el.data('summernote') || $el.hasClass('note-editor')) return;
                const initialContent = decodeURIComponent($el.attr('data-initial-content') || '');
                $el.summernote({
                    height: 150,
                    placeholder: PageBuilderConfig.text_placeholder,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['link', 'hr']]
                    ],
                    dialogsInBody: true,
                    callbacks: {
                        onChange: function(contents) {
                            const keys = $el[0].dataset.keys;
                            if (keys) {
                                const [si, ri, ci, bi] = keys.split(',').map(Number);
                                self.updateBlockText(si, ri, ci, bi, contents);
                            }
                        }
                    }
                });
                if (initialContent) {
                    $el.summernote('code', initialContent);
                }
            });
        },

        syncEditors() {
            const self = this;
            $('.block-text-editor').each(function() {
                const $el = $(this);
                if (!$el.data('summernote')) {
                    return;
                }
                const keys = this.dataset.keys;
                if (!keys) {
                    return;
                }
                const [si, ri, ci, bi] = keys.split(',').map(Number);
                self.updateBlockText(si, ri, ci, bi, $el.summernote('code'));
            });
        },

        renderImageBlock(block, si, ri, ci, bi) {
            const imageUrl = this.getImageBlockUrl(block);
            const hasImage = !!imageUrl;
            const imageBlockLabel = PageBuilderConfig.image_block;
            const settingsLabel = PageBuilderConfig.element_settings;
            const deleteLabel = PageBuilderConfig.delete;
            const noImageText = PageBuilderConfig.no_image_selected;
            const removeImageLabel = PageBuilderConfig.remove_image;
            const imageFromFilesLabel = PageBuilderConfig.image_from_files;
            const imageUploadLabel = PageBuilderConfig.image_upload;
            const imageFromUrlLabel = PageBuilderConfig.image_from_url;
            const dragPath = JSON.stringify([si, ri, ci, bi]);
            const safeUrl = imageUrl.replace(/"/g, '&quot;');
            const previewHtml = hasImage
                ? `<img src="${safeUrl}" alt="" class="builder-block-image-preview mb-2">`
                : `<p class="text-muted text-center small mb-2">${noImageText}</p>`;
            const removeBtn = hasImage
                ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="PageBuilder.removeBlockImage(${si}, ${ri}, ${ci}, ${bi})"><i class="bi bi-trash"></i> ${removeImageLabel}</button>`
                : '';
            return `
                <div class="builder-block border rounded p-2 mb-2 bg-light drag-target" draggable="true" data-drag-type="block" data-drag-path="${dragPath}">
                    <div class="builder-block-header d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            <i class="bi bi-grip-vertical builder-drag-handle" title="Drag to reorder"></i>
                            ${imageBlockLabel}
                        </small>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.openElementSettings('block', ${si}, ${ri}, ${ci}, ${bi}); return false;"><i class="bi bi-gear me-2"></i>${settingsLabel}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="PageBuilder.deleteBlock(${si}, ${ri}, ${ci}, ${bi}); return false;"><i class="bi bi-trash me-2"></i>${deleteLabel}</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="text-center">${previewHtml}</div>
                    <div class="d-grid gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="PageBuilder.openImageSelector(${si}, ${ri}, ${ci}, ${bi})">
                            <i class="bi bi-folder2-open"></i> ${imageFromFilesLabel}
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="PageBuilder.openImageUpload(${si}, ${ri}, ${ci}, ${bi})">
                            <i class="bi bi-cloud-upload"></i> ${imageUploadLabel}
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="PageBuilder.openImageUrlModal(${si}, ${ri}, ${ci}, ${bi})">
                            <i class="bi bi-link-45deg"></i> ${imageFromUrlLabel}
                        </button>
                        ${removeBtn}
                    </div>
                </div>
            `;
        },

        renderVideoBlock(block, si, ri, ci, bi) {
            const hasVideo = !!this.getVideoBlockUrl(block);
            const videoBlockLabel = PageBuilderConfig.video_block;
            const settingsLabel = PageBuilderConfig.element_settings;
            const deleteLabel = PageBuilderConfig.delete;
            const noVideoText = PageBuilderConfig.no_video_selected;
            const removeVideoLabel = PageBuilderConfig.remove_video;
            const videoFromFilesLabel = PageBuilderConfig.video_from_files;
            const videoUploadLabel = PageBuilderConfig.video_upload;
            const videoEmbedUrlLabel = PageBuilderConfig.video_embed_url;
            const dragPath = JSON.stringify([si, ri, ci, bi]);
            const previewHtml = hasVideo
                ? this.getVideoBlockPreviewHtml(block).replace('style="margin-bottom:8px;"', 'class="builder-block-video-preview mb-2"')
                : `<p class="text-muted text-center small mb-2">${noVideoText}</p>`;
            const removeBtn = hasVideo
                ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="PageBuilder.removeBlockVideo(${si}, ${ri}, ${ci}, ${bi})"><i class="bi bi-trash"></i> ${removeVideoLabel}</button>`
                : '';
            return `
                <div class="builder-block border rounded p-2 mb-2 bg-light drag-target" draggable="true" data-drag-type="block" data-drag-path="${dragPath}">
                    <div class="builder-block-header d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            <i class="bi bi-grip-vertical builder-drag-handle" title="Drag to reorder"></i>
                            ${videoBlockLabel}
                        </small>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="PageBuilder.openElementSettings('block', ${si}, ${ri}, ${ci}, ${bi}); return false;"><i class="bi bi-gear me-2"></i>${settingsLabel}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="PageBuilder.deleteBlock(${si}, ${ri}, ${ci}, ${bi}); return false;"><i class="bi bi-trash me-2"></i>${deleteLabel}</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="text-center">${previewHtml}</div>
                    <div class="d-grid gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="PageBuilder.openVideoSelector(${si}, ${ri}, ${ci}, ${bi})">
                            <i class="bi bi-folder2-open"></i> ${videoFromFilesLabel}
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="PageBuilder.openVideoUpload(${si}, ${ri}, ${ci}, ${bi})">
                            <i class="bi bi-cloud-upload"></i> ${videoUploadLabel}
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="PageBuilder.openVideoEmbedModal(${si}, ${ri}, ${ci}, ${bi})">
                            <i class="bi bi-link-45deg"></i> ${videoEmbedUrlLabel}
                        </button>
                        ${removeBtn}
                    </div>
                </div>
            `;
        }
    };

    // Initialize on DOM ready
    <?php if (!$isPostsPage): ?>
    document.addEventListener('DOMContentLoaded', () => PageBuilder.init());
    <?php endif; ?>

    // Image selection context: featured image or builder block
    var pageImageSelectContext = null;
    var pageUploadContext = null;
    var blockImageUrlContext = null;

    // Image selection for pages and builder blocks
    window.selectPageImage = function(filename, url) {
        if (pageImageSelectContext && pageImageSelectContext.type === 'block') {
            PageBuilder.updateBlockImageFile(
                pageImageSelectContext.si,
                pageImageSelectContext.ri,
                pageImageSelectContext.ci,
                pageImageSelectContext.bi,
                filename,
                url
            );
            pageImageSelectContext = null;
            bootstrap.Modal.getInstance(document.getElementById('pageImageSelectorModal')).hide();
            return;
        }

        var preview = document.getElementById('pageFeaturedImagePreview');
        preview.innerHTML = '';
        var img = document.createElement('img');
        img.src = url;
        img.alt = '';
        img.className = 'featured-image-preview-img mb-2';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'featured_image';
        input.value = filename;
        preview.appendChild(img);
        preview.appendChild(input);
        pageImageSelectContext = null;
        bootstrap.Modal.getInstance(document.getElementById('pageImageSelectorModal')).hide();
    };

    function setPageFeaturedImage(filename, url) {
        var preview = document.getElementById('pageFeaturedImagePreview');
        preview.innerHTML = '';
        var img = document.createElement('img');
        img.src = url;
        img.alt = '';
        img.className = 'featured-image-preview-img mb-2';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'featured_image';
        input.value = filename;
        preview.appendChild(img);
        preview.appendChild(input);
    }

    // Image URL for builder blocks
    document.getElementById('blockImageUrlBtn').addEventListener('click', function() {
        var urlInput = document.getElementById('blockImageUrlInput');
        var errorEl = document.getElementById('blockImageUrlError');
        var url = urlInput.value.trim();
        if (!url) {
            errorEl.textContent = PageBuilderConfig.invalid_image_url;
            errorEl.style.display = 'block';
            urlInput.focus();
            return;
        }
        try {
            var parsed = new URL(url);
            if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
                throw new Error('invalid protocol');
            }
        } catch (e) {
            errorEl.textContent = PageBuilderConfig.invalid_image_url;
            errorEl.style.display = 'block';
            urlInput.focus();
            return;
        }
        if (!blockImageUrlContext) return;
        PageBuilder.updateBlockImageUrl(
            blockImageUrlContext.si,
            blockImageUrlContext.ri,
            blockImageUrlContext.ci,
            blockImageUrlContext.bi,
            url
        );
        blockImageUrlContext = null;
        bootstrap.Modal.getInstance(document.getElementById('blockImageUrlModal')).hide();
    });

    document.getElementById('blockImageUrlInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('blockImageUrlBtn').click();
        }
    });

    // Image upload for pages and builder blocks
    var pageUploadBtn = document.getElementById('pageUploadBtn');
    var pageUploadInput = document.getElementById('pageUploadInput');
    var pageProgressContainer = document.getElementById('pageUploadProgressContainer');
    var pageProgressBar = document.getElementById('pageUploadProgressBar');
    var pageUploadStatus = document.getElementById('pageUploadStatus');

    pageUploadBtn.addEventListener('click', function() {
        if (!pageUploadInput.files.length) {
            pageUploadInput.focus();
            return;
        }
        uploadPageImage();
    });

    pageUploadInput.addEventListener('change', function() {
        if (this.files.length) {
            uploadPageImage();
        }
    });

    function uploadPageImage() {
        var formData = new FormData(document.getElementById('pageUploadImageForm'));
        pageProgressContainer.style.display = 'block';
        pageUploadStatus.textContent = '';
        pageUploadBtn.disabled = true;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?= admin_url('files/upload-ajax') ?>', true);

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                pageProgressBar.style.width = percent + '%';
                pageProgressBar.textContent = percent + '%';
            }
        });

        xhr.addEventListener('load', function() {
            pageUploadBtn.disabled = false;
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    pageUploadStatus.className = 'text-success';
                    pageUploadStatus.textContent = '<?= __t('file_uploaded') ?>';
                    if (pageUploadContext && pageUploadContext.type === 'block') {
                        PageBuilder.updateBlockImageFile(
                            pageUploadContext.si,
                            pageUploadContext.ri,
                            pageUploadContext.ci,
                            pageUploadContext.bi,
                            response.filename,
                            response.url
                        );
                        pageUploadContext = null;
                    } else {
                        setPageFeaturedImage(response.filename, response.url);
                        pageUploadContext = null;
                    }
                    pageUploadInput.value = '';
                    pageProgressBar.style.width = '0%';
                    pageProgressBar.textContent = '';
                    setTimeout(function() {
                        bootstrap.Modal.getInstance(document.getElementById('pageUploadImageModal')).hide();
                    }, 500);
                } else {
                    pageUploadStatus.className = 'text-danger';
                    pageUploadStatus.textContent = response.error || '<?= __t('upload_failed') ?>';
                }
            } else {
                pageUploadStatus.className = 'text-danger';
                pageUploadStatus.textContent = '<?= __t('upload_failed') ?>';
            }
            pageProgressContainer.style.display = 'none';
        });

        xhr.addEventListener('error', function() {
            pageUploadBtn.disabled = false;
            pageUploadStatus.className = 'text-danger';
            pageUploadStatus.textContent = '<?= __t('upload_failed') ?>';
            pageProgressContainer.style.display = 'none';
        });

        xhr.send(formData);
    }

    // Video selection and upload for builder blocks
    var pageVideoSelectContext = null;
    var pageVideoUploadContext = null;
    var blockVideoEmbedContext = null;

    window.selectPageVideo = function(filename, url) {
        if (!pageVideoSelectContext) return;
        PageBuilder.updateBlockVideoFile(
            pageVideoSelectContext.si,
            pageVideoSelectContext.ri,
            pageVideoSelectContext.ci,
            pageVideoSelectContext.bi,
            filename,
            url
        );
        pageVideoSelectContext = null;
        bootstrap.Modal.getInstance(document.getElementById('pageVideoSelectorModal')).hide();
    };

    document.getElementById('blockVideoEmbedBtn').addEventListener('click', function() {
        var urlInput = document.getElementById('blockVideoEmbedInput');
        var errorEl = document.getElementById('blockVideoEmbedError');
        var url = urlInput.value.trim();
        if (!url) {
            errorEl.textContent = PageBuilderConfig.invalid_video_url;
            errorEl.style.display = 'block';
            urlInput.focus();
            return;
        }
        if (!PageBuilder.parseVideoEmbed(url)) {
            errorEl.textContent = PageBuilderConfig.invalid_video_url;
            errorEl.style.display = 'block';
            urlInput.focus();
            return;
        }
        if (!blockVideoEmbedContext) return;
        PageBuilder.updateBlockVideoEmbed(
            blockVideoEmbedContext.si,
            blockVideoEmbedContext.ri,
            blockVideoEmbedContext.ci,
            blockVideoEmbedContext.bi,
            url
        );
        blockVideoEmbedContext = null;
        bootstrap.Modal.getInstance(document.getElementById('blockVideoEmbedModal')).hide();
    });

    document.getElementById('blockVideoEmbedInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('blockVideoEmbedBtn').click();
        }
    });

    document.getElementById('elementSettingsBtn').addEventListener('click', function() {
        PageBuilder.saveElementSettings();
    });

    var pageUploadVideoBtn = document.getElementById('pageUploadVideoBtn');
    var pageUploadVideoInput = document.getElementById('pageUploadVideoInput');
    var pageUploadVideoProgressContainer = document.getElementById('pageUploadVideoProgressContainer');
    var pageUploadVideoProgressBar = document.getElementById('pageUploadVideoProgressBar');
    var pageUploadVideoStatus = document.getElementById('pageUploadVideoStatus');

    pageUploadVideoBtn.addEventListener('click', function() {
        if (!pageUploadVideoInput.files.length) {
            pageUploadVideoInput.focus();
            return;
        }
        uploadPageVideo();
    });

    pageUploadVideoInput.addEventListener('change', function() {
        if (this.files.length) {
            uploadPageVideo();
        }
    });

    function uploadPageVideo() {
        var formData = new FormData(document.getElementById('pageUploadVideoForm'));
        pageUploadVideoProgressContainer.style.display = 'block';
        pageUploadVideoStatus.textContent = '';
        pageUploadVideoBtn.disabled = true;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?= admin_url('files/upload-ajax') ?>', true);

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                pageUploadVideoProgressBar.style.width = percent + '%';
                pageUploadVideoProgressBar.textContent = percent + '%';
            }
        });

        xhr.addEventListener('load', function() {
            pageUploadVideoBtn.disabled = false;
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    pageUploadVideoStatus.className = 'text-success';
                    pageUploadVideoStatus.textContent = '<?= __t('file_uploaded') ?>';
                    if (pageVideoUploadContext) {
                        PageBuilder.updateBlockVideoFile(
                            pageVideoUploadContext.si,
                            pageVideoUploadContext.ri,
                            pageVideoUploadContext.ci,
                            pageVideoUploadContext.bi,
                            response.filename,
                            response.url
                        );
                        pageVideoUploadContext = null;
                    }
                    pageUploadVideoInput.value = '';
                    pageUploadVideoProgressBar.style.width = '0%';
                    pageUploadVideoProgressBar.textContent = '';
                    setTimeout(function() {
                        bootstrap.Modal.getInstance(document.getElementById('pageUploadVideoModal')).hide();
                    }, 500);
                } else {
                    pageUploadVideoStatus.className = 'text-danger';
                    pageUploadVideoStatus.textContent = response.error || '<?= __t('upload_failed') ?>';
                }
            } else {
                pageUploadVideoStatus.className = 'text-danger';
                pageUploadVideoStatus.textContent = '<?= __t('upload_failed') ?>';
            }
            pageUploadVideoProgressContainer.style.display = 'none';
        });

        xhr.addEventListener('error', function() {
            pageUploadVideoBtn.disabled = false;
            pageUploadVideoStatus.className = 'text-danger';
            pageUploadVideoStatus.textContent = '<?= __t('upload_failed') ?>';
            pageUploadVideoProgressContainer.style.display = 'none';
        });

        xhr.send(formData);
    }

    AdminAutosave.init({
        entityType: 'page',
        entityId: <?= json_encode($id ? (string) $id : 'new') ?>,
        formSelector: '#pageForm',
        serverUpdatedAt: <?= json_encode($page['updated_at'] ?? $page['created_at'] ?? null) ?>,
        statusSelector: '#pageDraftStatus',
        keepaliveUrl: <?= json_encode(admin_url('session/keepalive')) ?>,
        strings: {
            title: <?= json_encode(__t('draft_restore_title')) ?>,
            message: <?= json_encode(__t('draft_restore_message')) ?>,
            restore: <?= json_encode(__t('draft_restore')) ?>,
            discard: <?= json_encode(__t('draft_discard')) ?>,
            savedLocally: <?= json_encode(__t('draft_saved_locally')) ?>,
        },
        beforeCollect: function() {
            if (typeof PageBuilder !== 'undefined') {
                PageBuilder.syncStructure();
            }
        },
        afterRestore: function(fields) {
            if (fields.builder_structure && typeof PageBuilder !== 'undefined') {
                try {
                    const parsed = JSON.parse(fields.builder_structure);
                    PageBuilder.structure = { sections: parsed.sections || [] };
                    PageBuilder.render();
                    PageBuilder.updateCodeView();
                    PageBuilder.updatePreview();
                } catch (e) {
                    /* ignore invalid builder JSON */
                }
            }
        },
        onDirty: function() {
            window.pageFormDirty = true;
        },
        onInit: function(api) {
            if (typeof PageBuilder !== 'undefined') {
                const origMarkDirty = PageBuilder.markDirty.bind(PageBuilder);
                PageBuilder.markDirty = function() {
                    origMarkDirty();
                    api.scheduleSave();
                };
            }
        },
    });
    </script>

    <style>
        .builder-section { transition: box-shadow 0.15s ease; }
        .builder-section:hover { box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .builder-section-header { cursor: default; }
        .builder-row:hover { background: #e8e8e3; }
        .builder-column:hover { box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .builder-block-header small { font-weight: 500; }
        .builder-row-columns { flex-wrap: wrap; }
        .builder-section .btn-group-sm .btn { padding: 0.25rem 0.4rem; font-size: 0.75rem; }
        #codeEditor { tab-size: 2; }
        .nav-tabs .nav-link { color: var(--off-black); }
        .nav-tabs .nav-link.active { font-weight: 600; }
        .collapse-toggle-icon { transition: transform 0.2s ease; transform: rotate(180deg); }
        .card-header.collapsed .collapse-toggle-icon { transform: rotate(0deg); }
        .featured-image-preview-img {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .page-image-option:hover {
            box-shadow: 0 0 0 2px var(--bs-primary);
        }
        .builder-block-image-preview {
            max-width: 100%;
            max-height: 120px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .page-video-option:hover {
            box-shadow: 0 0 0 2px var(--bs-primary);
        }
        .builder-block-video-preview video {
            max-width: 100%;
            border-radius: 4px;
        }
        .builder-block-video-preview iframe {
            border-radius: 4px;
        }

        /* Drag and Drop Styles */
        .builder-drag-handle {
            cursor: grab;
            user-select: none;
            color: #999;
            margin-right: 0.25rem;
            display: inline-flex;
            align-items: center;
        }
        .builder-drag-handle:hover {
            color: #333;
        }
        .builder-drag-handle:active {
            cursor: grabbing;
        }
        .builder-dragging {
            opacity: 0.4;
        }
        .builder-drag-over-top {
            border-top: 3px solid #0d6efd !important;
        }
        .builder-drag-over-bottom {
            border-bottom: 3px solid #0d6efd !important;
        }
        .builder-drag-over-left {
            border-left: 3px solid #0d6efd !important;
        }
        .builder-drag-over-right {
            border-right: 3px solid #0d6efd !important;
        }
        .builder-section.drag-target,
        .builder-row.drag-target,
        .builder-column.drag-target,
        .builder-block.drag-target {
            transition: border-color 0.15s ease;
        }
    </style>

    <!-- Delete & Versions Row -->
    <?php if ($id): ?>
    <?php $pageSlug = admin_page_slug($page); ?>
    <div class="card mt-3">
        <div class="card-body py-3">
            <div class="d-flex gap-2">
                <?php if (!$isPostsPage): ?>
                <a href="<?= admin_entity_url('pages', 'versions', $pageSlug) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> <?= __t('versions') ?></a>
                <?= post_action_form(
                    admin_entity_url('pages', 'delete', $pageSlug),
                    __t('delete'),
                    __t('confirm_delete'),
                    'btn btn-outline-danger btn-sm',
                    [],
                    'bi bi-trash'
                ) ?>
                <?php else: ?>
                <span class="text-muted small"><?= __t('protected_page') ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
    $summernoteCss = '<link href="' . asset('summernote/summernote-bs5.min.css') . '" rel="stylesheet">';
    admin_layout($id ? ($page['title'] ?? __t('new_page')) : __t('new_page'), ob_get_clean(), false, $summernoteCss);
    return;
}

$pages = ContentManager::getPages();

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('pages'), 'url' => admin_url('pages')]
]) ?>
<div class="d-flex justify-content-end mb-4">
    <a href="<?= admin_url('pages/new') ?>" class="btn btn-dark"><i class="bi bi-plus"></i> <?= __t('new_page') ?></a>
</div>
<?= admin_flash() ?>

<style>
    .page-title {
        font-weight: 500;
    }
    .page-slug {
        font-size: 0.82em;
        color: #6c757d;
    }
    .page-date {
        font-weight: 500;
    }
    .page-user {
        font-size: 0.82em;
        color: #6c757d;
    }
    .page-row {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    .page-row:hover {
        background-color: #f8f9fa !important;
    }
    .page-row:hover td {
        color: unset;
    }
    .page-preview-cell {
        width: 64px;
        padding: 8px !important;
    }
    .page-preview-img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }
    .page-preview-icon {
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
    <?php if (empty($pages)): ?>
    <div class="p-4 text-center text-muted"><?= __t('no_pages_yet') ?></div>
    <?php else: ?>
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th style="width: 64px;"></th>
                <th><?= __t('page') ?></th>
                <th><?= __t('status') ?></th>
                <th><?= __t('created') ?></th>
                <th><?= __t('updated') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
            <?php $hasFeatured = !empty($p['featured_image']); ?>
            <tr class="page-row" onclick="window.location.href='<?= esc(admin_entity_url('pages', 'edit', admin_page_slug($p))) ?>'">
                <td class="page-preview-cell text-center">
                    <?php if ($hasFeatured): ?>
                        <img src="<?= esc(file_url($p['featured_image'])) ?>" alt="" class="page-preview-img" loading="lazy">
                    <?php else: ?>
                        <span class="page-preview-icon">
                            <i class="bi bi-file-earmark fs-4"></i>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="page-title">
                        <?= esc($p['title']) ?>
                        <?php if (ContentManager::isPostsPage($p)): ?>
                            <span class="badge bg-secondary ms-1"><?= __t('system') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="page-slug"><?= esc(ContentManager::isPostsPage($p) && ContentManager::isPostsPageHomepage() ? '/' : ($p['slug'] ?: '/')) ?></div>
                </td>
                <td><span class="badge <?= $p['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>"><?= esc($p['status']) ?></span></td>
                <td>
                    <div class="page-date"><?= format_date($p['created_at'], Auth::getUserTimezone()) ?></div>
                    <div class="page-user"><?= esc($p['author_name'] ?? '') ?></div>
                </td>
                <td>
                    <?php if ($p['updated_at'] && $p['updated_at'] !== $p['created_at']): ?>
                        <div class="page-date"><?= format_date($p['updated_at'], Auth::getUserTimezone()) ?></div>
                        <div class="page-user"><?= esc($p['author_name'] ?? '') ?></div>
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
admin_layout(__t('pages'), ob_get_clean());
