<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$action = $segments[1] ?? 'index';
$identifier = isset($segments[2]) ? rawurldecode($segments[2]) : null;
$id = null;

if ($identifier !== null && $action !== 'new') {
    $resolvedTag = ContentManager::resolveTagAdminIdentifier($identifier);
    if (!$resolvedTag) {
        throw new RuntimeException('Tag not found', 404);
    }
    $id = (int) $resolvedTag['id'];
}

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = ContentManager::deleteTag($id);
    if ($result['success']) {
        flash('success', __t('tag_deleted'));
    } else {
        flash('error', $result['error']);
    }
    redirect(admin_url('tags'));
}

if ($action === 'edit' || $action === 'new') {
    $tag = $id ? Database::fetch('SELECT * FROM tags WHERE id = ?', [$id]) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
        ];
        $result = ContentManager::saveTag($data, $id);
        if ($result['success']) {
            flash('success', __t('tag_saved'));
            redirect(admin_entity_url('tags', 'edit', $result['slug']));
        }
        flash('error', $result['error']);
        $tag = array_merge($tag ?? [], $data);
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('tags'), 'url' => admin_url('tags')],
        ['label' => $id ? __t('edit_tag') : __t('new_tag'), 'url' => '#']
    ]) ?>
    <?= admin_flash() ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card p-4">
                    <div class="mb-3">
                        <label class="form-label"><?= __t('tag_name') ?></label>
                        <input type="text" name="name" class="form-control" required value="<?= esc($tag['name'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
            <a href="<?= admin_url('tags') ?>" class="btn btn-outline-secondary"><?= __t('cancel') ?></a>
        </div>
    </form>
    <?php
    admin_layout($id ? __t('edit_tag') : __t('new_tag'), ob_get_clean());
    return;
}

$tags = ContentManager::getTags();

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('tags'), 'url' => admin_url('tags')]
]) ?>
<div class="d-flex justify-content-end mb-4">
    <a href="<?= admin_url('tags/new') ?>" class="btn btn-dark"><i class="bi bi-plus"></i> <?= __t('new_tag') ?></a>
</div>
<?= admin_flash() ?>
<div class="card">
    <?php if (empty($tags)): ?>
    <div class="p-4 text-center text-muted"><?= __t('no_tags_yet') ?></div>
    <?php else: ?>
    <table class="table mb-0">
        <thead><tr><th><?= __t('tag_name') ?></th><th><?= __t('tag_slug') ?></th><th><?= __t('tag_post_count') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tags as $t): ?>
            <tr>
                <td><?= esc($t['name']) ?></td>
                <td><code><?= esc($t['slug']) ?></code></td>
                <td><?= (int) $t['post_count'] ?></td>
                <td class="text-end">
                    <a href="<?= admin_entity_url('tags', 'edit', $t['slug']) ?>" class="btn btn-sm btn-outline-dark"><?= __t('edit') ?></a>
                    <?= post_action_form(
                        admin_entity_url('tags', 'delete', $t['slug']),
                        __t('delete'),
                        __t('confirm_delete'),
                        'btn btn-sm btn-outline-danger'
                    ) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
admin_layout(__t('tags'), ob_get_clean());
