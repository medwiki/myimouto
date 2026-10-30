# MyImouto PHP 8.5 migration

This package is a compatibility port of the exact `myimouto.zip` you supplied, including its RailsPHP v1.0.6 dependencies and existing design. It is not the newer Wachhund fork. PHP 8.5.12 and MySQL 5.7.44 were used for the tests described in `TEST-REPORT-PHP85.md`.

The patched vendor directory and rebuilt CSS/JavaScript are included. No Composer installation, Composer update, Node.js, or online minification service is required to deploy this package.

## Deploy alongside the current installation

1. Back up the existing application and MySQL database. Keep the current installation available for rollback. Extract this ZIP to a new directory, for example `/storage/nimo-php85`.
2. Copy your **actual working server configuration** into the new directory: `config/config.php`, `config/database.yml`, and your customized `config/default_config.php`. The copies included here are the sanitized versions from your upload. Preserve the existing `user_password_salt` exactly: changing it makes existing password hashes unusable. Also preserve any other local configuration changes you made after uploading the archive.
3. Keep your existing `public/data` images, avatars, and other uploads. Copy them into the new release or configure the same existing data directory there. **This ZIP excludes uploaded content, temporary files, logs, and cache entries. Do not replace or delete the live data directory.**
4. Give the PHP-FPM worker account read access to the application and write access to `log`, `tmp`, and `public/data`. Give it write access to `public/assets` only when rebuilding assets. Use your actual FPM pool user; do not assume it is `apache` or `www`. Do not make the whole application world-writable.
5. Point the site's Apache `DocumentRoot` at the new release's **`public` directory** and allow its `.htaccess` rewrite rules. Ensure `mod_rewrite`, `mod_proxy`, and `mod_proxy_fcgi` are loaded.
6. Point this virtual host at PHP 8.5 FPM. Validate the Apache configuration before reloading it, and reload your PHP 8.5 FPM service to discard any old OPcache entries. Check the pages listed in the test report with your real database.

Do not run the installer, import `install/structure.php`, or recreate the database when upgrading an existing site. The migration does not require a database schema change. Tests retained MySQL 5.7 with legacy-compatible SQL mode; this package does not establish compatibility with MySQL 8 or stricter SQL modes.

Example Apache fragment for your existing Remi-style socket (adjust the release path):

```apache
DocumentRoot "/storage/nimo-php85/public"

<Directory "/storage/nimo-php85/public">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>

<FilesMatch "\.php$">
    SetHandler "proxy:unix:/var/opt/remi/php85/run/php-fpm/www.sock|fcgi://localhost/"
</FilesMatch>
```

Keep your existing HTTPS configuration. The socket must match the running PHP 8.5 pool. If `open_basedir` is configured, allow the **whole application directory**, its actual upload directory, and the required temporary/session directories. Allowing only `public/` prevents PHP from loading `config/boot.php` and `vendor/`.

For CentOS with SELinux enabled, retain appropriate read labels for the code and write labels for the writable directories. Check actual denials if access fails; disabling SELinux is not part of this migration.

## PHP and database requirements

Use PHP 8.5 with PDO MySQL, mbstring, GD with JPEG/PNG support, fileinfo, ctype, iconv, tokenizer, curl, XML/DOM/SimpleXML, and zlib. The test environment also loaded mysqli, intl, zip, bz2, exif, posix, sockets, and phar. CLI and FPM must use the same patched application and required extensions.

Keep your working database settings. If the new PHP build cannot find a MySQL Unix socket, set the correct socket for that PHP build, or use `127.0.0.1` and the actual TCP port if MySQL is listening there and the account is authorized. Do not copy the local test database settings into production.

## Assets

The ZIP includes `public/assets/manifest.yml`, both JavaScript bundles, and `application.css`, with matching digest and gzip files. The rebuilt CSS is byte-for-byte identical to the CSS in your upload. The malformed forced-gzip rewrite rules were removed; Apache serves the normal assets without requiring precompressed responses. Server-level compression may be configured separately.

If you later change SCSS or JavaScript, rebuild from the application root using your PHP 8.5 CLI executable:

```bash
cd /storage/nimo-php85
/opt/php-8.5.12/bin/php config/boot.php assets compile:all
```

Adjust the PHP binary path if necessary. Successful output must include creation of **both JavaScript bundles and `application.css`**, followed by an updated manifest containing all three logical assets. Inspect `log/production.log` and your PHP error log if any asset is missing. The legacy console error handler can exit without a nonzero status, so the exit code alone is insufficient.

**Do not run `composer update` or reinstall the original locked vendor packages over this release.** Compatibility fixes are included in those vendored files; replacing them with the old upstream versions removes the fixes. Keep the complete patched release together.

## Rollback

Switch the virtual host back to the previous application directory and its previous PHP-FPM pool, then reload Apache/FPM. Keep the existing database and uploaded files. This code port does not change the password hashing scheme or database schema.

## Scope

The tested operations are detailed in the accompanying report. External mail delivery, remote image imports, cloud storage, background jobs, production traffic/load, and the original application's unfinished endpoints were not validated. Your CentOS permissions, SELinux policy, production data, and FPM configuration must still match your deployment.
