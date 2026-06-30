<?php

declare(strict_types=1);

use Elementary\Config;
use Elementary\ErrorHandler;
use Elementary\Auth;
use Elementary\Router;
use Elementary\PluginLoader;
use Elementary\Upgrader;

ErrorHandler::register();

$config = Config::load();
date_default_timezone_set($config['time_zone'] ?? 'UTC');

$route = trim($_GET['route'] ?? '', '/');
$segments = $route !== '' ? explode('/', $route) : [];
$needsFullBootstrap = in_array($segments[0] ?? '', ['admin', 'install'], true);

if ($needsFullBootstrap) {
    Auth::startSession();
}

if (Config::get('installed', false)) {
    try {
        Config::validate();

        if ($needsFullBootstrap) {
            if (Upgrader::needsUpgrade()) {
                $_SESSION['upgrade_required'] = true;
            } else {
                unset($_SESSION['upgrade_required']);
            }

            PluginLoader::loadAll();
        }
    } catch (\Throwable $e) {
        ErrorHandler::handleException($e);
    }
}

$router = new Router();
$router->dispatch();
