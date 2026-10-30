# MyImouto

An **unofficial** PHP 8.5 compatibility distribution of the legacy MyImouto imageboard software. This repository includes the application source, required vendor libraries, compiled assets, the historical schema, and a CLI-only fresh installer.


## Quick start

Use a new installation directory and empty MySQL database. Install Remi PHP 8.5 CLI/FPM and required extensions; run:

```bash
php tools/fresh-install.php --check
php tools/fresh-install.php --install
```
Make sure you have /public/data access permission if images can not be seen in website.

A sample website runnig with PHP 8.5: https://touhou.morian.icu/

An interrupted install that has already touched the database must use `--resume`, **not** `--install`.

## Attribution and license

Original MyImouto copyright **© 2013 Pop|Parziphal|Asterix Vader**; original project license: MIT, retained in [`LICENSE`](LICENSE). Original authors are not represented as endorsing this community distribution. Third-party code remains subject to its own notices and licenses under `vendor/` and other bundled paths.