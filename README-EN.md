# Simple PHP Blog

A lightweight blog built around a single-entry-point architecture:

- The main application is contained in `index.php`
- The installation flow is contained in `install.php`
- SQLite storage
- A narrow, reading-focused frontend inspired by Hugo's `paper` theme

## Features

- Post listing on the homepage
- Individual post pages
- Comment lists, comment forms for guests and signed-in users, and comment replies
- Standalone pages
- Archives
- Tag pages
- RSS feed
- Administrator login
- Post management
- Comment moderation, unread notifications, and deletion
- Drafts, immediate publishing, and scheduled publishing
- Basic site settings
- Extensible frontend themes, theme assets, and action/filter hooks
- Toggleable plugins, plugin action/filter hooks, and administration UI
- An optional bundled English interface plugin
- Toggleable bundled plugins for AI writing, email notifications, and S3 uploads, each with separate configuration storage
- Dedicated theme management with previews and frontend theme switching
- Automatic GitHub Release checks and one-click application updates
- Optional pretty URLs
- Basic Markdown rendering
- Automatic embeds for NetEase Cloud Music, Bilibili, YouTube, and Douban links

## Media Embeds

Place a supported media URL in its own paragraph inside a post or standalone page. It will be converted automatically when the content is published:

- NetEase Cloud Music track, playlist, and album links become embedded players
- Bilibili and YouTube links become responsive video players
- Douban movie, music, and book subject links become linked information cards

URLs must include the full `http://` or `https://` scheme. A URL placed in the same paragraph as other text remains a regular link.

## Requirements

- PHP 8.0+
- The `pdo_sqlite` extension
- The `fileinfo` extension
- The `curl` extension when using S3 uploads
- Apache, Nginx, Caddy, or PHP's built-in web server

## Installation

### Single-File Automatic Installation

1. Upload only `installer.php` to the empty directory where you want to install the blog.
2. Open `installer.php` in your browser.
3. After the environment checks pass, click **Download and Deploy**. The installer downloads the latest stable release from the official GitHub repository.
4. Complete the site setup, then remove `installer.php` from the server.

The installer requires PHP 8.0+ and the `curl`, `zip`, `pdo_sqlite`, and `fileinfo` extensions. It does not overwrite existing files with the same names. If a write fails, files created during that deployment are rolled back.

### Manual Installation

1. Place the project in your web root or a subdirectory.
2. Make sure `data/` and `cache/` are writable.
3. Open `install.php` in your browser.
4. Choose Chinese or English installer prompts, then enter the site details and administrator account. The installer creates `Hello World` as the first post automatically.
5. After installation, sign in to the admin panel to finish configuring the site.

## Directory Structure

```text
index.php      Main entry point
install.php    Installation page
installer.php  Single-file automatic deployer
index.css      Frontend and admin styles
index.js       Frontend interactions
.htaccess      Apache rewrite rules and directory protection
data/          SQLite database, installation lock, and configuration
cache/         Settings cache
uploads/       Local uploads and optional S3 backups
themes/        Custom frontend themes
plugins/       Feature and language plugins
```

## Configuration and Cache

- The `settings` table stores basic site settings.
- `cache/settings.php` caches basic site settings to reduce database reads on regular pages.
- The `ai_settings` table stores the AI endpoint, model, prompts, and API key. These values are not written to the cache file.
- The `mail_settings` table stores the SMTP host, account, password, sender, and notification recipient. These values are not written to the cache file.
- The `s3_settings` table stores the S3 endpoint, bucket, access credentials, and upload options. These values are not written to the cache file.
- Saving basic site settings in the admin panel refreshes `cache/settings.php`.
- Saving AI, email notification, or S3 settings updates only the corresponding table.

## Custom Themes

- Place each theme in `themes/<theme-directory>/` and include a `theme.json` file.
- A theme can override frontend styles with `style.css`, register action/filter hooks in `functions.php`, or take over the complete frontend layout with `layout.php`.
- Preview and enable themes under **Themes** in the admin panel. If the selected theme is invalid or has been removed, the application falls back to the built-in theme.
- See `themes/README.md` for the theme development API and the complete hook reference.
- One-click updates replace release files but do not delete additional custom theme directories.

## Plugins

- Place each plugin in `plugins/<plugin-directory>/` with both `plugin.json` and `plugin.php`.
- Plugins can be enabled, disabled, and configured under **Plugins** in the admin panel. Bundled feature plugins do not add separate sidebar entries.
- Priority-based actions and filters can extend requests, post saves, comment creation, the admin sidebar, and final HTML output.
- The bundled `ai-assistant`, `email-notifications`, and `s3-storage` feature plugins are enabled on new installations and can be disabled independently.
- The bundled `english-language` and `russian-language` plugins translate the public site, sign-in pages, and administration interface without changing post content in the database. Language plugins are mutually exclusive.
- See `plugins/README.md` for the plugin API and complete hook reference.
- Plugins execute trusted server-side PHP. Install plugins only from sources you trust.

## S3 Uploads

After the `s3-storage` plugin and S3 uploads are enabled in the admin panel:

- New attachments are uploaded to Amazon S3 or a compatible service using AWS Signature Version 4.
- Object keys use the format `path-prefix/year/random-filename`.
- You can provide a complete CDN URL including its scheme. Attachment URLs will combine that URL with the object key. When left blank, URLs are generated from the S3 endpoint.
- Path-style URLs can be enabled for services such as MinIO.
- When **Keep a Local Backup** is enabled, the same file is also written to `uploads/<year>/`. When disabled, no local copy is stored in the site directory.
- If an S3 upload fails, no attachment link is inserted. Any local file created for that failed upload is also removed when local backups are enabled.

## Email Notifications

With the `email-notifications` plugin enabled, you can use SMTP or fall back to the server's PHP `mail()` transport:

- Password reset messages are sent through SMTP whenever possible.
- After a new comment is submitted, an email notification is sent if new-comment admin alerts are enabled in the site settings.
- The notification address configured under Email Notifications takes priority. If it is blank, the first administrator's email address is used.
- An SMTP failure does not prevent a comment from being submitted.
- A fallback password reset link is still written to `cache/password-reset-*.txt`.

## Running Locally

If PHP is installed locally, run:

```bash
php -S 127.0.0.1:8000
```

Then open:

```text
http://127.0.0.1:8000/install.php
```

## Pretty URLs

When pretty URLs are enabled in the admin panel, public pages and the main admin pages use paths such as:

- `/`
- `/page/2`
- `/archives`
- `/tags`
- `/tag/php`
- `/archive/your-slug`
- `/about`
- `/rss.xml`
- `/login`
- `/admin`
- `/write`
- `/edit/12`

Apache can use the repository's `.htaccess` file directly.

For Nginx, use the following configuration as a starting point:

```nginx
location ^~ /data/ { deny all; }
location ^~ /cache/ { deny all; }
location ~* ^/themes/.+\.(?:php|json)$ { deny all; }
location ~* ^/plugins/.+\.(?:php|json|md)$ { deny all; }

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

## Notes

- The `data/` and `cache/` directories must not be publicly accessible.
- The `ai_settings`, `mail_settings`, and `s3_settings` tables contain sensitive backend credentials. Modify them only through the admin panel.
- To reinstall the application, delete `data/install.lock` first.
- If an application update includes database schema changes, sign in to the admin panel and then open `update.php` to run the migration.
- One-click updates preserve `data/`, `cache/`, `uploads/`, and user-created themes and plugins. Release files under `themes/` and `plugins/` are merged recursively, and overwritten application, theme, and plugin files are backed up to `cache/update-backup-*`. If bundled themes or plugins are missing after an upgrade, the updater automatically reads the current release again to restore them.
