# Fresh-install edition QA report — 2026-10-30

## Package inspection

- Based on `myimouto-php85-combined-20261030.zip`, including all prior hotfixes.
- Original bundled vendors, CSS, JavaScript and original install schema retained.
- PHP-FPM and Nginx configuration files are deliberately **not** included.
- The public `index.html` artifact was removed to prevent precedence over `index.php`.
- Original owner-specific config values and bundled placeholder root MySQL password replaced with neutral config placeholders; fresh installer writes site-specific values.
- Fresh installer refuses to operate unless its config placeholders are intact and `SHOW FULL TABLES` returns an empty database.
- Fresh installer creates password salt with `random_bytes(32)`, and assigns private group-readable permissions to credentials.
- PHP's existing MyImouto password hashing remains legacy salted SHA-1 (not modernized).

## Checks actually performed in this environment

- `php -l`: **1254/1254 PHP files passed** under PHP 8.4.24.
- `node --check` on both deployed JS bundles: **passed** under Node 22.
- `tools/fresh-install.php --check`: **schema structure and package integrity passed**; correctly detected missing PHP 8.5 and missing extensions in this environment.
- Schema: **68 SQL statements**, containing **31 CREATE TABLE**, **7 CREATE TRIGGER**, **23 ALTER TABLE** statements. The remaining statements drop triggers before creation.
- Database table/seed cross-reference confirmed `table_data.row_count`, first administrator SQL, and the expected 3 background-task rows.
- `Symfony\\Component\\Yaml\\Yaml::parse` from the bundled vendor directory parsed the generated connection format correctly, including `driver_options[1002]`.
- Assembled ZIP will be checked with `unzip -t` and SHA-256.

## Not tested in this environment

- No MySQL server or PDO MySQL extension is available; **the end-to-end fresh database creation and account login were not executed**.
- PHP 8.5 binaries and the Remi PHP-FPM pool are unavailable here; current fresh install script is only syntax-checked under PHP 8.4, although the predecessor application's migration had PHP 8.5 tests.
- Actual Nginx/Apache request dispatch, SELinux access, image uploads to the fresh installation and cron/background tasks require testing on the destination host.

**Do not interpret this report as certification of all runtime functionality.** The installer is suitable for a disposable NEW MySQL database; verify a test installation before exposing publicly.
