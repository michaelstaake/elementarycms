<?php

declare(strict_types=1);

function url(string $path = ''): string
{
    $base = rtrim(\Elementary\Config::get('site_url', ''), '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function asset(string $path): string
{
    return url('vendor/' . ltrim($path, '/'));
}

function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

function admin_page_slug(array $page): string
{
    if (\Elementary\ContentManager::isPostsPage($page) && ($page['slug'] ?? '') === '') {
        return \Elementary\ContentManager::POSTS_PAGE_ADMIN_SLUG;
    }

    return $page['slug'];
}

function admin_file_slug(string $filename): string
{
    return str_replace('.', '-', $filename);
}

function admin_entity_url(string $resource, string $action, string $slug): string
{
    return admin_url($resource . '/' . $action . '/' . rawurlencode($slug));
}

function file_url(string $filename): string
{
    return url('file/' . ltrim($filename, '/'));
}

function esc(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Build HTML attributes (class, id, style) for page builder elements.
 */
function element_attrs(array $el, string $baseClass = '', string $baseStyle = ''): string
{
    $class = trim($baseClass . ' ' . ($el['css_class'] ?? ''));
    $style = trim($baseStyle);
    $inlineCss = trim($el['inline_css'] ?? '');
    if ($inlineCss !== '') {
        $style = $style !== '' ? rtrim($style, ';') . '; ' . $inlineCss : $inlineCss;
    }

    $attrs = '';
    if ($class !== '') {
        $attrs .= ' class="' . esc($class) . '"';
    }
    if (!empty($el['css_id'])) {
        $attrs .= ' id="' . esc($el['css_id']) . '"';
    }
    if ($style !== '') {
        $attrs .= ' style="' . esc($style) . '"';
    }
    return $attrs;
}

function __t(string $key, array $replacements = []): string
{
    static $lang = null;
    if ($lang === null) {
        $langFile = ELEMENTARY_ROOT . '/lang/' . \Elementary\Config::get('lang', 'en_US') . '.php';
        $lang = file_exists($langFile) ? require $langFile : [];
    }
    $text = $lang[$key] ?? $key;
    foreach ($replacements as $search => $replace) {
        $text = str_replace(':' . $search, (string) $replace, $text);
    }
    return $text;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Render a POST form with CSRF for destructive or state-changing actions.
 */
function post_action_form(
    string $actionUrl,
    string $buttonText,
    string $confirmMessage = '',
    string $buttonClass = 'btn btn-outline-danger btn-sm',
    array $hiddenFields = [],
    ?string $iconClass = null
): string {
    $onclick = $confirmMessage !== ''
        ? ' onsubmit="return confirm(' . json_encode($confirmMessage) . ')"'
        : '';
    $buttonContent = $iconClass !== null
        ? '<i class="' . esc($iconClass) . '"></i> ' . esc($buttonText)
        : esc($buttonText);
    $html = '<form method="post" action="' . esc($actionUrl) . '" class="d-inline"' . $onclick . '>';
    $html .= csrf_field();
    foreach ($hiddenFields as $name => $value) {
        $html .= '<input type="hidden" name="' . esc((string) $name) . '" value="' . esc((string) $value) . '">';
    }
    $html .= '<button type="submit" class="' . esc($buttonClass) . '">' . $buttonContent . '</button>';
    $html .= '</form>';
    return $html;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function format_date(string $datetime, ?string $timezone = null): string
{
    $tz = $timezone ?: \Elementary\Config::get('time_zone', 'UTC');
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone($tz));
    return $dt->format('M j, Y g:i A');
}

/**
 * Get the post excerpt for listing pages.
 * Returns the manual excerpt if available, otherwise generates one from the content
 * by stripping HTML tags and truncating to a reasonable length.
 *
 * @param array $post Post row with excerpt and content keys.
 * @param int $length Maximum character length for auto-generated excerpts.
 */
function post_excerpt(array $post, int $length = 150): string
{
    $excerpt = trim($post['excerpt'] ?? '');
    if ($excerpt !== '') {
        return $excerpt;
    }

    $content = trim($post['content'] ?? '');
    if ($content === '') {
        return '';
    }

    // Strip HTML tags and decode entities
    $text = strip_tags($content);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // Trim whitespace
    $text = trim($text);

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . '…';
}

/**
 * Render post meta line: "Timestamp by author in category".
 *
 * @param array $post Post row with published_at/created_at and optional author_name.
 * @param array $categories Category rows with name and slug keys.
 */
function post_meta(array $post, array $categories = []): void
{
    $timestamp = $post['published_at'] ?? $post['created_at'] ?? '';
    $siteUrl = rtrim(\Elementary\Config::get('site_url', ''), '/');

    echo '<div class="meta">';
    if ($timestamp !== '') {
        echo '<time datetime="' . esc($timestamp) . '">' . esc(format_date($timestamp)) . '</time>';
    }
    if (!empty($post['author_name'])) {
        $authorUrl = '';
        if (!empty($post['author_username'])) {
            $authorUrl = esc($siteUrl . '/author/' . rawurlencode($post['author_username']));
        }
        echo '<span class="author"> by ';
        if ($authorUrl) {
            echo '<a href="' . $authorUrl . '">' . esc($post['author_name']) . '</a>';
        } else {
            echo esc($post['author_name']);
        }
        echo '</span>';
    }
    if (!empty($categories)) {
        echo '<span class="categories"> in ';
        foreach ($categories as $i => $cat) {
            if ($i > 0) {
                echo ', ';
            }
            echo '<a href="' . esc($siteUrl . '/' . $cat['slug']) . '">' . esc($cat['name']) . '</a>';
        }
        echo '</span>';
    }
    echo '</div>';
}

/**
 * Compute the Longest Common Subsequence (LCS) of two arrays.
 */
function lcs_table(array $a, array $b): array
{
    $m = count($a);
    $n = count($b);
    $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            if ($a[$i - 1] === $b[$j - 1]) {
                $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }
    }
    return $dp;
}

/**
 * Generate a unified diff between two strings (line-level with character highlighting).
 * Returns an array of diff line arrays: ['type' => 'context'|'add'|'remove', 'line' => '...'].
 */
function diff_lines(string $old, string $new): array
{
    $oldLines = explode("\n", $old);
    $newLines = explode("\n", $new);

    // Remove trailing empty line from split artifact
    if (count($oldLines) > 0 && $oldLines[count($oldLines) - 1] === '') array_pop($oldLines);
    if (count($newLines) > 0 && $newLines[count($newLines) - 1] === '') array_pop($newLines);

    $dp = lcs_table($oldLines, $newLines);

    // Back-track to build the diff
    $result = [];
    $i = count($oldLines);
    $j = count($newLines);
    $stack = [];
    while ($i > 0 || $j > 0) {
        if ($i > 0 && $j > 0 && $oldLines[$i - 1] === $newLines[$j - 1]) {
            $stack[] = ['type' => 'context', 'line' => $oldLines[$i - 1]];
            $i--;
            $j--;
        } elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
            $stack[] = ['type' => 'add', 'line' => $newLines[$j - 1]];
            $j--;
        } elseif ($i > 0 && ($j === 0 || $dp[$i][$j - 1] < $dp[$i - 1][$j])) {
            $stack[] = ['type' => 'remove', 'line' => $oldLines[$i - 1]];
            $i--;
        } else {
            break;
        }
    }
    return array_reverse($stack);
}

/**
 * Render a GitHub-style HTML diff between two content strings.
 * Supports character-level highlighting within changed lines.
 */
function diff_html(string $old, string $new, bool $showStats = true): string
{
    $diff = diff_lines($old, $new);

    // Count stats
    $added = 0;
    $removed = 0;
    foreach ($diff as $d) {
        if ($d['type'] === 'add') $added++;
        if ($d['type'] === 'remove') $removed++;
    }

    $html = '';

    // Stats bar
    if ($showStats) {
        $labelInsertions = __t('diff_insertions');
        if ($labelInsertions === 'diff_insertions') $labelInsertions = 'insertions';
        $labelDeletions = __t('diff_deletions');
        if ($labelDeletions === 'diff_deletions') $labelDeletions = 'deletions';
        $labelNoChanges = __t('diff_no_changes');
        if ($labelNoChanges === 'diff_no_changes') $labelNoChanges = 'No changes';

        $html .= '<div class="diff-stats-bar mb-2">';
        if ($added > 0) {
            $html .= '<span class="diff-stat diff-stat-added"><i class="bi bi-plus-square-fill"></i> +' . $added . ' ' . esc($labelInsertions) . '</span>';
        }
        if ($removed > 0) {
            $html .= '<span class="diff-stat diff-stat-removed"><i class="bi bi-dash-square-fill"></i> -' . $removed . ' ' . esc($labelDeletions) . '</span>';
        }
        if ($added === 0 && $removed === 0) {
            $html .= '<span class="text-muted">' . esc($labelNoChanges) . '</span>';
        }
        $html .= '</div>';
    }

    // Diff table
    $labelDiff = __t('diff');
    if ($labelDiff === 'diff') $labelDiff = 'Diff';
    $html .= '<div class="diff-container" role="region" aria-label="' . esc($labelDiff) . '">';
    $html .= '<table class="diff-table"><tbody>';

    $oldLineNum = 1;
    $newLineNum = 1;

    // Render each diff line with proper line numbers
    foreach ($diff as $d) {
        if ($d['type'] === 'context') {
            $html .= diff_render_row($d, $oldLineNum++, $newLineNum++);
        } elseif ($d['type'] === 'remove') {
            $html .= diff_render_row($d, $oldLineNum++, null);
        } elseif ($d['type'] === 'add') {
            $html .= diff_render_row($d, null, $newLineNum++);
        }
    }

    $html .= '</tbody></table></div>';
    return $html;
}

/**
 * Render a single diff row as an HTML table row.
 */
function diff_render_row(array $d, ?int $oldLineNum, ?int $newLineNum): string
{
    $line = esc($d['line']);
    $html = '<tr class="diff-row diff-row-' . $d['type'] . '">';

    if ($d['type'] === 'context') {
        $html .= '<td class="diff-line-num">' . $oldLineNum . '</td>';
        $html .= '<td class="diff-line-num">' . $newLineNum . '</td>';
        $html .= '<td class="diff-line-content"><span class="diff-line-prefix"> </span>' . $line . '</td>';
    } elseif ($d['type'] === 'remove') {
        $html .= '<td class="diff-line-num diff-line-num-removed">' . $oldLineNum . '</td>';
        $html .= '<td class="diff-line-num"></td>';
        // Try to find a matching added line to do character-level diff
        $html .= '<td class="diff-line-content diff-line-content-removed"><span class="diff-line-prefix">-</span>' . $line . '</td>';
    } elseif ($d['type'] === 'add') {
        $html .= '<td class="diff-line-num"></td>';
        $html .= '<td class="diff-line-num diff-line-num-added">' . $newLineNum . '</td>';
        $html .= '<td class="diff-line-content diff-line-content-added"><span class="diff-line-prefix">+</span>' . $line . '</td>';
    }

    $html .= '</tr>';
    return $html;
}

function setting(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        try {
            $rows = \Elementary\Database::fetchAll('SELECT setting_key, setting_value FROM settings');
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = json_decode($row['setting_value'], true) ?? $row['setting_value'];
            }
        } catch (\Throwable) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

function set_setting(string $key, mixed $value): void
{
    $encoded = is_string($value) ? $value : json_encode($value);
    $existing = \Elementary\Database::fetch('SELECT id FROM settings WHERE setting_key = ?', [$key]);
    if ($existing) {
        \Elementary\Database::update('settings', ['setting_value' => $encoded], 'setting_key = ?', [$key]);
    } else {
        \Elementary\Database::insert('settings', [
            'setting_key'   => $key,
            'setting_value' => $encoded,
        ]);
    }

    if (str_starts_with($key, 'menu_location_') || str_starts_with($key, 'home_page') || str_starts_with($key, 'analytics_')) {
        \Elementary\HtmlCache::clearAll();
    }
}

function autoload_core(string $class): void
{
    $prefix = 'Elementary\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = ELEMENTARY_ROOT . '/core/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * Output custom analytics/head code if enabled.
 * Safe to call from theme templates inside <head>.
 */
function head_content(): void
{
    if (!setting('analytics_enabled', false)) {
        return;
    }
    $code = (string) setting('analytics_code', '');
    if ($code !== '') {
        echo $code;
    }
}

/**
 * Render a navigation menu for a given theme location.
 *
 * @param string $location The menu location name as defined by the theme.
 * @param string $class CSS class for the top-level <ul> element.
 */
function menu(string $location, string $class = 'menu'): void
{
    $menuData = \Elementary\MenuManager::getMenuTreeForLocation($location);
    if (!$menuData || empty($menuData['items'])) {
        return;
    }

    echo '<ul class="' . esc($class) . '">';
    foreach ($menuData['items'] as $item) {
        menu_item($item);
    }
    echo '</ul>';
}

/**
 * Recursively render a menu item and its children.
 */
function menu_item(array $item): void
{
    $type = $item['type'] ?? 'url';
    $typeId = isset($item['type_id']) && $item['type_id'] !== null && $item['type_id'] !== ''
        ? (int) $item['type_id']
        : null;
    $url = \Elementary\MenuManager::resolveMenuItemUrl($type, $typeId, $item['url'] ?? '');
    $hasChildren = !empty($item['children']);

    echo '<li class="menu-item';
    if ($hasChildren) {
        echo ' menu-item-has-children';
    }
    echo '">';
    echo '<a href="' . esc($url) . '">' . esc($item['title']) . '</a>';

    if ($hasChildren) {
        echo '<ul class="sub-menu">';
        foreach ($item['children'] as $child) {
            menu_item($child);
        }
        echo '</ul>';
    }

    echo '</li>';
}

/**
 * Output favicon <link> tags in the <head> section.
 * Uses a custom favicon file if set, otherwise falls back to the first letter
 * of the site name as an inline SVG data URI.
 *
 * Safe to call from theme templates inside <head>.
 */
function favicon(): void
{
    $filename = (string) setting('favicon_filename', '');
    if ($filename !== '') {
        echo '<link rel="icon" type="image/svg+xml" href="' . esc(file_url($filename)) . '">' . "\n";
        echo '<link rel="shortcut icon" href="' . esc(file_url($filename)) . '">' . "\n";
        return;
    }

    $siteName = \Elementary\Config::get('site_name', 'E');
    $letter = strtoupper(mb_substr($siteName, 0, 1));
    if (!preg_match('/^[A-Z0-9]$/', $letter)) {
        $letter = 'E';
    }
    $svg = '%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22%3E%3Crect width=%2264%22 height=%2264%22 rx=%2212%22 fill=%22%231a1a1a%22/%3E%3Ctext x=%2232%22 y=%2246%22 font-family=%22system-ui,-apple-system,sans-serif%22 font-size=%2240%22 font-weight=%22700%22 fill=%22%23f5f5f0%22 text-anchor=%22middle%22%3E' . $letter . '%3C/text%3E%3C/svg%3E';
    $dataUri = 'data:image/svg+xml,' . $svg;
    echo '<link rel="icon" type="image/svg+xml" href="' . $dataUri . '">' . "\n";
    echo '<link rel="shortcut icon" href="' . $dataUri . '">' . "\n";
}
