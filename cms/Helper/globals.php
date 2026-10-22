<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\Media\ImageOptimizer;

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

if (!function_exists('optimized_content_image')) {
    /**
     * URL of a content image (e.g. `2026/hello.png`) converted to AVIF and cached in
     * `public/media/images/`, e.g. `/media/images/7abed….avif`. Returns `$file` unchanged
     * if Imagick/GD can't convert it, or if it is a WebP/AVIF of about 100 KB already (auto mode).
     *
     * @param int|null $maxFileSize roughly, in KB (null: auto)
     * @param int|null $quality 0–100 (null: auto, depending on the resolution)
     */
    function optimized_content_image(string $file, ?int $maxFileSize = null, ?int $quality = null): string
    {
        static $optimizer = null;
        if ($optimizer === null) {
            $config = Config::load(dirname(__DIR__));
            $optimizer = new ImageOptimizer($config->contentDir, $config->publicDir);
        }

        return $optimizer->url($file, $maxFileSize, $quality);
    }
}

if (!function_exists('optimized_content_images')) {
    /**
     * Rendered HTML with every local `<img>` pointing to its optimized AVIF
     * (see `optimized_content_image()`), e.g. `optimized_content_images($page->content()->md())`.
     *
     * @param int|null $maxFileSize roughly, in KB (null: auto)
     * @param int|null $quality 0–100 (null: auto, depending on the resolution)
     */
    function optimized_content_images(string $html, ?int $maxFileSize = null, ?int $quality = null): string
    {
        return ImageOptimizer::replaceImages(
            $html,
            static fn (string $file): string => optimized_content_image($file, $maxFileSize, $quality),
        );
    }
}
