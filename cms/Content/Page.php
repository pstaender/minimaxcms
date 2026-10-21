<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Content;

use DateTimeImmutable;
use DateTimeInterface;
use Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer;

/**
 * A single content file (`content/**.{md,php,html,txt}`), available as `$page` in templates.
 */
final readonly class Page
{
    /**
     * @param array<string, mixed> $meta front matter
     */
    public function __construct(
        public string $file,
        private string $url,
        public FileType $type,
        private array $meta,
        private string $body,
        private MarkdownRenderer $markdown,
    ) {}

    /** The canonical URL, e.g. `/2026/my_post` or `/2026/thoughts/` for an index page. */
    public function url(): string
    {
        return $this->url;
    }

    /** Front matter `title`, else the first `# Heading` of a Markdown page. */
    public function title(): ?string
    {
        $title = $this->meta('title');
        if (is_scalar($title) && (string) $title !== '') {
            return (string) $title;
        }

        if ($this->type === FileType::Markdown && preg_match('/^#[ \t]+(.+?)[ \t#]*$/m', $this->body, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** A front matter value, or all of them without a key. */
    public function meta(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->meta : $this->meta[$key] ?? $default;
    }

    public function content(): Content
    {
        return new Content($this->body, $this->type, $this->url(), $this->markdown);
    }

    /**
     * The template (relative to `templates/`, without `.php`) this page is rendered with,
     * or null if the file is output as is (PHP pages, HTML/text without `template`).
     */
    public function template(): ?string
    {
        $template = $this->meta('template');

        return match (true) {
            $this->type === FileType::Php => null,
            is_string($template) && $template !== '' => $template,
            $this->type === FileType::Markdown => 'default',
            default => null,
        };
    }

    /** The response media type: plain text for `.txt` pages without template, otherwise HTML. */
    public function contentType(): string
    {
        return $this->type === FileType::Text && $this->template() === null ? 'text/plain' : 'text/html';
    }

    /** Front matter `date`, else the file's modification time. */
    public function date(): DateTimeImmutable
    {
        $date = $this->meta('date');

        return match (true) {
            $date instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($date),
            is_int($date) => new DateTimeImmutable("@{$date}"),
            is_string($date) && strtotime($date) !== false => new DateTimeImmutable($date),
            default => new DateTimeImmutable('@' . filemtime($this->file)),
        };
    }

    /** The file name without extension and sort prefix, e.g. `we_are_entering_a_new_software_era`. */
    public function slug(): string
    {
        return FileName::slug($this->file);
    }

    /** The numeric sort prefix of the file name (`01_post.md` → 1), or null. */
    public function order(): ?int
    {
        return FileName::order($this->file);
    }
}
