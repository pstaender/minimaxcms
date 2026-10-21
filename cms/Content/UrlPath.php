<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

/**
 * Normalizes URL paths and keeps them inside the content folder.
 */
final class UrlPath
{
    /**
     * Resolve `.`/`..` and duplicate slashes into `/a/b` (no trailing slash; `/` for the root).
     * Returns null for paths that escape the root or touch hidden files (`.env`, `.folder.yml`, …).
     */
    public static function normalize(string $path): ?string
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);
                continue;
            }
            if (str_starts_with($segment, '.')) {
                return null;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    /** The directory part of a URL: `/2026/post` → `/2026/`, `/2026/thoughts/` → `/2026/thoughts/`. */
    public static function directory(string $url): string
    {
        return substr($url, 0, (int) strrpos($url, '/') + 1) ?: '/';
    }
}
