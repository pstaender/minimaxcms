<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\View;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Zeitpulse\MiniMaxCMS\Config\Config;
use Zeitpulse\MiniMaxCMS\Content\FileType;
use Zeitpulse\MiniMaxCMS\Content\Page;
use Zeitpulse\MiniMaxCMS\Content\Site;
use Zeitpulse\MiniMaxCMS\Content\UrlPath;

/**
 * Renders plain PHP templates from `templates/` and PHP content files.
 *
 * Templates get their data as local variables plus `$config` and `$site`. `partial('name')`
 * renders `templates/partials/name.php` with the same variables.
 */
final class View
{
    /** @var list<array{View, array<string, mixed>}> templates being rendered (innermost last), for partial() */
    private static array $rendering = [];

    public function __construct(
        private readonly Config $config,
        private readonly Site $site,
    ) {}

    /**
     * Render `templates/<name>.php`.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        if (preg_match('~^[\w-]+(/[\w-]+)*$~', $template) !== 1) {
            throw new InvalidArgumentException("Invalid template name: {$template}");
        }

        return $this->renderFile($this->config->templateDir . "/{$template}.php", $data);
    }

    /**
     * Render a content page: PHP pages run as is, others through their template
     * (or raw, if they have none).
     *
     * @param array<string, mixed> $data
     */
    public function renderPage(Page $page, array $data = []): string
    {
        $data = ['page' => $page, ...$data];
        $template = $page->template();

        return match (true) {
            $page->type === FileType::Php => $this->renderFile($page->file, $data),
            $template !== null => $this->render($template, $data),
            default => $page->content()->raw(),
        };
    }

    /**
     * Render `templates/partials/<name>.php` with the variables of the template that calls it.
     * Use the global `partial()` helper in templates.
     *
     * @param array<string, mixed> $data extra/overriding variables
     */
    public static function partial(string $name, array $data = []): string
    {
        $current = end(self::$rendering);
        if ($current === false) {
            throw new LogicException('partial() can only be used inside a template.');
        }

        [$view, $parentData] = $current;

        return $view->render("partials/{$name}", [...$parentData, ...$data]);
    }

    /**
     * The URL of a file in `public/` with `?timestamp=<mtime>` appended, so it can be cached forever.
     * Unknown files are returned unchanged. Use the global `static_file_url()` helper in templates.
     */
    public static function staticFileUrl(string $path): string
    {
        $current = end(self::$rendering);
        if ($current === false) {
            throw new LogicException('static_file_url() can only be used inside a template.');
        }

        $url = UrlPath::normalize($path);
        $file = $url !== null ? $current[0]->config->publicDir . rawurldecode($url) : null;

        return $file !== null && is_file($file) ? "{$url}?timestamp=" . filemtime($file) : $path;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('Template not found: ' . $this->relative($file));
        }

        $data = [...$data, 'config' => $this->config, 'site' => $this->site];
        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };

        self::$rendering[] = [$this, $data];
        $level = ob_get_level();
        ob_start();
        try {
            $render($file, $data);

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            array_pop(self::$rendering);
        }
    }

    private function relative(string $file): string
    {
        $root = rtrim($this->config->rootDir, '/') . '/';

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }
}
