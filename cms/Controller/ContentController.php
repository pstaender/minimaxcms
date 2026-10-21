<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Controller;

use Dumbo\Context;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Zeitpulse\MiniMaxCMS\Content\Assets;
use Zeitpulse\MiniMaxCMS\Content\ContentRepository;
use Zeitpulse\MiniMaxCMS\Content\FileType;
use Zeitpulse\MiniMaxCMS\Content\Folder;
use Zeitpulse\MiniMaxCMS\Content\Page;
use Zeitpulse\MiniMaxCMS\Http\Kernel;
use Zeitpulse\MiniMaxCMS\Routing\Redirects;
use Zeitpulse\MiniMaxCMS\View\View;

/**
 * Serves everything below `content/`: redirects, assets, pages, folder indexes.
 */
final readonly class ContentController
{
    public function __construct(
        private ContentRepository $content,
        private Assets $assets,
        private Redirects $redirects,
        private View $view,
    ) {}

    public function show(Context $c): ResponseInterface
    {
        $path = '/' . trim(rawurldecode($c->req->path()), '/');
        $isFolderUrl = $path === '/' || $c->req->raw()->getAttribute(Kernel::TRAILING_SLASH) === true;
        $query = $c->req->raw()->getUri()->getQuery();
        $withQuery = static fn (string $url): string => $query !== '' ? "{$url}?{$query}" : $url;

        if ($redirect = $this->redirects->find($path)) {
            return $c->redirect($redirect->target, $redirect->status);
        }

        if ($isFolderUrl) {
            if ($page = $this->content->index($path)) {
                return $this->page($c, $page);
            }
            if ($folder = $this->content->folder($path)) {
                return $this->folder($c, $folder);
            }
            if ($this->content->page($path) || $this->assets->file($path)) {
                return $c->redirect($withQuery($path), 301);
            }

            return $c->notFound();
        }

        if ($file = $this->assets->file($path)) {
            return $this->asset($c, $file);
        }
        if ($page = $this->content->markdownSource($path)) {
            return $this->markdown($c, $page);
        }
        if (basename($path) === 'index' && $this->content->index(dirname($path))) {
            return $c->redirect($withQuery(rtrim(dirname($path), '/') . '/'), 301);
        }
        if ($page = $this->content->page($path)) {
            // e.g. `/2026/01_post` → `/2026/post`
            return $page->url() === $path ? $this->page($c, $page) : $c->redirect($withQuery($page->url()), 301);
        }
        if ($this->content->index($path) || $this->content->folder($path)) {
            return $c->redirect($withQuery("{$path}/"), 302);
        }

        return $c->notFound();
    }

    private function page(Context $c, Page $page): ResponseInterface
    {
        if ($page->type === FileType::Markdown) {
            // Markdown pages answer `Accept: text/markdown` with their source.
            $c->header('Vary', 'Accept');
            if (self::prefersMarkdown($c->req->header('Accept'))) {
                return $this->markdown($c, $page);
            }
        }

        return $c->send($this->view->renderPage($page), $page->contentType() . '; charset=utf-8');
    }

    /** The Markdown source of a page, without its front matter. */
    private function markdown(Context $c, Page $page): ResponseInterface
    {
        return $c->send($page->content()->raw(), 'text/markdown; charset=utf-8');
    }

    /**
     * Whether the Accept header asks for Markdown at least as much as for HTML
     * (wildcards don't count, so browsers get HTML).
     */
    private static function prefersMarkdown(?string $accept): bool
    {
        $quality = ['markdown' => 0.0, 'html' => 0.0];

        foreach (explode(',', (string) $accept) as $range) {
            $params = array_map(trim(...), explode(';', $range));
            $kind = match (strtolower((string) array_shift($params))) {
                'text/markdown', 'text/x-markdown' => 'markdown',
                'text/html' => 'html',
                default => null,
            };
            if ($kind === null) {
                continue;
            }

            $q = 1.0;
            foreach ($params as $param) {
                if (preg_match('/^q=([\d.]+)$/i', $param, $m) === 1) {
                    $q = (float) $m[1];
                }
            }
            $quality[$kind] = max($quality[$kind], $q);
        }

        return $quality['markdown'] > 0 && $quality['markdown'] >= $quality['html'];
    }

    private function folder(Context $c, Folder $folder): ResponseInterface
    {
        return $c->html($this->view->render($folder->template(), [
            'folder' => $folder,
            'page' => $folder,
        ]));
    }

    /**
     * Send a static file with validators; `?timestamp=…` URLs are immutable.
     */
    private function asset(Context $c, string $file): ResponseInterface
    {
        $modified = (int) filemtime($file);
        $etag = sprintf('"%x-%x"', $modified, (int) filesize($file));
        $headers = [
            'Cache-Control' => $c->req->query('timestamp') !== null
                ? 'public, max-age=31536000, immutable'
                : 'public, max-age=3600',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modified) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $ifNoneMatch = $c->req->header('If-None-Match');
        $ifModifiedSince = $c->req->header('If-Modified-Since');
        $notModified = $ifNoneMatch !== null
            ? in_array($etag, array_map(trim(...), explode(',', $ifNoneMatch)), true) || trim($ifNoneMatch) === '*'
            : $ifModifiedSince !== null && (int) strtotime($ifModifiedSince) >= $modified;

        if ($notModified) {
            return $c->send('', (string) $this->assets->mimeType($file), 304, $headers);
        }

        return $c->send(Stream::create(fopen($file, 'rb')), (string) $this->assets->mimeType($file), 200, $headers);
    }
}
