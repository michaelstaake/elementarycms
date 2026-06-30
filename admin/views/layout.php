<?php

declare(strict_types=1);

function admin_layout(string $title, string $content, bool $fullWidth = false, string $headExtra = ''): void
{
    $appName = \Elementary\Config::get('app_name', 'Elementary');
    $user = \Elementary\Auth::user();
    $currentPage = explode('/', trim($_GET['route'] ?? '', '/'))[1] ?? 'dashboard';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?> - <?= esc($appName) ?></title>
    <link href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <style>
        :root { --off-black: #1a1a1a; --off-white: #f5f5f0; --border: #e0e0d8; }
        body { background: var(--off-white); color: var(--off-black); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .sidebar { background: #fff; border-right: 1px solid var(--border); min-height: 100vh; }
        .sidebar .nav-link { color: var(--off-black); padding: 0.6rem 1rem; border-radius: 4px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--off-white); color: var(--off-black); }
        .sidebar .nav-link.active { font-weight: 600; }
        .main-content {
            padding: 2rem 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }
        .card { border: 1px solid var(--border); box-shadow: none; }
        .btn-dark { background: var(--off-black); border-color: var(--off-black); }
        .btn-dark:hover { background: #333; border-color: #333; }
        .table th { font-weight: 600; border-bottom-width: 1px; }
        .badge-draft { background: #e0e0d8; color: #1a1a1a; }
        .badge-published { background: #1a1a1a; color: #f5f5f0; }
        .diff-added { background: #d4edda; text-decoration: none; display: block; }
        .diff-removed { background: #f8d7da; text-decoration: line-through; display: block; }

        /* GitHub-style diff */
        .diff-stats-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
            font-size: 0.85rem;
        }
        .diff-stat {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .diff-stat-added { color: #1a7f37; }
        .diff-stat-removed { color: #cf222e; }
        .diff-container {
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
        }
        .diff-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.82rem;
            line-height: 1.5;
        }
        .diff-table tr {
            border-bottom: 1px solid #eaeaea;
        }
        .diff-table tr:last-child {
            border-bottom: none;
        }
        .diff-line-num {
            width: 1%;
            min-width: 50px;
            padding: 0 8px;
            text-align: right;
            vertical-align: top;
            color: #6e7781;
            background: #f6f8fa;
            user-select: none;
            border-right: 1px solid #eaeaea;
            font-size: 0.75rem;
            line-height: 20px;
        }
        .diff-line-num-removed {
            background: #ffeef0;
            color: #cf222e;
        }
        .diff-line-num-added {
            background: #e6ffec;
            color: #1a7f37;
        }
        .diff-line-content {
            padding: 0 12px;
            vertical-align: top;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .diff-line-content-removed {
            background: #ffeef0;
        }
        .diff-line-content-added {
            background: #e6ffec;
        }
        .diff-line-prefix {
            color: #6e7781;
        }
        .diff-row-remove .diff-line-prefix {
            color: #cf222e;
        }
        .diff-row-add .diff-line-prefix {
            color: #1a7f37;
        }
        .diff-chars-added {
            background: #acff9b;
            border-radius: 3px;
            padding: 1px 0;
        }
        .diff-chars-removed {
            background: #ff9494;
            border-radius: 3px;
            padding: 1px 0;
            text-decoration: none;
        }

        /* Version accordion */
        .version-accordion-btn:not(.collapsed)::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%231a1a1a'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }
        .version-accordion-btn {
            font-size: 0.9rem;
            padding: 0.85rem 1rem;
        }
        .version-accordion-btn .row {
            font-size: 0.88rem;
        }
        .accordion-item .accordion-body {
            background: #fafaf7;
        }
        .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #fff; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; }
        .login-card h1 { font-size: 1.5rem; font-weight: 600; }
        .turnstile-widget { display: flex; justify-content: center; padding: 0.5rem 0; }

        /* Top bar (mobile-style, used on all devices) */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3.25rem;
            z-index: 1050;
            background: #fff;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .topbar .topbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 0.75rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }
        .topbar .topbar-left {
            display: flex;
            align-items: center;
        }
        .topbar .topbar-brand {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--off-black);
            text-decoration: none;
            margin-left: 0.75rem;
        }
        .topbar .topbar-right .nav-link {
            color: var(--off-black);
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .topbar .topbar-right .nav-link:hover {
            background: var(--off-white);
        }
        .mobile-menu-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            padding: 0.25rem;
            color: var(--off-black);
            cursor: pointer;
        }
        .mobile-menu-btn:hover { background: var(--off-white); border-radius: 4px; }

        /* Sidebar (slide-out, used on all devices) */
        .sidebar {
            display: none;
            position: fixed;
            top: 3.25rem;
            left: 0;
            width: 280px;
            height: calc(100vh - 3.25rem);
            z-index: 1040;
            overflow-y: auto;
            background: #fff;
            border-right: 1px solid var(--border);
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }
        .sidebar.show { display: block; }
        .sidebar .nav-link {
            color: var(--off-black);
            padding: 0.6rem 1rem;
            border-radius: 4px;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--off-white);
            color: var(--off-black);
        }
        .sidebar .nav-link.active { font-weight: 600; }

        .main-content {
            padding: 4.25rem 1.5rem 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 3.25rem;
            left: 0;
            width: 100%;
            height: calc(100vh - 3.25rem);
            background: rgba(0,0,0,0.3);
            z-index: 1035;
        }
        .sidebar-overlay.show { display: block; }

        /* Dashboard-style cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            max-width: 640px;
            margin: 2rem auto 0;
        }
        .dashboard-card {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }
        .dashboard-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.25rem 1.25rem 0.75rem;
            color: var(--off-black);
            background: var(--off-white);
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            transition: background 0.15s ease;
        }
        .dashboard-card-header:hover {
            background: #ebebe6;
        }
        .dashboard-card-header .card-icon {
            font-size: 1.5rem;
            color: #666;
        }
        .dashboard-card-header .card-title {
            font-size: 1.05rem;
            font-weight: 600;
            flex: 1;
        }
        .dashboard-card-header .card-count {
            font-size: 0.8rem;
            color: #999;
            font-weight: 400;
        }
        .dashboard-card-header .header-action {
            font-size: 0.8rem;
            color: var(--bs-danger);
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            white-space: nowrap;
        }
        .dashboard-card-header .header-action:hover {
            text-decoration: underline;
        }
        .dashboard-card-body {
            padding: 0;
        }
        .dashboard-card-body .recent-item {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.65rem 1.25rem;
            font-size: 0.88rem;
            color: #555;
            text-decoration: none;
            border-bottom: 1px solid #f0f0ea;
            background: transparent;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .dashboard-card-body .recent-item:last-child {
            border-bottom: none;
        }
        .dashboard-card-body .recent-item:hover {
            background: var(--off-white);
            color: var(--off-black);
        }
        .dashboard-card-body .recent-item .item-status {
            font-size: 0.75rem;
            color: #999;
            margin-left: 0.35rem;
        }
        .dashboard-card-body .info-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.65rem 1.25rem;
            font-size: 0.88rem;
            color: #555;
            border-bottom: 1px solid #f0f0ea;
            background: transparent;
            cursor: default;
        }
        .dashboard-card-body .info-item:last-child {
            border-bottom: none;
        }
        .dashboard-card-body .info-item .item-status {
            font-size: 0.75rem;
            color: #999;
            margin-left: 0.35rem;
        }
        .dashboard-card-body .card-actions a {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.65rem 1.25rem;
            font-size: 0.88rem;
            color: #555;
            text-decoration: none;
            background: transparent;
            border-bottom: 1px solid #f0f0ea;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .dashboard-card-body .card-actions a:last-child {
            border-bottom: none;
        }
        .dashboard-card-body .card-actions a:hover {
            background: var(--off-white);
            color: var(--off-black);
        }
        .dashboard-welcome {
            text-align: center;
            margin-top: 2rem;
            margin-bottom: 0.5rem;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        .profile-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        .dashboard-card-body.profile-card-body {
            padding: 1rem 1.25rem 1.25rem;
        }
        .dashboard-card-body .session-item {
            justify-content: space-between;
            gap: 1rem;
            cursor: pointer;
        }
        .session-item-details {
            min-width: 0;
        }
        .session-item-details .session-browser {
            font-weight: 600;
            color: var(--off-black);
        }
        .session-item-details .session-current {
            font-size: 0.7rem;
            font-weight: 500;
            vertical-align: middle;
        }
        .session-item-details .session-meta {
            display: block;
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.15rem;
        }
        .session-logout-label {
            font-size: 0.8rem;
            color: var(--bs-danger);
            padding: 0;
            flex-shrink: 0;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .session-item:hover .session-logout-label {
            opacity: 1;
        }
        .profile-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 991.98px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 576px) {
            .dashboard-grid,
            .profile-settings-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
    <?= $headExtra ?>
</head>
<body>
<?php if ($user): ?>
<!-- Top bar (mobile-style, all devices) -->
<div class="topbar">
    <div class="topbar-container">
        <div class="topbar-left">
            <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation">
                <i class="bi bi-list fs-4"></i>
            </button>
            <a class="topbar-brand" href="<?= admin_url() ?>"><?= esc($appName) ?></a>
        </div>
        <div class="topbar-right">
            <a class="nav-link d-none d-md-inline" href="<?= url() ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i><?= __t('view_site') ?></a>
        </div>
    </div>
</div>
<div class="sidebar-overlay"></div>
<nav class="sidebar py-3" aria-label="Admin navigation">
    <ul class="nav flex-column px-2">
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= admin_url() ?>"><i class="bi bi-speedometer2 me-2"></i><?= __t('dashboard') ?></a></li>
        <?php if (\Elementary\Auth::canManagePages()): ?>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'pages' ? 'active' : '' ?>" href="<?= admin_url('pages') ?>"><i class="bi bi-file-earmark me-2"></i><?= __t('pages') ?></a></li>
        <?php endif; ?>
        <?php if (\Elementary\Auth::canManagePosts()): ?>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'posts' ? 'active' : '' ?>" href="<?= admin_url('posts') ?>"><i class="bi bi-newspaper me-2"></i><?= __t('posts') ?></a></li>
        <?php endif; ?>
        <?php if (\Elementary\Auth::canManageFiles()): ?>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'files' ? 'active' : '' ?>" href="<?= admin_url('files') ?>"><i class="bi bi-folder me-2"></i><?= __t('files') ?></a></li>
        <?php endif; ?>
        <?php if (\Elementary\Auth::canAccessSettings()): ?>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= admin_url('settings') ?>"><i class="bi bi-gear me-2"></i><?= __t('settings') ?></a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>" href="<?= admin_url('profile') ?>"><i class="bi bi-person me-2"></i><?= __t('profile') ?></a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url() ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i><?= __t('view_site') ?></a></li>
    </ul>
</nav>
<main class="main-content">
    <?= $content ?>
</main>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const closeBtn = document.querySelector('.mobile-close-btn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    function setMenuIcon(open) {
        const icon = menuBtn.querySelector('i');
        if (!icon) return;
        icon.classList.toggle('bi-list', !open);
        icon.classList.toggle('bi-x-lg', open);
    }
    function openMenu() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        setMenuIcon(true);
    }
    function closeMenu() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        setMenuIcon(false);
    }

    if (menuBtn) menuBtn.addEventListener('click', function() { sidebar.classList.contains('show') ? closeMenu() : openMenu(); });
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMenu();
    });
});
</script>
<script src="<?= asset('bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
    <?php
}

function admin_flash(): string
{
    $html = '';
    if ($error = flash('error')) {
        $html .= '<div class="alert alert-danger">' . esc($error) . '</div>';
    }
    if ($success = flash('success')) {
        $html .= '<div class="alert alert-success">' . esc($success) . '</div>';
    }
    return $html;
}

function admin_breadcrumb(array $crumbs): string
{
    if (empty($crumbs)) {
        return '';
    }
    $html = '<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb">';
    $appName = \Elementary\Config::get('app_name', 'Elementary');
    $html .= '<li class="breadcrumb-item"><a href="' . admin_url() . '">' . esc($appName) . '</a></li>';
    foreach ($crumbs as $i => $crumb) {
        $isLast = $i === array_key_last($crumbs);
        if ($isLast) {
            $html .= '<li class="breadcrumb-item active">' . esc($crumb['label']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . esc($crumb['url']) . '">' . esc($crumb['label']) . '</a></li>';
        }
    }
    $html .= '</ol></nav>';
    return $html;
}
