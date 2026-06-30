<?php

declare(strict_types=1);

namespace Elementary;

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::render(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    public static function handleException(\Throwable $e): void
    {
        self::render($e);
    }

    public static function render(\Throwable $e): void
    {
        $code = $e->getCode() ?: 500;
        if ($code < 100 || $code > 599) {
            $code = 500;
        }

        http_response_code($code);

        $debug = Config::get('debug_mode', false);
        $appName = Config::get('app_name', 'Elementary');

        $errorNames = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];

        $errorName = $errorNames[$code] ?? 'Error';
        if ($e->getMessage() === 'Configuration invalid') {
            $errorName = 'Configuration Invalid';
        }
        $friendlyMessages = [
            400 => 'The request could not be understood. Please check your input and try again.',
            401 => 'You need to be logged in to access this page.',
            403 => 'You do not have permission to access this resource.',
            404 => 'The page you are looking for could not be found.',
            500 => 'Something went wrong on our end. Please try again later.',
            503 => 'The site is temporarily unavailable. Please try again later.',
        ];
        $friendlyMessage = $friendlyMessages[$code] ?? 'An unexpected error occurred.';
        if ($e->getMessage() === 'Configuration invalid') {
            $friendlyMessage = 'Installation is not complete. Edit config.php and set installed to true.';
        }
        if ($e->getMessage() === 'Database is not empty') {
            $friendlyMessage = 'Installation cannot run because the configured database already contains tables. Use an empty database or remove existing tables first.';
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        // Check for a theme-based 404 template
        if ($code === 404) {
            $theme = Config::get('theme', '2026');
            $theme404 = ELEMENTARY_ROOT . '/theme/' . $theme . '/404.php';
            if (file_exists($theme404)) {
                $site_name = Config::get('site_name', '');
                $site_url = Config::get('site_url', '');
                extract(get_defined_vars(), EXTR_SKIP);
                include $theme404;
                exit;
            }
        }

        include ELEMENTARY_ROOT . '/core/views/error.php';
        exit;
    }
}
