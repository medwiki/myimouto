# MyImouto

An **unofficial** PHP 8.5 compatibility distribution of the legacy MyImouto imageboard software. This repository includes the application source, required vendor libraries, compiled assets, the historical schema, and a CLI-only fresh installer.

> **Status:** compatibility work in progress. The PHP source and JavaScript assets passed local syntax checks, and installer unit tests passed under PHP 8.4. A clean first install against Remi PHP 8.5 + MySQL has **not** been demonstrated in this build. This is legacy software and is not security-audited for public production use.

## Quick start

Use a new installation directory and empty MySQL database. Install Remi PHP 8.5 CLI/FPM and required extensions; run:

```bash
/opt/remi/php85/root/usr/bin/php tools/fresh-install.php --check
/opt/remi/php85/root/usr/bin/php tools/fresh-install.php --install
```
Make sure you have /public/data access permission if images can not be seen in website.

A sample website runnig with PHP 8.5: https://touhou.morian.icu/

An interrupted install that has already touched the database must use `--resume`, **not** `--install`.

## Attribution and license

Original MyImouto copyright **© 2013 Pop|Parziphal|Asterix Vader**; original project license: MIT, retained in [`LICENSE`](LICENSE). Original authors are not represented as endorsing this community distribution. Third-party code remains subject to its own notices and licenses under `vendor/` and other bundled paths.