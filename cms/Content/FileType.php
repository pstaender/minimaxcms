<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

/**
 * Content file types, in lookup order: `/foo` resolves to foo.md, then foo.php, foo.html, foo.txt.
 */
enum FileType: string
{
    case Markdown = 'md';
    case Php = 'php';
    case Html = 'html';
    case Text = 'txt';

    public static function fromFile(string $file): ?self
    {
        return self::tryFrom(strtolower(pathinfo($file, PATHINFO_EXTENSION)));
    }

    /** Whether the file starts with optional YAML front matter (`---` … `---`). */
    public function hasFrontMatter(): bool
    {
        return $this !== self::Php;
    }
}
