# CLAUDE.md

This file guides Claude Code when working in this repository.

## Project

MiniMax CMS: a flat file, markdown first, minimal CMS for PHP. No database and no admin UI; every file in `content/`
is a page. Published on Packagist as [`zeitpulse/minimaxcms`](https://packagist.org/packages/zeitpulse/minimaxcms), and
new sites start with `composer create-project zeitpulse/minimaxcms`. The repository is the CMS and an example site in one.

## Stack

- **PHP**: modern PHP (8.3+), meaning `declare(strict_types=1);`, typed properties/params/returns, readonly
  classes/properties, enums, match expressions, named arguments and first-class callables. No legacy patterns (no
  `array()`, no untyped code, no globals).
- **Routing**: [PHP Dumbo](https://github.com/notrab/dumbo). Keep routing in Dumbo; don't add another framework.
- **Markdown**: league/commonmark (GitHub flavoured). **Front matter**: symfony/yaml. **`.env`**: vlucas/phpdotenv.
- **Images**: Imagick or GD (AVIF), both optional; without them images are used as is.
- **CSS**: [minimax.css](https://www.npmjs.com/package/minimaxcss) from a CDN, plus `public/assets/css/layout.css`.
- **Dependencies**: managed with Composer. Keep them minimal.

## Structure

- `cms/`: the CMS, namespace `Zeitpulse\MiniMaxCMS` (`App`, `Config`, `Content`, `Controller`, `Helper`, `Http`,
  `Markdown`, `Media`, `Routing`, `View`). `cms/Helper/globals.php` holds the global template helpers.
- `public/`: web root. `index.php` is the only entry point; `media/images/` caches optimized images (generated).
- `config/` (`config.php`, optional `config.<host>.php`), `content/` (including `redirects.json`, `404.php`, `500.php`)
  and `templates/` (with `partials/`): the example site.
- `tests/`: Pest tests; `tests/Fixtures/site` is their site.

README.md covers installation, configuration and how content, folders, templates, images, error pages and redirects
behave. Keep it up to date when you change that behavior.

## Conventions

- One class per file; the folder is the sub-namespace. Routes are registered in `cms/App.php`, using first-class
  callables to controller methods that return PSR-7 responses.
- Dumbo's trailing-slash redirect is disabled (`cms/Http/Kernel.php`) because folder URLs end with `/`.
- Templates are plain PHP: escape every value with `esc()`, and output only rendered content (e.g. `$page->content()`)
  raw.
- Link files from `public/` via `static_file_url('/assets/…')` (adds `?timestamp=<mtime>`, output is already escaped, so
  don't wrap it in `esc()`), never with hard-coded URLs.
- Template helpers that need the site (`partial()`, `static_file_url()`, `optimized_content_image()`) get it from the
  template being rendered (`View::currentConfig()`), never by loading the config again.
- With `'debug' => true`, 500 errors show `cms/View/DebugPage.php` instead of `content/500.php`.
- `dd()`/`dump()` (symfony/var-dumper) are dev-only debugging helpers; never leave them in committed code.
- Keep pages fast and lightweight: no unnecessary JS, progressive enhancement first.

## Development

```sh
composer install
composer start    # APP_ENV=development php -S localhost:8000 -t public public/index.php
composer test
```

Run `composer dump-autoload` after adding new namespaces or helper files. Tests use only `tests/Fixtures/site` (or a
`tempSite()`), never the real `content/`; the helpers are in `tests/Pest.php`. Add tests and fixtures for every routing
or templating change. Tests that trigger a 500 call `$this->expectErrorLog()`, because the error handler logs every
exception.
