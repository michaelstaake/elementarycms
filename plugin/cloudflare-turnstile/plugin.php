<?php

declare(strict_types=1);

use Elementary\Database;
use Elementary\Hooks;
use Elementary\Logger;

function turnstile_get_setting(string $key, string $default = ''): string
{
    $row = Database::fetch(
        'SELECT setting_value FROM plugin_settings WHERE plugin_slug = ? AND setting_key = ?',
        ['cloudflare-turnstile', $key]
    );
    return $row ? (string) $row['setting_value'] : $default;
}

function turnstile_set_setting(string $key, string $value): void
{
    $existing = Database::fetch(
        'SELECT id FROM plugin_settings WHERE plugin_slug = ? AND setting_key = ?',
        ['cloudflare-turnstile', $key]
    );
    if ($existing) {
        Database::update('plugin_settings', ['setting_value' => $value], 'plugin_slug = ? AND setting_key = ?', ['cloudflare-turnstile', $key]);
    } else {
        Database::insert('plugin_settings', [
            'plugin_slug'   => 'cloudflare-turnstile',
            'setting_key'   => $key,
            'setting_value' => $value,
        ]);
    }
}

function turnstile_verify(string $token): bool
{
    $secret = turnstile_get_setting('secret_key');
    if (!$secret || !$token) {
        return false;
    }

    $response = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => Elementary\Logger::getClientIp(),
            ]),
        ],
    ]));

    if (!$response) {
        return false;
    }

    $data = json_decode($response, true);
    return !empty($data['success']);
}

Hooks::addAction('plugin_install', function (string $plugin) {
    if ($plugin !== 'cloudflare-turnstile') {
        return;
    }
    // Plugin uses shared plugin_settings table; no extra tables needed
    Logger::log('plugin_installed', null, 'cloudflare-turnstile');
});

Hooks::addFilter('admin_settings_pages', function (array $pages) {
    $pages['cloudflare-turnstile'] = [
        'title' => 'Cloudflare Turnstile',
        'file'  => ELEMENTARY_ROOT . '/plugin/cloudflare-turnstile/settings.php',
    ];
    return $pages;
});

Hooks::addAction('login_form_fields', function () {
    $siteKey = turnstile_get_setting('site_key');
    if (!$siteKey) {
        return;
    }
    echo '<div class="mb-3 turnstile-widget">';
    echo '<div class="cf-turnstile" data-sitekey="' . esc($siteKey) . '"></div>';
    echo '</div>';
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
});

Hooks::addAction('forgot_password_form_fields', function () {
    $siteKey = turnstile_get_setting('site_key');
    if (!$siteKey) {
        return;
    }
    echo '<div class="mb-3 turnstile-widget">';
    echo '<div class="cf-turnstile" data-sitekey="' . esc($siteKey) . '"></div>';
    echo '</div>';
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
});

Hooks::addAction('before_login', function (array $post) {
    if (!turnstile_get_setting('site_key')) {
        return;
    }
    if (!turnstile_verify($post['cf-turnstile-response'] ?? '')) {
        flash('error', 'CAPTCHA verification failed. Please try again.');
        redirect(admin_url('login'));
    }
});

Hooks::addAction('before_password_reset_request', function (array $post) {
    if (!turnstile_get_setting('site_key')) {
        return;
    }
    if (!turnstile_verify($post['cf-turnstile-response'] ?? '')) {
        flash('error', 'CAPTCHA verification failed. Please try again.');
        redirect(admin_url('forgot-password'));
    }
});
