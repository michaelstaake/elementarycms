<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($document_title ?? (($title ?? $page['title']) . ' - ' . $site_name)) ?></title>
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
        <article class="single-page">
            <?php if (!empty($page['featured_image_url'])): ?>
            <div class="featured-image">
                <img src="<?= esc($page['featured_image_url']) ?>" alt="<?= esc($page['title']) ?>" class="featured-image-img">
            </div>
            <?php endif; ?>
            <?php if (empty($is_home) || empty($hide_home_heading)): ?>
            <h1 class="page-title"><?= esc($page['title']) ?></h1>
            <?php endif; ?>
            <?php if (!empty($page_structure)): ?>
                <?php foreach ($page_structure as $section): ?>
                    <section<?= element_attrs($section, 'page-section') ?>>
                        <?php foreach ($section['rows'] ?? [] as $row): ?>
                            <div<?= element_attrs($row, 'page-row') ?>>
                                <?php foreach ($row['columns'] ?? [] as $column): ?>
                                    <div<?= element_attrs($column, 'page-column', 'flex: ' . ($column['width'] ?? 100) . ';') ?>>
                                        <?php foreach ($column['blocks'] ?? [] as $block): ?>
                                            <?php if ($block['block_type'] === 'text'): ?>
                                                <div<?= element_attrs($block, 'page-block page-block-text') ?>>
                                                    <?= nl2br(esc($block['block_data']['text'] ?? '')) ?>
                                                </div>
                                            <?php elseif ($block['block_type'] === 'image'): ?>
                                                <?php
                                                $imageSource = $block['block_data']['source'] ?? 'file';
                                                if ($imageSource !== 'url' && empty($block['block_data']['filename']) && !empty($block['block_data']['url'])) {
                                                    $imageSource = 'url';
                                                }
                                                $imageSrc = '';
                                                if ($imageSource === 'url' && !empty($block['block_data']['url'])) {
                                                    $imageSrc = $block['block_data']['url'];
                                                } elseif (!empty($block['block_data']['filename'])) {
                                                    $imageSrc = file_url($block['block_data']['filename']);
                                                }
                                                ?>
                                                <?php if ($imageSrc !== ''): ?>
                                                <div<?= element_attrs($block, 'page-block page-block-image') ?>>
                                                    <img src="<?= esc($imageSrc) ?>" alt="" class="page-block-image-img">
                                                </div>
                                                <?php endif; ?>
                                            <?php elseif ($block['block_type'] === 'video'): ?>
                                                <?php
                                                $videoSource = $block['block_data']['source'] ?? 'file';
                                                if ($videoSource !== 'embed' && empty($block['block_data']['filename']) && !empty($block['block_data']['url'])) {
                                                    $videoSource = 'embed';
                                                }
                                                ?>
                                                <?php if ($videoSource === 'embed' && !empty($block['block_data']['url'])): ?>
                                                    <?php $videoEmbed = \Elementary\VideoEmbed::embedHtml($block['block_data']['url']); ?>
                                                    <?php if ($videoEmbed !== ''): ?>
                                                    <div<?= element_attrs($block, 'page-block page-block-video') ?>>
                                                        <?= $videoEmbed ?>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php elseif (!empty($block['block_data']['filename'])): ?>
                                                <div<?= element_attrs($block, 'page-block page-block-video') ?>>
                                                    <video class="page-block-video-player" controls preload="metadata" src="<?= esc(file_url($block['block_data']['filename'])) ?>"></video>
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            <?php elseif (!empty($page['content'])): ?>
                <div class="content">
                    <?= nl2br(esc($page['content'])) ?>
                </div>
            <?php endif; ?>
        </article>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= esc($site_name) ?></p>
        </div>
    </footer>
</body>
</html>
