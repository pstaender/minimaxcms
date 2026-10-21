<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer;

/**
 * Maps URL paths to the flat files in the content folder.
 *
 * - `/foo/bar`  → `content/foo/bar.{md,php,html,txt}`, else a sort-prefixed `content/foo/<n>_bar.*` (e.g. `01_bar.md`)
 * - `/foo/`     → `content/foo/index.{md,php,html,txt}`, else the folder listing if `content/foo/.folder.yml` exists
 * - `content/404.php` and `content/500.php` are error pages and not reachable directly.
 */
final readonly class ContentRepository
{
    private const array ERROR_PAGES = ['/404', '/500'];

    private FrontMatterParser $frontMatter;

    public function __construct(
        private string $contentDir,
        private MarkdownRenderer $markdown,
    ) {
        $this->frontMatter = new FrontMatterParser(new SymfonyYamlFrontMatterParser());
    }

    /** The page for `/foo/bar` (decoded URL path, no trailing slash). */
    public function page(string $urlPath): ?Page
    {
        $path = UrlPath::normalize($urlPath);
        if ($path === null || $path === '/' || basename($path) === 'index' || in_array($path, self::ERROR_PAGES, true)) {
            return null;
        }

        return $this->findPage($path);
    }

    /**
     * The Markdown page behind `/foo/bar.md` (or `/foo/index.md` for the folder's index page).
     */
    public function markdownSource(string $urlPath): ?Page
    {
        $path = UrlPath::normalize($urlPath);
        if ($path === null || !str_ends_with($path, '.md')) {
            return null;
        }

        $path = substr($path, 0, -3);
        $page = basename($path) === 'index' ? $this->index(dirname($path)) : $this->page($path);

        return $page?->type === FileType::Markdown ? $page : null;
    }

    /** The index page of the folder `/foo` (`content/foo/index.*`). */
    public function index(string $urlPath): ?Page
    {
        $path = UrlPath::normalize($urlPath);
        if ($path === null) {
            return null;
        }

        $dir = rtrim($path, '/');

        return $this->findPage("{$dir}/index");
    }

    /** The listable folder `/foo` (`content/foo/.folder.yml`). */
    public function folder(string $urlPath): ?Folder
    {
        $path = UrlPath::normalize($urlPath);
        if ($path === null) {
            return null;
        }

        $dir = rtrim($this->contentDir . $path, '/');
        $settingsFile = "{$dir}/.folder.yml";
        if (!is_file($settingsFile)) {
            return null;
        }

        return new Folder($dir, rtrim($path, '/') . '/', $this->parseFolderSettings($settingsFile), $this);
    }

    /**
     * The listable folders directly below `/foo`, sorted by name (natural order).
     *
     * @return list<Folder>
     */
    public function foldersIn(string $urlPath): array
    {
        $path = UrlPath::normalize($urlPath);
        $dir = $path !== null ? rtrim($this->contentDir . $path, '/') : null;
        if ($dir === null || !is_dir($dir)) {
            return [];
        }

        $names = array_filter(
            scandir($dir) ?: [],
            static fn (string $name): bool => !str_starts_with($name, '.') && is_file("{$dir}/{$name}/.folder.yml"),
        );
        natsort($names);

        return array_values(array_filter(array_map(
            fn (string $name): ?Folder => $this->folder(rtrim($path, '/') . "/{$name}"),
            $names,
        )));
    }

    /** `content/404.php` / `content/500.php` (or another supported extension), if present. */
    public function errorPage(int $status): ?Page
    {
        return $this->findPage("/{$status}");
    }

    /**
     * @return list<Page>
     */
    public function pagesIn(Folder $folder): array
    {
        $patterns = self::expandBraces($folder->filter());
        $pages = [];

        foreach (scandir($folder->dir) ?: [] as $name) {
            $file = "{$folder->dir}/{$name}";
            if (
                str_starts_with($name, '.')
                || FileName::slug($name) === 'index'
                || !is_file($file)
                || FileType::fromFile($name) === null
                || !self::matchesAny($patterns, $name)
            ) {
                continue;
            }

            $page = $this->load($file);
            if (!in_array($page->url(), self::ERROR_PAGES, true)) {
                $pages[] = $page;
            }
        }

        [$field, $descending] = [ltrim($folder->sort(), '-'), str_starts_with($folder->sort(), '-')];
        usort($pages, static function (Page $a, Page $b) use ($field, $descending): int {
            $order = $field === 'date'
                ? [$a->date(), $a->url()] <=> [$b->date(), $b->url()]
                // Sort-prefixed files first, by number; then the rest by name.
                : [$a->order() ?? PHP_INT_MAX, $a->slug()] <=> [$b->order() ?? PHP_INT_MAX, $b->slug()];

            return $descending ? -$order : $order;
        });

        return $pages;
    }

    /**
     * Find `<basePath>.<ext>`, else a sort-prefixed `<n>_<name>.<ext>` in the same folder
     * (extensions in FileType order, then the lowest prefix).
     */
    private function findPage(string $basePath): ?Page
    {
        foreach (FileType::cases() as $type) {
            $file = "{$this->contentDir}{$basePath}.{$type->value}";
            if (is_file($file)) {
                return $this->load($file);
            }
        }

        $dir = dirname($this->contentDir . $basePath);
        $slug = basename($basePath);
        $candidates = array_filter(
            is_dir($dir) ? scandir($dir) ?: [] : [],
            static fn (string $name): bool => FileName::isPrefixedVariant($name, $slug)
                && FileType::fromFile($name) !== null
                && is_file("{$dir}/{$name}"),
        );
        usort($candidates, static fn (string $a, string $b): int => [
            array_search(FileType::fromFile($a), FileType::cases(), true),
            FileName::order($a),
        ] <=> [
            array_search(FileType::fromFile($b), FileType::cases(), true),
            FileName::order($b),
        ]);

        return $candidates === [] ? null : $this->load("{$dir}/{$candidates[0]}");
    }

    private function load(string $file): Page
    {
        $type = FileType::fromFile($file) ?? throw new RuntimeException("Unsupported content file: {$file}");
        $meta = [];
        $body = '';

        if ($type->hasFrontMatter()) {
            $source = (string) file_get_contents($file);
            try {
                $parsed = $this->frontMatter->parse($source);
            } catch (InvalidFrontMatterException $e) {
                throw new RuntimeException("Invalid front matter in {$file}: {$e->getMessage()}", previous: $e);
            }
            $meta = is_array($parsed->getFrontMatter()) ? $parsed->getFrontMatter() : [];
            $body = $parsed->getContent();
        }

        return new Page($file, $this->urlOf($file), $type, $meta, $body, $this->markdown);
    }

    /** The canonical URL of a content file: `2026/01_post.md` → `/2026/post`, `docs/index.md` → `/docs/`. */
    private function urlOf(string $file): string
    {
        $dir = substr(dirname($file), strlen($this->contentDir));
        $slug = FileName::slug($file);

        return $slug === 'index' ? "{$dir}/" : "{$dir}/{$slug}";
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFolderSettings(string $file): array
    {
        // Glob values like `filter: *.md` are YAML aliases syntactically; quote them so they stay strings.
        $yaml = (string) preg_replace(
            '/^(\s*[\w.-]+\s*:[ \t]+)(\*[^\s#]*)/m',
            "$1'$2'",
            (string) file_get_contents($file),
        );

        try {
            $settings = Yaml::parse($yaml);
        } catch (ParseException $e) {
            throw new RuntimeException("Invalid {$file}: {$e->getMessage()}", previous: $e);
        }

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param list<string> $patterns
     */
    private static function matchesAny(array $patterns, string $name): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand `{a,b}` alternatives: `*.{md,txt}` → [`*.md`, `*.txt`].
     *
     * @return list<string>
     */
    private static function expandBraces(string $pattern): array
    {
        if (preg_match('/\{([^{}]*)\}/', $pattern, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return [$pattern];
        }

        [$group, $offset] = $m[0];
        $expanded = [];
        foreach (explode(',', $m[1][0]) as $option) {
            $candidate = substr($pattern, 0, $offset) . $option . substr($pattern, $offset + strlen($group));
            array_push($expanded, ...self::expandBraces($candidate));
        }

        return $expanded;
    }
}
