<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

use RuntimeException;

/**
 * A listable content folder (one that contains a `.folder.yml`), available as
 * `$folder` (and `$page`) in its template.
 *
 * `.folder.yml` keys: `title`, `filter` (glob, default `*.{txt,md,php,html}`),
 * `template` (default `folder_index`), `sort` (`name` (default), `-name`, `date`, `-date`).
 * It applies to this folder only, not to subfolders.
 */
final class Folder
{
    public const string DEFAULT_FILTER = '*.{txt,md,php,html}';
    public const string DEFAULT_TEMPLATE = 'folder_index';
    public const array SORTS = ['name', '-name', 'date', '-date'];

    /** @var list<Page>|null */
    private ?array $pages = null;

    /**
     * @param array<string, mixed> $settings parsed `.folder.yml`
     */
    public function __construct(
        public readonly string $dir,
        private readonly string $url,
        private readonly array $settings,
        private readonly ContentRepository $repository,
    ) {}

    /** The folder URL, always with a trailing slash, e.g. `/2026/`. */
    public function url(): string
    {
        return $this->url;
    }

    /** The folder name, e.g. `2026`. */
    public function name(): string
    {
        return basename($this->dir);
    }

    public function title(): string
    {
        $title = $this->meta('title');

        return is_scalar($title) && (string) $title !== '' ? (string) $title : basename($this->dir);
    }

    public function filter(): string
    {
        $filter = $this->meta('filter');

        return is_string($filter) && $filter !== '' ? $filter : self::DEFAULT_FILTER;
    }

    public function template(): string
    {
        $template = $this->meta('template');

        return is_string($template) && $template !== '' ? $template : self::DEFAULT_TEMPLATE;
    }

    /**
     * `name`: by sort prefix (`01_…`), then file name; `date`: by front matter `date`, else mtime.
     * A leading `-` reverses the order.
     */
    public function sort(): string
    {
        $sort = $this->meta('sort', 'name');

        return in_array($sort, self::SORTS, true)
            ? $sort
            : throw new RuntimeException("Invalid sort in {$this->dir}/.folder.yml, use one of: " . implode(', ', self::SORTS));
    }

    /** A `.folder.yml` value, or all of them without a key. */
    public function meta(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->settings : $this->settings[$key] ?? $default;
    }

    /**
     * The pages directly in this folder that match the filter (no index files, no subfolders),
     * in `sort()` order.
     *
     * @return list<Page>
     */
    public function pages(): array
    {
        return $this->pages ??= $this->repository->pagesIn($this);
    }
}
