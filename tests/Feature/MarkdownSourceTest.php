<?php

declare(strict_types=1);

it('serves the Markdown source without front matter for .md URLs', function () {
    $response = get('/blog/first.md');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/markdown; charset=utf-8')
        ->and(body($response))->toBe("The first post.\n");
});

it('serves sources of pages without front matter and of index pages', function () {
    expect(body(get('/about.md')))->toStartWith("# About the fixture\n")
        ->and(body(get('/docs/index.md')))->toBe("# Docs index\n")
        ->and(body(get('/index.md')))->toBe("# Fixture home\n\nWelcome *home*.\n");
});

it('also finds sort-prefixed files', function () {
    expect(body(get('/blog/01_first.md')))->toBe("The first post.\n");
});

it('serves the source when Markdown is preferred via Accept', function (string $accept) {
    $response = get('/blog/first', ['Accept' => $accept]);

    expect($response->getHeaderLine('Content-Type'))->toBe('text/markdown; charset=utf-8')
        ->and($response->getHeaderLine('Vary'))->toBe('Accept')
        ->and(body($response))->toBe("The first post.\n");
})->with([
    'text/markdown',
    'text/x-markdown',
    'text/markdown, text/html',
    'text/html;q=0.5, text/markdown',
]);

it('serves HTML to browsers', function (?string $accept) {
    $response = get('/blog/first', $accept === null ? [] : ['Accept' => $accept]);

    expect($response->getHeaderLine('Content-Type'))->toStartWith('text/html')
        ->and($response->getHeaderLine('Vary'))->toBe('Accept');
})->with([
    'no header' => null,
    'browser' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'wildcard' => '*/*',
    'html preferred' => 'text/html, text/markdown;q=0.5',
]);

it('does not serve sources of non-Markdown pages', function () {
    expect(get('/hello.md')->getStatusCode())->toBe(404)
        ->and(get('/hello', ['Accept' => 'text/markdown'])->getHeaderLine('Content-Type'))->toStartWith('text/html')
        ->and(get('/missing.md')->getStatusCode())->toBe(404);
});
