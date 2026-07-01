<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(__t('search')) ?> - <?= esc($site_name) ?></title>
    <meta name="robots" content="noindex, nofollow">
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
        <h1 class="page-title"><?= esc(__t('search')) ?></h1>

        <form class="search-form" action="<?= esc($site_url . '/search') ?>" method="get" role="search">
            <div class="search-form-group">
                <label for="search-query" class="sr-only"><?= esc(__t('search_placeholder')) ?></label>
                <input
                    type="search"
                    id="search-query"
                    name="q"
                    value=""
                    placeholder="<?= esc(__t('search_placeholder')) ?>"
                    autocomplete="off"
                    required
                >
                <button type="submit" class="search-submit">
                    <?= esc(__t('search_button')) ?>
                </button>
            </div>
        </form>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= esc($site_name) ?></p>
        </div>
    </footer>
    <script src="<?= url('theme/2026/assets/js/main.js') ?>"></script>
</body>
</html>
