<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

/**
 * Access to the whole content tree from templates, available as `$site`.
 *
 *     <?php foreach ($site->folders() as $folder): ?> … <?php endforeach; ?>
 */
final readonly class Site
{
    public function __construct(private ContentRepository $content) {}

    /** The page at a URL: `/about`, or `/docs/` for a folder's index page. */
    public function page(string $url): ?Page
    {
        return str_ends_with($url, '/') ? $this->content->index($url) : $this->content->page($url);
    }

    /** The listable folder at a URL, e.g. `/2026/`. */
    public function folder(string $url): ?Folder
    {
        return $this->content->folder($url);
    }

    /**
     * The listable subfolders (with `.folder.yml`) directly below `$url`, sorted by name.
     *
     * @return list<Folder>
     */
    public function folders(string $url = '/'): array
    {
        return $this->content->foldersIn($url);
    }
}
