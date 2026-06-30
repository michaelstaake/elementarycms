<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$action = $segments[1] ?? 'index';
$identifier = isset($segments[2]) ? rawurldecode($segments[2]) : null;
$currentUserId = (int) Auth::user()['id'];
$id = null;

if ($identifier !== null && $action !== 'new') {
    $resolvedUser = Auth::getUserByUsername($identifier);
    if (!$resolvedUser) {
        throw new RuntimeException('User not found', 404);
    }
    $id = (int) $resolvedUser['id'];
}

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id === $currentUserId) {
        flash('error', __t('edit_own_account_via_profile'));
        redirect(admin_url('profile'));
    }
    if (!Auth::isProtectedUser($id)) {
        $target = Database::fetch('SELECT username FROM users WHERE id = ?', [$id]);
        if ($target) {
            Database::delete('users', 'id = ?', [$id]);
            Logger::log('user_deleted', $currentUserId, 'Deleted user: ' . $target['username']);
            flash('success', __t('user_deleted'));
        }
    } else {
        flash('error', __t('protected_user'));
    }
    redirect(admin_url('users'));
}

if ($action === 'edit' || $action === 'new') {
    if ($action === 'edit' && $id === $currentUserId) {
        flash('error', __t('edit_own_account_via_profile'));
        redirect(admin_url('profile'));
    }

    $user = $id ? Database::fetch('SELECT * FROM users WHERE id = ?', [$id]) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'username'     => trim($_POST['username'] ?? ''),
            'display_name' => trim($_POST['display_name'] ?? ''),
            'email'        => trim($_POST['email'] ?? ''),
            'user_group'   => $_POST['user_group'] ?? 'standard',
            'status'       => $_POST['status'] ?? 'active',
            'timezone'     => $_POST['timezone'] ?? 'system',
        ];

        $usernameError = Validator::validateUsername($data['username']);
        $emailError = Validator::validateEmail($data['email']);

        if ($usernameError || $emailError) {
            flash('error', $usernameError ?: $emailError);
        } elseif ($id && Auth::isProtectedUser($id) && $currentUserId !== $id) {
            flash('error', __t('protected_user'));
        } else {
            $existing = Database::fetch('SELECT id FROM users WHERE username = ? AND id != ?', [$data['username'], $id ?? 0]);
            if ($existing) {
                flash('error', __t('username_exists'));
            } else {
                $now = date('Y-m-d H:i:s');
                if ($id) {
                    $update = [
                        'username'     => $data['username'],
                        'display_name' => $data['display_name'],
                        'email'        => $data['email'],
                        'user_group'   => $data['user_group'],
                        'status'       => $data['status'],
                        'timezone'     => $data['timezone'],
                        'updated_at'   => $now,
                    ];
                    if (!empty($_POST['password'])) {
                        $update['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    }
                    Database::update('users', $update, 'id = ?', [$id]);
                    Logger::log('user_updated', $currentUserId, 'User ID: ' . $id);
                    flash('success', __t('user_saved'));
                    redirect(admin_entity_url('users', 'edit', $data['username']));
                } else {
                    if (empty($_POST['password'])) {
                        flash('error', __t('password_required'));
                        redirect(admin_url('users/new'));
                    }
                    Database::insert('users', array_merge($data, [
                        'password'   => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                    Logger::log('user_created', $currentUserId, 'Username: ' . $data['username']);
                    flash('success', __t('user_saved'));
                    redirect(admin_url('users'));
                }
            }
        }
        $user = array_merge($user ?? [], $data);
    }

    ob_start();
    ?>
    <?= admin_breadcrumb([
        ['label' => __t('users'), 'url' => admin_url('users')],
        ['label' => $id ? __t('edit_user') : __t('new_user'), 'url' => '#']
    ]) ?>
    <?= admin_flash() ?>
    <form method="post" class="card p-4" style="max-width: 600px;">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label"><?= __t('username') ?></label>
            <input type="text" name="username" class="form-control" required value="<?= esc($user['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('display_name') ?></label>
            <input type="text" name="display_name" class="form-control" value="<?= esc($user['display_name'] ?? '') ?>" placeholder="<?= __t('display_name_placeholder') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('email') ?></label>
            <input type="email" name="email" class="form-control" required value="<?= esc($user['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('password') ?><?= $id ? ' (' . __t('leave_blank') . ')' : '' ?></label>
            <input type="password" name="password" class="form-control" <?= $id ? '' : 'required' ?>>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('user_group') ?></label>
            <select name="user_group" class="form-select">
                <option value="admin" <?= ($user['user_group'] ?? '') === 'admin' ? 'selected' : '' ?>><?= __t('group_admin') ?></option>
                <option value="standard" <?= ($user['user_group'] ?? 'standard') === 'standard' ? 'selected' : '' ?>><?= __t('group_standard') ?></option>
                <option value="author" <?= ($user['user_group'] ?? '') === 'author' ? 'selected' : '' ?>><?= __t('group_author') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('status') ?></label>
            <select name="status" class="form-select">
                <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>><?= __t('active') ?></option>
                <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= __t('inactive') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= __t('timezone') ?></label>
            <select name="timezone" class="form-select">
                <option value="system" <?= ($user['timezone'] ?? 'system') === 'system' ? 'selected' : '' ?>><?= __t('use_system_timezone') ?></option>
                <?php foreach (timezone_identifiers_list() as $tz): ?>
                    <option value="<?= esc($tz) ?>" <?= ($user['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= esc($tz) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
        <a href="<?= admin_url('users') ?>" class="btn btn-outline-secondary"><?= __t('cancel') ?></a>
    </form>
    <?php
    admin_layout($id ? __t('edit_user') : __t('new_user'), ob_get_clean());
    return;
}

$users = Database::fetchAll('SELECT * FROM users WHERE id != ? ORDER BY username ASC', [$currentUserId]);

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('users'), 'url' => admin_url('users')]
]) ?>
<div class="d-flex justify-content-end mb-4">
    <a href="<?= admin_url('users/new') ?>" class="btn btn-dark"><i class="bi bi-plus"></i> <?= __t('new_user') ?></a>
</div>
<?= admin_flash() ?>
<div class="card">
    <table class="table mb-0">
        <thead><tr><th><?= __t('username') ?></th><th><?= __t('email') ?></th><th><?= __t('user_group') ?></th><th><?= __t('status') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= esc($u['username']) ?></td>
                <td><?= esc($u['email']) ?></td>
                <td><?= esc(__t('group_' . $u['user_group'])) ?></td>
                <td><span class="badge <?= $u['status'] === 'active' ? 'badge-published' : 'badge-draft' ?>"><?= esc($u['status']) ?></span></td>
                <td class="text-end">
                    <a href="<?= admin_entity_url('users', 'edit', $u['username']) ?>" class="btn btn-sm btn-outline-dark"><?= __t('edit') ?></a>
                    <?php if (!Auth::isProtectedUser((int) $u['id'])): ?>
                        <?= post_action_form(
                            admin_entity_url('users', 'delete', $u['username']),
                            __t('delete'),
                            __t('confirm_delete'),
                            'btn btn-sm btn-outline-danger'
                        ) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
admin_layout(__t('users'), ob_get_clean());
