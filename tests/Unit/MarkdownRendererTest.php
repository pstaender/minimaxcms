<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\Content\Assets;
use Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer;

beforeEach(function () {
    $this->markdown = new MarkdownRenderer(new Assets(fixtureRoot() . '/content'));
});

it('renders GitHub flavoured markdown', function () {
    $html = $this->markdown->toHtml((string) file_get_contents(fixtureRoot() . '/content/about.md'));

    expect($html)
        ->toContain('<table>')
        ->toContain('<td>Tables</td>')
        ->toContain('<input checked="" disabled="" type="checkbox">')
        ->toContain('<del>strikethrough</del>');
});

it('autolinks URLs', function () {
    expect($this->markdown->toHtml('See https://example.com'))
        ->toContain('<a href="https://example.com">https://example.com</a>');
});

it('adds ?timestamp=<mtime> to local images, resolved against the page URL', function () {
    $pixel = filemtime(fixtureRoot() . '/content/blog/pixel.png');
    $logo = filemtime(fixtureRoot() . '/content/logo.png');

    $html = $this->markdown->toHtml(
        "![a](pixel.png)\n![b](../logo.png)\n![c](/blog/pixel.png?size=2#x)\n![d](https://example.com/remote.png)\n![e](missing.png)",
        '/blog/second',
    );

    expect($html)
        ->toContain("src=\"/blog/pixel.png?timestamp={$pixel}\"")
        ->toContain("src=\"/logo.png?timestamp={$logo}\"")
        ->toContain("src=\"/blog/pixel.png?size=2&amp;timestamp={$pixel}#x\"")
        ->toContain('src="https://example.com/remote.png"')
        ->toContain('src="missing.png"');
});

it('versions images in rendered pages', function () {
    $pixel = filemtime(fixtureRoot() . '/content/blog/pixel.png');

    expect(body(get('/blog/second')))->toContain("<img src=\"/blog/pixel.png?timestamp={$pixel}\" alt=\"Pixel\" />");
});

it('renders <style> tags but still filters <script>', function () {
    $html = $this->markdown->toHtml("<style>\n.box { color: red; }\n</style>\n\n# Hi\n\n<script>alert(1)</script>");

    expect($html)
        ->toContain("<style>\n.box { color: red; }\n</style>")
        ->toContain('&lt;script>')
        ->not->toContain('<script>');
});

it('renders footnotes', function () {
    $html = $this->markdown->toHtml("Some text[^1].\n\n[^1]: A footnote.");

    expect($html)
        ->toContain('<sup id="fnref:1"><a class="footnote-ref" href="#fn:1" role="doc-noteref">1</a></sup>')
        ->toContain('<div class="footnotes" role="doc-endnotes">')
        ->toContain('<li class="footnote" id="fn:1" role="doc-endnote">')
        ->toContain('A footnote.');
});
