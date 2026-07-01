<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($document_title ?? ($title ?? $site_name)) ?></title>
    <?php if (!empty($meta_keywords)): ?>
    <meta name="keywords" content="<?= esc($meta_keywords) ?>">
    <?php endif; ?>
    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?= esc($meta_description) ?>">
    <?php endif; ?>
    <link href="<?= url('theme/2026/assets/css/style.css') ?>" rel="stylesheet">
    <?php favicon(); ?>
    <?php head_content(); ?>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="<?= esc($site_url) ?>" class="site-logo"><?= esc($site_name) ?></a>
            <?php if (has_menu('primary')): ?>
            <button type="button" class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <span class="nav-toggle-icon"></span>
            </button>
            <nav class="site-nav" aria-label="Main navigation">
                <?php menu('primary'); ?>
            </nav>
            <?php endif; ?>
        </div>
    </header>

    <main class="site-main container">
        <?php if (empty($is_home) || empty($hide_home_heading)): ?>
        <h1 class="page-title"><?= esc($page['title'] ?? 'Posts') ?></h1>
        <?php endif; ?>
        <?php if (!empty($posts)): ?>
            <div class="post-list">
                <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <?php if (!empty($post['featured_image_url'])): ?>
                    <div class="post-card-image">
                        <a href="<?= esc($site_url . '/' . $post['slug']) ?>">
                            <img src="<?= esc($post['featured_image_url']) ?>" alt="<?= esc($post['title']) ?>" class="post-card-img">
                        </a>
                    </div>
                    <?php endif; ?>
                    <h2><a href="<?= esc($site_url . '/' . $post['slug']) ?>"><?= esc($post['title']) ?></a></h2>
                    <?php $excerpt = post_excerpt($post); ?>
                    <?php if ($excerpt !== ''): ?>
                        <p class="excerpt"><?= esc($excerpt) ?></p>
                    <?php endif; ?>
                    <?php post_meta($post, $post['categories'] ?? []); ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No posts yet.</p>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= esc($site_name) ?></p>
        </div>
    </footer>
    <script src="<?= url('theme/2026/assets/js/main.js') ?>"></script>
</body>
</html>
