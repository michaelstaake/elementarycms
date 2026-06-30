<?php

declare(strict_types=1);

use Elementary\Auth;
use Elementary\Config;
use Elementary\ContentManager;
use Elementary\Database;
use Elementary\FileManager;
use Elementary\Hooks;
use Elementary\Logger;
use Elementary\PluginLoader;
use Elementary\Template;
use Elementary\Upgrader;
use Elementary\Validator;
use Elementary\VideoEmbed;

foreach ([
    Auth::class,
    Config::class,
    ContentManager::class,
    Database::class,
    FileManager::class,
    Hooks::class,
    Logger::class,
    PluginLoader::class,
    Template::class,
    Validator::class,
    VideoEmbed::class,
    Upgrader::class,
] as $class) {
    $alias = substr($class, strrpos($class, '\\') + 1);
    if (!class_exists($alias, false)) {
        class_alias($class, $alias);
    }
}

$segments = array_slice((new Elementary\Router())->getSegments(), 1);
$page = $segments[0] ?? 'dashboard';
$action = $segments[1] ?? 'index';
$identifier = isset($segments[2]) ? rawurldecode($segments[2]) : null;

$publicPages = ['login', 'forgot-password', 'reset-password'];

// AJAX upload endpoint for media (images and videos)
$isMediaUpload = $page === 'files'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_FILES['upload'])
    && (($action === 'upload-ajax') || (isset($_GET['ajax']) && $_GET['ajax'] === 'upload'));
if ($isMediaUpload) {
    Auth::requireLogin();
    if (!Auth::canManageFiles()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json');
    if (!verify_csrf()) {
        echo json_encode(['success' => false, 'error' => __t('csrf_error')]);
        exit;
    }
    $user = Auth::user();
    $mediaType = $_POST['media_type'] ?? 'image';
    $result = FileManager::upload($_FILES['upload'], (int) $user['id']);
    if ($result['success']) {
        $file = FileManager::getFile($result['id']);
        $mimeType = $file['mime_type'] ?? '';
        $isValid = $mediaType === 'video'
            ? VideoEmbed::isVideoMimeType($mimeType)
            : str_starts_with($mimeType, 'image/');
        if (!$file || !$isValid) {
            if ($file) {
                FileManager::deleteFile((int) $file['id']);
            }
            $error = $mediaType === 'video'
                ? __t('featured_video_must_be_video')
                : __t('featured_image_must_be_image');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        echo json_encode([
            'success'  => true,
            'message'  => __t('file_uploaded'),
            'filename' => $file['filename'],
            'url'      => file_url($file['filename']),
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    exit;
}

// Session keepalive for long editing sessions (no CSRF required for GET)
if ($page === 'session' && $action === 'keepalive' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => Auth::check()]);
    exit;
}

if (!in_array($page, $publicPages, true)) {
    Auth::requireLogin();
}

if (
    $page !== 'update'
    && !in_array($page, $publicPages, true)
    && Upgrader::needsUpgrade()
) {
    redirect(admin_url('update'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    flash('error', __t('csrf_error'));
    redirect(admin_url($page));
}

switch ($page) {
    case 'login':
        require ELEMENTARY_ROOT . '/admin/pages/login.php';
        break;
    case 'forgot-password':
        require ELEMENTARY_ROOT . '/admin/pages/forgot-password.php';
        break;
    case 'reset-password':
        require ELEMENTARY_ROOT . '/admin/pages/reset-password.php';
        break;
    case 'logout':
        Auth::logout();
        redirect(admin_url('login'));
        break;
    case 'dashboard':
        require ELEMENTARY_ROOT . '/admin/pages/dashboard.php';
        break;
    case 'users':
        if (!Auth::canManageUsers()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/users.php';
        break;
    case 'pages':
        if (!Auth::canManagePages()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/pages.php';
        break;
    case 'posts':
        if (!Auth::canManagePosts()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/posts.php';
        break;
    case 'categories':
        if (!Auth::canManageCategories()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/categories.php';
        break;
    case 'tags':
        if (!Auth::canManageCategories()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/tags.php';
        break;
    case 'files':
        if (!Auth::canManageFiles()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/files.php';
        break;
    case 'settings':
        if (!Auth::canAccessSettings()) {
            throw new RuntimeException('Forbidden', 403);
        }
        if ($action !== 'index' && $action !== '') {
            $settingsPage = $action;
            $settingsFile = ELEMENTARY_ROOT . '/admin/pages/settings/' . $settingsPage . '.php';
            $pluginSettingsFile = null;
            foreach (PluginLoader::getSettingsPages() as $slug => $info) {
                if ($slug === $settingsPage) {
                    $pluginSettingsFile = $info['file'] ?? null;
                    break;
                }
            }
            if ($pluginSettingsFile && file_exists($pluginSettingsFile)) {
                require $pluginSettingsFile;
            } elseif (file_exists($settingsFile)) {
                require $settingsFile;
            } else {
                throw new RuntimeException('Settings page not found', 404);
            }
        } else {
            require ELEMENTARY_ROOT . '/admin/pages/settings/index.php';
        }
        break;
    case 'logs':
        if (!Auth::canAccessSettings()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/logs.php';
        break;
    case 'profile':
        require ELEMENTARY_ROOT . '/admin/pages/profile.php';
        break;
    case 'update':
        if (!Auth::canAccessSettings()) {
            throw new RuntimeException('Forbidden', 403);
        }
        require ELEMENTARY_ROOT . '/admin/pages/update.php';
        break;
    default:
        throw new RuntimeException('Page not found', 404);
}
