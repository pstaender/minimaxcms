<?php

declare(strict_types=1);

/*
 * Global template helpers (no namespace, so templates can call them without `use`).
 */

use Zeitpulse\MiniMaxCMS\Helper\Html;
use Zeitpulse\MiniMaxCMS\View\View;

if (!function_exists('partial')) {
    /**
     * Render `templates/partials/<name>.php` with the current template's variables (plus `$data`).
     *
     * @param array<string, mixed> $data
     */
    function partial(string $name, array $data = []): string
    {
        return View::partial($name, $data);
    }
}

if (!function_exists('static_file_url')) {
    /**
     * URL of a file in `public/` with `?timestamp=<mtime>` for cache busting,
     * e.g. `static_file_url('/assets/css/app.css')` → `/assets/css/app.css?timestamp=1791622677`.
     * The result is HTML-escaped, ready to output in an attribute.
     */
    function static_file_url(string $path): string
    {
        return Html::esc(View::staticFileUrl($path));
    }
}

if (!function_exists('env')) {
    /**
     * An environment variable (from the real environment or `.env`), or `$default`
     * if it is unset or empty. Meant for `config/*.php`.
     */
    function env(string $name, ?string $default = null): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}

if (!function_exists('esc')) {
    /**
     * Escape a value for HTML output.
     */
    function esc(string|int|float|null $value): string
    {
        return Html::esc($value);
    }
}
