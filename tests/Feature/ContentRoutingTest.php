<?php

declare(strict_types=1);

it('renders the front page from content/index.md with its template', function () {
    $response = get('/');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toStartWith('text/html')
        ->and(body($response))
        ->toContain('<main class="home"><h1>Fixture home</h1>')
        ->toContain('Welcome <em>home</em>.');
});

it('maps a path to the matching markdown file', function () {
    expect(body(get('/about')))
        ->toContain('<main class="default">')
        ->toContain('<h1>About the fixture</h1>');
});

it('runs PHP content files with $page and partial() available', function () {
    $response = get('/hello');

    expect($response->getStatusCode())->toBe(200)
        ->and(body($response))
        ->toContain('<p class="greeting">Hello PHP from /hello</p>')
        ->toContain('<p>Rendered from hello.php</p>');
});

it('serves .txt files as plain text', function () {
    $response = get('/plain');

    expect($response->getHeaderLine('Content-Type'))->toStartWith('text/plain')
        ->and(body($response))->toBe("Just plain text <b>not html</b>\n");
});

it('serves .html files as they are, unless they set a template', function () {
    expect(body(get('/raw')))->toBe("<!doctype html><p>Raw HTML page</p>\n")
        ->and(body(get('/styled')))
        ->toContain('<main class="default"><p>HTML inside a template</p>');
});

it('escapes plain text pages rendered with a template', function () {
    expect(body(get('/templated')))->toContain('<main class="default">Text with &lt;b&gt;tags&lt;/b&gt; &amp; more');
});

it('serves the index file of a folder on its trailing slash URL', function () {
    $response = get('/docs/');

    expect($response->getStatusCode())->toBe(200)
        ->and(body($response))->toContain('<h1>Docs index</h1>');
});

it('temporarily redirects a folder URL without trailing slash', function () {
    $response = get('/docs?x=1');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeaderLine('Location'))->toBe('/docs/?x=1');
});

it('prefers a page over a folder of the same name', function () {
    expect(get('/section')->getStatusCode())->toBe(200)
        ->and(body(get('/section')))->toContain('<h1>Section page</h1>')
        ->and(body(get('/section/')))->toContain('<h1>Section folder index</h1>');
});

it('redirects a page URL with trailing slash to the canonical URL', function () {
    $response = get('/about/');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaderLine('Location'))->toBe('/about');
});

it('redirects /index URLs to their folder', function () {
    expect(get('/docs/index')->getHeaderLine('Location'))->toBe('/docs/')
        ->and(get('/index')->getHeaderLine('Location'))->toBe('/');
});

it('does not expose hidden files, folder settings or the error pages', function (string $path) {
    expect(get($path)->getStatusCode())->toBe(404);
})->with([
    '/.hidden',
    '/blog/.folder.yml',
    '/404',
    '/500',
    '/../composer.json',
    '/%2e%2e/composer.json',
    '/redirects.json',
]);

it('does not list folders without .folder.yml', function () {
    expect(get('/private/')->getStatusCode())->toBe(404)
        ->and(get('/private')->getStatusCode())->toBe(404)
        ->and(get('/private/secret')->getStatusCode())->toBe(200);
});
