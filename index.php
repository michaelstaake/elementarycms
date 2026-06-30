<?php

declare(strict_types=1);

define('ELEMENTARY_ROOT', __DIR__);
define('ELEMENTARY_START', microtime(true));

require_once ELEMENTARY_ROOT . '/core/helpers.php';
spl_autoload_register('autoload_core');

require ELEMENTARY_ROOT . '/core/HtmlCache.php';

if (\Elementary\HtmlCache::tryServeEarly()) {
    exit;
}

require ELEMENTARY_ROOT . '/core/bootstrap.php';
