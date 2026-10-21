<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Zeitpulse\MiniMaxCMS\Content\Assets;

/**
 * GitHub flavoured Markdown (tables, task lists, strikethrough, autolinks, …) plus footnotes via league/commonmark.
 *
 * Local image URLs are resolved against the page URL and versioned with `?timestamp=<mtime>`.
 */
final readonly class MarkdownRenderer
{
    public function __construct(private Assets $assets) {}

    /**
     * @param string $baseUrl URL of the page the Markdown belongs to (relative URLs resolve against it)
     */
    public function toHtml(string $markdown, string $baseUrl = '/'): string
    {
        $environment = new Environment([
            // Content is written by the site owner, so inline HTML is allowed.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            // GFM's default list minus `style`, so pages can carry their own CSS.
            'disallowed_raw_html' => [
                'disallowed_tags' => ['title', 'textarea', 'xmp', 'iframe', 'noembed', 'noframes', 'script', 'plaintext'],
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FootnoteExtension());
        $environment->addEventListener(
            DocumentParsedEvent::class,
            function (DocumentParsedEvent $event) use ($baseUrl): void {
                foreach ($event->getDocument()->iterator() as $node) {
                    if ($node instanceof Image) {
                        $node->setUrl($this->assets->versionedUrl($node->getUrl(), $baseUrl));
                    }
                }
            },
        );

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }
}
