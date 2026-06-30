<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($errorName ?? 'Not Found') ?> - <?= esc($site_name) ?></title>
    <meta name="description" content="<?= esc($friendlyMessage ?? 'The page you are looking for could not be found.') ?>">
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
        <div class="error-page">
            <div class="error-code"><?= (int) $code ?></div>
            <h1 class="error-title"><?= esc($errorName ?? 'Not Found') ?></h1>
            <p class="error-message"><?= esc($friendlyMessage ?? 'The page you are looking for could not be found.') ?></p>
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
            <a href="<?= esc($site_url) ?>" class="back-link">Return to homepage</a>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= esc($site_name) ?></p>
        </div>
    </footer>
</body>
</html>
