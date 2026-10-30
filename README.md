# MyImouto PHP 8.5 — Community Compatibility Release

An **unofficial** PHP 8.5 compatibility/fresh-install distribution of the legacy MyImouto imageboard software. This repository includes the application source, required vendor libraries, compiled assets, the historical schema, and a CLI-only fresh installer.

> **Status:** compatibility work in progress. The PHP source and JavaScript assets passed local syntax checks, and installer unit tests passed under PHP 8.4. A clean first install against Remi PHP 8.5 + MySQL has **not** been demonstrated in this build. This is legacy software and is not security-audited for public production use.

## Contents

- `app/`, `lib/`, `config/`, `db/`, `install/`, `script/`: application and installer source
- `vendor/`: bundled PHP libraries needed for an offline install
- `public/assets/`: precompiled CSS and JS, including the image-load fix
- `tools/fresh-install.php`: interactive, retryable installation tool (`--check`, `--install`, `--resume`)
- `tools/test-installer.php`: non-destructive installer unit tests
- `INSTALL-FRESH.md`: original v2 setup guide
- [`INSTALLATION-SOP.md`](INSTALLATION-SOP.md): formal end-to-end installation SOP
- [`GITHUB-PUBLISH-SOP.md`](GITHUB-PUBLISH-SOP.md): publication and Git commands
- [`SECURITY.md`](SECURITY.md): legacy software security and disclosure notice
- `LICENSE`: upstream MIT copyright and terms

## Quick start (new database only)

Use a new installation directory and empty MySQL database. Install Remi PHP 8.5 CLI/FPM and required extensions; run:

```bash
/opt/remi/php85/root/usr/bin/php tools/fresh-install.php --check
/opt/remi/php85/root/usr/bin/php tools/fresh-install.php --install
```

An interrupted install that has already touched the database must use `--resume`, **not** `--install`.

The installer creates `config/config.php` and `config/database.yml` at the end of setup. **Those two files are deliberately absent from Git and must never be committed.** See [`INSTALLATION-SOP.md`](INSTALLATION-SOP.md) for prerequisites, credentials, permissions, web server requirements, verification, and rollback.

## Compatibility fixes

Includes accumulated PHP 8.5 compatibility work: pagination argument order, version-history non-null `value_index`, upload error handling, deletion-state checks, tag sidebar listing, and asynchronous image resizing on post pages. See [`INSTALLER-V2-CHANGELOG.md`](INSTALLER-V2-CHANGELOG.md) for the installer v2 changes.

## Attribution and license

Original MyImouto copyright **© 2013 Pop|Parziphal|Asterix Vader**; original project license: MIT, retained in [`LICENSE`](LICENSE). Original authors are not represented as endorsing this community distribution. Third-party code remains subject to its own notices and licenses under `vendor/` and other bundled paths.

Do not publish production settings, uploads, databases, user data, API keys, or authentication tokens.
