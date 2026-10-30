# PHP 8.5 migration verification

Date: 2026-10-30. Source: the user's uploaded `myimouto.zip`, SHA-256 `c2c525bdb5cab99c5d376277bfc838a84a705089c435a8314cf4872a5add558e`.

## Test environment

| Component | Actual version / configuration |
| --- | --- |
| OS | Ubuntu 24.04, x86_64, isolated local test environment |
| PHP | 8.5.12 CLI, Zend Engine 4.5.12 |
| HTTP runtime | PHP 8.5.12 built-in HTTP server with a local router reproducing the front-controller and image-path rewrites |
| Error reporting | `E_ALL`, `log_errors=On`, `display_errors=Off`; logs checked for warnings, deprecations and exceptions |
| Database | MySQL Community Server 5.7.44 |
| SQL mode | `NO_ENGINE_SUBSTITUTION` |
| Browser | Chrome 131.0.6778.204, driven by Playwright, 1440 × 1000 viewport |
| Apache | 2.4.58 configuration syntax validation, including the shipped `.htaccess` and PHP-FPM handler example |

Actual PHP 8.5 binaries were used; the results are not based on PHP 8.2/8.3 or static inspection alone. Database tests used the supplied schema, synthetic users/posts/comments/pools/wiki/forum/notes, and generated PNG images. No production database or private server was accessed.

## Results

| Check | Result |
| --- | --- |
| All 1,253 PHP source files, including vendor and templates, linted with PHP 8.5.12 | PASS; no syntax errors or logged warnings/deprecations |
| SCSS/CSS and both JavaScript bundles rebuilt with PHP 8.5.12 | PASS |
| Rebuilt CSS compared with original uploaded CSS | Byte-for-byte identical |
| Every generated gzip asset compared with its uncompressed file | PASS |
| Both rebuilt JavaScript bundles checked with `node --check` | PASS |
| 32 HTTP functional requests | PASS; expected successful responses and redirects |
| Seven direct database assertions after write operations | PASS |
| Twelve browser page checks, including authenticated pages and image browsing | PASS; no JavaScript/console errors, failed HTTP asset requests, or broken images with assigned sources |
| Stylesheet loading in browser | Digest stylesheet loaded on all pages, 865 CSS rules |
| Final browser-run application and PHP logs | Empty; no PHP warning, deprecation, exception, or fatal error recorded |
| Apache configuration syntax | `Syntax OK` |
| Collection maximum regression with sparse keys and exhausted iterator | PASS |

CSS SHA-256: `04eefdbccbe94786d3020f2a152091e44b7176c498d7b2f71a04d20c769f10eb`.

### Functional coverage

HTTP checks covered login, account home/settings, upload form, tagged post search, JSON/XML post APIs, tag/artist/pool/wiki/forum/user indexes, notes/history, popular posts, browsing, history and post detail. Write checks exercised:

- Password login using the existing hash algorithm and original salt.
- Comment creation and subsequent database lookup.
- Note modification and creation of a note version.
- Post tag/rating update and persisted tag verification.
- Wiki modification and persisted body verification.
- A real multipart PNG upload, automatic thumbnail generation, database insertion, and retrieval of both the original image and preview.
- Logout, new-account registration, verification of the stored password hash, and login with that newly created account.

Browser checks covered `/post`, `/post/show/1`, `/comment`, `/user/login`, `/pool/show/1`, `/wiki/show?title=migration_test`, `/forum/show/1`, plus authenticated account home/settings, upload, post detail, and `/post/browse#1`. The browsing view's displayed image was explicitly checked. Empty image placeholders used by hidden editors were excluded from broken-image counts. Post list and detail screenshots were visually inspected; they retain the original dark layout, navigation, thumbnails, image, notes, and comments.

## Changes included

The port addresses removed/deprecated PHP syntax and APIs, nullable argument handling, legacy interface return signatures, intentional framework property containers, Throwable handling and transaction rollback, primary-key insertion handling, null string arguments, collection iteration, and the old reserved Zend `Null` writer class name. It also repairs legacy template syntax, SCSS parser/compiler compatibility, CSS minifier `/e` replacements, JavaScript minifier character handling, and asset directive parsing. JavaScript compilation now uses the bundled local minifier instead of the obsolete remote Closure service.

Password hashing, the configured password salt, database schema, and CSS appearance were preserved. The compiled CSS exactly matches the original. A file list of code changes is included in `PHP85-CHANGED-FILES.txt`.

## Limits of this verification

These results apply to the exercised scenarios in the environment above. Apache configuration was syntax-checked; live Apache-to-FPM request handling on CentOS was not exercised. No claim is made that every dormant legacy endpoint, all production data, every browser, SMTP, cloud storage, remote imports, scheduled jobs, concurrency, or strict SQL mode works. Atom/PicLens and other unfinished controllers were not enabled or claimed as functioning features. This is a PHP compatibility migration, not a comprehensive security audit or a framework modernization.

The release contains the tested application/vendor code and compiled assets. Local test credentials, synthetic uploaded images, database files, logs, cached entries, screenshots, and test runtimes are not included. The sanitized configuration files from the original upload are restored in the release; use the actual working configuration and uploads from your server as described in `MIGRATION-PHP85.md`.
