<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

/**
 * Static files (images, media, documents) stored next to the content.
 */
final readonly class Assets
{
    /** Extensions that may be served from the content folder, with their media types. */
    private const array MIME_TYPES = [
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'pdf' => 'application/pdf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function __construct(private string $contentDir) {}

    /**
     * The absolute file for a (decoded) URL path, if it is a servable asset.
     */
    public function file(string $urlPath): ?string
    {
        $path = UrlPath::normalize($urlPath);
        if ($path === null || $this->mimeType($path) === null) {
            return null;
        }

        $file = $this->contentDir . $path;

        return is_file($file) ? $file : null;
    }

    public function mimeType(string $file): ?string
    {
        return self::MIME_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * Resolve a URL used in content (relative to `$baseUrl`) and, if it points to a
     * local asset, append `?timestamp=<mtime>` so it can be cached forever.
     * External and unknown URLs are returned unchanged.
     */
    public function versionedUrl(string $url, string $baseUrl = '/'): string
    {
        if ($url === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//|#)~i', $url) === 1) {
            return $url;
        }

        $fragment = '';
        if (($hash = strpos($url, '#')) !== false) {
            [$url, $fragment] = [substr($url, 0, $hash), substr($url, $hash)];
        }
        $query = '';
        if (($mark = strpos($url, '?')) !== false) {
            [$url, $query] = [substr($url, 0, $mark), substr($url, $mark + 1)];
        }

        $path = UrlPath::normalize(str_starts_with($url, '/') ? $url : UrlPath::directory($baseUrl) . $url);
        $file = $path !== null ? $this->file(rawurldecode($path)) : null;

        if ($file === null) {
            return $url . ($query !== '' ? "?{$query}" : '') . $fragment;
        }

        parse_str($query, $params);
        if (!isset($params['timestamp'])) {
            $query .= ($query !== '' ? '&' : '') . 'timestamp=' . filemtime($file);
        }

        return "{$path}?{$query}{$fragment}";
    }
}
