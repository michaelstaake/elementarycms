<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

use Elementary\ContentManager;
use Elementary\Auth;
use Elementary\FileManager;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($action === 'restore_page' && $id) {
        $page = ContentManager::getDeletedPage($id);
        if ($page) {
            ContentManager::restorePage($id);
            flash('success', __t('page_restored'));
        }
    } elseif ($action === 'restore_post' && $id) {
        $post = ContentManager::getDeletedPost($id);
        if ($post) {
            ContentManager::restorePost($id);
            flash('success', __t('post_restored'));
        }
    } elseif ($action === 'delete_page' && $id) {
        $page = ContentManager::getDeletedPage($id);
        if ($page) {
            if (ContentManager::permanentlyDeletePage($id)) {
                flash('success', __t('page_permanently_deleted'));
            } else {
                flash('error', __t('protected_page'));
            }
        }
    } elseif ($action === 'delete_post' && $id) {
        $post = ContentManager::getDeletedPost($id);
        if ($post) {
            ContentManager::permanentlyDeletePost($id);
            flash('success', __t('post_permanently_deleted'));
        }
    } elseif ($action === 'restore_file' && $id) {
        $file = FileManager::getDeletedFile($id);
        if ($file) {
            FileManager::restoreFile($id);
            flash('success', __t('file_restored'));
        }
    } elseif ($action === 'delete_file' && $id) {
        $file = FileManager::getDeletedFile($id);
        if ($file) {
            FileManager::permanentlyDeleteFile($id);
            flash('success', __t('file_permanently_deleted'));
        }
    } elseif ($action === 'empty') {
        ContentManager::emptyRecycleBin();
        flash('success', __t('recycle_bin_emptied'));
    }

    redirect(admin_url('settings/recycle-bin'));
}

$deletedPages = ContentManager::getDeletedPages();
$deletedPosts = ContentManager::getDeletedPosts();
$deletedFiles = FileManager::getDeletedFiles();

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('recycle_bin'), 'url' => '#']
]) ?>
<?= admin_flash() ?>

<?php if (empty($deletedPages) && empty($deletedPosts) && empty($deletedFiles)): ?>
<div class="card p-4 text-center text-muted">
    <i class="bi bi-trash3" style="font-size: 2rem;"></i>
    <p class="mb-0 mt-2"><?= __t('recycle_bin_empty') ?></p>
</div>
<?php else: ?>

<?php if (!empty($deletedPages)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><?= __t('pages') ?></h2>
        <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="empty">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= __t('confirm_empty_recycle_bin') ?>')"><?= __t('empty_recycle_bin') ?></button>
        </form>
    </div>
    <table class="table mb-0">
        <thead><tr><th><?= __t('title') ?></th><th><?= __t('slug') ?></th><th><?= __t('author') ?></th><th><?= __t('deleted_at') ?></th><th class="text-end"><?= __t('actions') ?></th></tr></thead>
        <tbody>
        <?php foreach ($deletedPages as $p): ?>
            <tr>
                <td><?= esc($p['title']) ?></td>
                <td><code><?= esc($p['slug']) ?></code></td>
                <td><?= esc($p['author_name'] ?? '') ?></td>
                <td><?= format_date($p['deleted_at'], Auth::getUserTimezone()) ?></td>
                <td class="text-end">
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore_page">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success"><?= __t('restore') ?></button>
                    </form>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_page">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= __t('confirm_permanent_delete') ?>')"><?= __t('delete') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($deletedPosts)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><?= __t('posts') ?></h2>
        <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="empty">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= __t('confirm_empty_recycle_bin') ?>')"><?= __t('empty_recycle_bin') ?></button>
        </form>
    </div>
    <table class="table mb-0">
        <thead><tr><th><?= __t('title') ?></th><th><?= __t('slug') ?></th><th><?= __t('author') ?></th><th><?= __t('deleted_at') ?></th><th class="text-end"><?= __t('actions') ?></th></tr></thead>
        <tbody>
        <?php foreach ($deletedPosts as $p): ?>
            <tr>
                <td><?= esc($p['title']) ?></td>
                <td><code><?= esc($p['slug']) ?></code></td>
                <td><?= esc($p['author_name'] ?? '') ?></td>
                <td><?= format_date($p['deleted_at'], Auth::getUserTimezone()) ?></td>
                <td class="text-end">
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore_post">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success"><?= __t('restore') ?></button>
                    </form>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_post">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= __t('confirm_permanent_delete') ?>')"><?= __t('delete') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($deletedFiles)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><?= __t('files') ?></h2>
        <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="empty">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= __t('confirm_empty_recycle_bin') ?>')"><?= __t('empty_recycle_bin') ?></button>
        </form>
    </div>
    <table class="table mb-0">
        <thead><tr><th><?= __t('title') ?></th><th><?= __t('filename') ?></th><th><?= __t('uploaded_by') ?></th><th><?= __t('deleted_at') ?></th><th class="text-end"><?= __t('actions') ?></th></tr></thead>
        <tbody>
        <?php foreach ($deletedFiles as $f): ?>
            <tr>
                <td><?= esc($f['title']) ?></td>
                <td><code><?= esc($f['filename']) ?></code></td>
                <td><?= esc($f['uploader_name'] ?? '') ?></td>
                <td><?= format_date($f['deleted_at'], Auth::getUserTimezone()) ?></td>
                <td class="text-end">
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore_file">
                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success"><?= __t('restore') ?></button>
                    </form>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_file">
                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= __t('confirm_permanent_delete') ?>')"><?= __t('delete') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>
<?php
admin_layout(__t('recycle_bin'), ob_get_clean());
