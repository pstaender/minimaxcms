# CLAUDE.md

This file guides Claude Code when working in this repository.

## Project

A simple flat file, markdown first cms

## Stack

- **PHP** — modern PHP (8.3+): `declare(strict_types=1);`, typed properties/params/returns, readonly classes/properties, enums, match expressions, named arguments, first-class callables. No legacy patterns (no `array()`, no untyped code, no globals).
- **Routing** — [PHP Dumbo](https://github.com/notrab/dumbo) (`composer require notrab/dumbo`). Keep routing in Dumbo; don't add another framework.
- **Dependencies** — managed with Composer. Keep them minimal.

## Structure

- `public/`: web root. `index.php` is the only entry point.
- `config/` (`config.php`, optional `config.<host>.php`), `content/` (including `redirects.json`), `templates/`: the site.
- `tests/`: Pest tests.

README.md covers how content, folders, templates and redirects behave. Keep it up to date when you change that behavior.

## Conventions

- One class per file; the folder is the sub-namespace. Routes are registered in `cms/App.php`, using first-class callables to controller methods that return PSR-7 responses.
- Dumbo's trailing-slash redirect is disabled (`cms/Http/Kernel.php`) because folder URLs end with `/`.
- Templates are plain PHP: escape every value with `esc()`, and output only rendered content (e.g. `$page->content()`) raw.
- Link files from `public/` via `static_file_url('/assets/…')` (adds `?timestamp=<mtime>`, output is already escaped — don't wrap it in `esc()`), never with hard-coded URLs.
- `dd()`/`dump()` (symfony/var-dumper) are dev-only debugging helpers; never leave them in committed code.
- Keep pages fast and lightweight: no unnecessary JS, progressive enhancement first.

## Development

```sh
composer install
composer start    # APP_ENV=development php -S localhost:8000 -t public public/index.php
composer test
```

Run `composer dump-autoload` after adding new namespaces or helper files. Tests use only `tests/Fixtures/site`, never the real `content/`; the helpers are in `tests/Pest.php`. Add tests and fixtures for every routing or templating change.

## Prompt & answer logs

Hooks in `.claude/settings.json` log every exchange automatically, with timestamps:

- `prompts.log` — each user prompt (`UserPromptSubmit` hook).
- `ai_answers.log` — Claude's final answer/summary at the end of each turn (`Stop` hook, `.claude/hooks/log-answer.sh`).

Logging is automatic, so don't write to these files by hand. Don't edit or truncate them, and don't add them to `.gitignore` unless asked. End every turn with a clear summary of what was done, because that's what goes into `ai_answers.log`.
