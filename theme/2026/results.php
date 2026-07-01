<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? $search_query) ?> - <?= esc($site_name) ?></title>
    <?php if (!empty($meta_keywords)): ?>
    <meta name="keywords" content="<?= esc($meta_keywords) ?>">
    <?php endif; ?>
    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?= esc($meta_description) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        <form class="search-form" action="<?= esc($site_url . '/search') ?>" method="get" role="search">
            <div class="search-form-group">
                <label for="search-query" class="sr-only"><?= esc(__t('search_placeholder')) ?></label>
                <input
                    type="search"
                    id="search-query"
                    name="q"
                    value="<?= esc($search_query) ?>"
                    placeholder="<?= esc(__t('search_placeholder')) ?>"
                    autocomplete="off"
                >
                <button type="submit" class="search-submit">
                    <?= esc(__t('search_button')) ?>
                </button>
            </div>
        </form>

        <h1 class="page-title">
            <?= esc(__t('search_results')) ?>
            <?= esc(__t('search_results_for')) ?>
            <span>"<?= esc($search_query) ?>"</span>
        </h1>

        <p class="search-results-summary">
            <?= (int) $result_count ?> <?= esc(__t('search_results_count')) ?>
        </p>

        <div class="post-list">
            <?php if (empty($posts)): ?>
                <p><?= esc(__t('search_no_results')) ?></p>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= esc($site_name) ?></p>
        </div>
    </footer>
    <script src="<?= url('theme/2026/assets/js/main.js') ?>"></script>
</body>
</html>
