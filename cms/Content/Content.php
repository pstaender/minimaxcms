<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

use Stringable;
use Zeitpulse\MiniMaxCMS\Helper\Html;
use Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer;

/**
 * The body of a page (everything after the front matter).
 *
 * In templates: `<?= $page->content() ?>` outputs the best default for the file type,
 * or pick explicitly with `->md()`, `->esc()` or `->raw()`.
 */
final readonly class Content implements Stringable
{
    public function __construct(
        private string $raw,
        private FileType $type,
        private string $baseUrl,
        private MarkdownRenderer $markdown,
    ) {}

    /** The unprocessed source. */
    public function raw(): string
    {
        return $this->raw;
    }

    /** Render the body as GitHub flavoured Markdown. */
    public function md(): string
    {
        return $this->markdown->toHtml($this->raw, $this->baseUrl);
    }

    /** The body, HTML-escaped (for plain text). */
    public function esc(): string
    {
        return Html::esc($this->raw);
    }

    /**
     * The best default output for the file type: `md()` for Markdown, `esc()` for plain text,
     * `raw()` for everything else (HTML).
     */
    public function __toString(): string
    {
        return match ($this->type) {
            FileType::Markdown => $this->md(),
            FileType::Text => $this->esc(),
            FileType::Html, FileType::Php => $this->raw(),
        };
    }
}
