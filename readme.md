# Elementary

Elementary is a simple, open-source content management system built in PHP.

It is designed on run on any standard shared hosting, web server, or cPanel server that runs the LAMP stack or compatible. You may also run it in Docker if you set up a suitable environment.

## Features and Benefits

Ships ready to use - great for small business, personal, portfolio, or blog websites.

Intuitive and light while remaining feature rich - sensible defaults while you get to make the choices you need to make your website yours.

Blazing fast - Elementary's built-in caching system means traffic spikes and AI bot crawling are problems of the past. Less resource usage and faster page load times deliver a better user experience, improved SEO, and lower hosting costs.

White label friendly - just change the app name in the config file to whatever you want. Great for web developers and agencies.

Secure by design - Elementary ships with user role management, 2FA, and an optional Cloudflare Turnstile plugin.

## Requirements

Elementary is designed to run on the LAMP stack or compatible Linux server environments.

Typical low cost shared web hosting plans are perfectly suited for Elementary, but of course you can run it on a VPS, cloud environment, dedicated server, or Docker.

## Installation

1. Upload files to your web root.
2. Create a MySQL database.
3. Rename config.sample.php to config.php and configure it. 
4. In your web browser, go to your site's URL and go to `/install` to create your admin account.
5. In the config file, set `installed` to `true` to disable future access to the installer.
4. Log in at `/admin` to get started!

## Configuration

Most core functionality is configured in the config.php file in the web root, while the Settings UI lets you manage most everyday features of your site conveniently from your browser.

This approach means handing sites off to clients or less technical users is less likely to result in a broken website or time-consuming support calls.

## Developing Themes

Themes live in `theme/`. Required files: `index.php` (metadata array) and `template.php` (default template). Optional: `post.php`, `page.php`, `category.php`, and `page-{slug}.php` custom page templates.

Templates receive variables via extract(): e.g. `$post`, `$page`, `$posts`, `$category`, `$site_name`, `$site_url`, `$title`.

Use hooks: `before_render`, `after_render`. Helpers: `url()`, `esc()`, `format_date()`.

You can only use one theme at a time. Your theme is set in the config file.

## Developing Plugins

Plugins live in `plugin/`. Required: `index.php` (metadata) and `plugin.php` (hooks).

Use `Hooks::addAction('plugin_install', ...)` to create tables on first enable.
Use `Hooks::addFilter('admin_settings_pages', ...)` to add settings pages.
Login hooks: `before_login`, `login_form_fields`, `forgot_password_form_fields`.

Enable plugins in config: `'plugins' => ['my-plugin']`.

## Updates

To update to a newer version, upload the new files, over-writing the existing installation. You should check the config.sample.php file to see if any new options have been introduced when updating.

Once the updated files are uploaded to your web server, go to `/update` on your website to migrate the database to the new version.

Keep in mind that your website may display an upgrade required message until you have done the database migration, so don't forget to check the `/update` page after uploading the new version.

## Included Packages

In the `vendors` folder, you'll find Bootstrap, Bootstrap icons, PHPMailer, and Summernote. Thank you to the wonderful people that built those!

## Recommended Hosting Providers

Thank you to these hosting providers for helping us out. If you select one of these companies, you'll be supporting the project at no additional cost to you while receiving fast, reliable web hosting. That's a win-win!

[NameCrane](https://namecrane.com/r/379/)
[Shock Hosting](https://shockhosting.com/portal/aff.php?aff=1392)
[Wag Websites](https://wagwebsites.com/hosting)

## License

GPL-3.0. Enjoy!
