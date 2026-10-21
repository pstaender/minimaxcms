<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\Content\FileType;

it('reads front matter', function () {
    $page = repository()->page('/blog/first');

    expect($page->title())->toBe('First post')
        ->and($page->template())->toBe('post')
        ->and($page->meta('template'))->toBe('post')
        ->and($page->meta('missing', 'fallback'))->toBe('fallback')
        ->and($page->date()->format('Y-m-d'))->toBe('2026-01-01')
        ->and($page->url())->toBe('/blog/first')
        ->and($page->slug())->toBe('first')
        ->and($page->order())->toBe(1)
        ->and($page->type)->toBe(FileType::Markdown);
});

it('strips the sort prefix from slug and URL', function (string $path, string $file, ?int $order) {
    $page = repository()->page($path);

    expect(basename($page->file))->toBe($file)
        ->and($page->url())->toBe($path)
        ->and($page->order())->toBe($order);
})->with([
    ['/blog/first', '01_first.md', 1],
    ['/blog/second', '2_second.md', 2],
    ['/blog/third', '003_third.md', 3],
    ['/blog/appendix', 'appendix.md', null],
]);

it('falls back to the first heading as title', function () {
    expect(repository()->page('/about')->title())->toBe('About the fixture')
        ->and(repository()->page('/hello')->title())->toBeNull();
});

it('separates front matter from content', function () {
    $content = repository()->page('/blog/first')->content();

    expect($content->raw())->toBe("The first post.\n")
        ->and($content->md())->toBe("<p>The first post.</p>\n")
        ->and((string) $content)->toBe("<p>The first post.</p>\n");
});

it('outputs the best default for the file type when printed', function (string $path, string $expected) {
    expect((string) repository()->page($path)->content())->toBe($expected);
})->with([
    'markdown → md()' => ['/blog/first', "<p>The first post.</p>\n"],
    'text → esc()' => ['/templated', "Text with &lt;b&gt;tags&lt;/b&gt; &amp; more\n"],
    'html → raw()' => ['/styled', "<p>HTML inside a template</p>\n"],
]);

it('returns the unprocessed body with raw() and escapes with esc()', function () {
    $content = repository()->page('/templated')->content();

    expect($content->raw())->toBe("Text with <b>tags</b> & more\n")
        ->and($content->esc())->toBe("Text with &lt;b&gt;tags&lt;/b&gt; &amp; more\n")
        ->and(repository()->page('/about')->content()->raw())->toStartWith("# About the fixture\n");
});

it('exposes the URL via url()', function () {
    expect(repository()->page('/blog/first')->url())->toBe('/blog/first')
        ->and(repository()->folder('/blog')->url())->toBe('/blog/');
});

it('picks templates by file type', function (string $path, ?string $template, string $contentType) {
    $page = repository()->page($path);

    expect($page->template())->toBe($template)
        ->and($page->contentType())->toBe($contentType);
})->with([
    'markdown default' => ['/about', 'default', 'text/html'],
    'php' => ['/hello', null, 'text/html'],
    'txt' => ['/plain', null, 'text/plain'],
    'raw html' => ['/raw', null, 'text/html'],
    'html with template' => ['/styled', 'default', 'text/html'],
]);

it('gives folder index pages the folder URL', function () {
    expect(repository()->index('/docs')->url())->toBe('/docs/')
        ->and(repository()->index('/')->url())->toBe('/');
});
