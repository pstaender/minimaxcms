<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

/**
 * Content file names may start with a numeric sort prefix, which is not part of the URL:
 * `01_my_post.md`, `1_my_post.md` and `001_my_post.md` are all `/my_post`.
 */
final class FileName
{
    private const string PREFIX = '/^(\d+)_(?=.)/';

    /** The URL slug: file name without extension and sort prefix. */
    public static function slug(string $file): string
    {
        return (string) preg_replace(self::PREFIX, '', pathinfo($file, PATHINFO_FILENAME));
    }

    /** The numeric sort prefix (`01_my_post.md` → 1), or null without one. */
    public static function order(string $file): ?int
    {
        return preg_match(self::PREFIX, pathinfo($file, PATHINFO_FILENAME), $m) === 1 ? (int) $m[1] : null;
    }

    /** Whether `$name` is `<slug>.<ext>` with a sort prefix, e.g. `01_my_post.md` for `my_post`. */
    public static function isPrefixedVariant(string $name, string $slug): bool
    {
        return preg_match('/^\d+_' . preg_quote($slug, '/') . '\.[^.]+$/', $name) === 1;
    }
}
