<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? $category['name']) ?> - <?= esc($site_name) ?></title>
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
            <nav aria-label="Main navigation">
                <?php menu('primary'); ?>
            </nav>
        </div>
    </header>

    <main class="site-main container">
        <?php if (!empty($category['featured_image_url'])): ?>
        <div class="featured-image">
            <img src="<?= esc($category['featured_image_url']) ?>" alt="<?= esc($category['name']) ?>" class="featured-image-img">
        </div>
        <?php endif; ?>
        <h1 class="page-title"><?= esc($category['name']) ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="category-description"><?= esc($category['description']) ?></p>
        <?php endif; ?>

        <div class="post-list">
            <?php if (empty($posts)): ?>
                <p>No posts in this category.</p>
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
                    <?php post_meta($post, [$category]); ?>
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
</body>
</html>
