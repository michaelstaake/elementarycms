<?php

return [
    // Language code for the interface (e.g., 'en_US',)
    'lang'             => 'en_US',

    // PHP timezone for date/time functions
    'time_zone'        => 'America/Los_Angeles',

    // Base URL of the site (no trailing slash)
    'site_url'         => 'http://localhost',

    // Displayed site name
    'site_name'        => 'My Website',

    // Application name
    'app_name'         => 'Elementary',

    // Enable debug mode (may reveal sensitive info on error pages)
    'debug_mode'       => false,

    // Enable maintenance mode (shows a maintenance page to visitors)
    'maintenance_mode' => false,

    // Enable page caching for faster page loads
    'cache'            => true,

    // Database server hostname
    'db_server'        => 'localhost',

    // Database name
    'db_name'          => '',

    // Database username
    'db_user'          => '',

    // Database password
    'db_pass'          => '',

    // SMTP server hostname (leave empty to use PHP mail())
    'smtp_host'        => '',

    // SMTP server port (587 = TLS, 465 = SSL)
    'smtp_port'        => 587,

    // SMTP username
    'smtp_user'        => '',

    // SMTP password
    'smtp_pass'        => '',

    // SMTP "from" email address
    'smtp_from_email'  => '',

    // SMTP "from" display name
    'smtp_from_name'   => '',

    // Active theme folder name - default theme is 2026
    'theme'            => '2026',

    // Enabled plugin slugs (e.g., ['cloudflare-turnstile'])
    'plugins'          => [],

    // Protected usernames cannot be deleted or modified by other admins
    'protected_users'  => [],

    // Allowed file extensions for uploads
    'file_types'       => ['png', 'jpg', 'jpeg', 'svg', 'ico', 'pdf', 'xlsx',],

    // Maximum allowed file upload size
    'file_size'        => '256M',

    // Session lifetime in seconds (2592000 = 30 days)
    'session_lifetime' => 2592000,

    // Set to true after installation
    'installed'        => false,
];
