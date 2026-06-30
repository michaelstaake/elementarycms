<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$total = (int) Database::fetch('SELECT COUNT(*) as cnt FROM logs')['cnt'];
$logs = Database::fetchAll(
    'SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT ? OFFSET ?',
    [$perPage, $offset]
);

ob_start();
?>
<?= admin_breadcrumb([
    ['label' => __t('settings'), 'url' => admin_url('settings')],
    ['label' => __t('logs'), 'url' => '#']
]) ?>
<p class="text-muted"><?= __t('logs_help') ?></p>

<div class="card">
    <table class="table mb-0 table-sm">
        <thead><tr><th><?= __t('date') ?></th><th><?= __t('user') ?></th><th><?= __t('action') ?></th><th><?= __t('details') ?></th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="text-nowrap"><?= format_date($log['created_at'], Auth::getUserTimezone()) ?></td>
                <td><?= esc($log['username'] ?? __t('system')) ?></td>
                <td><code><?= esc($log['action']) ?></code></td>
                <td><?= esc($log['details'] ?? '') ?></td>
                <td class="text-nowrap"><?= esc($log['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($total > $perPage): ?>
<nav class="mt-3">
    <ul class="pagination">
        <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= admin_url('logs?page=' . $i) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php
admin_layout(__t('logs'), ob_get_clean());
