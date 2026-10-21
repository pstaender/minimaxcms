<?php

declare(strict_types=1);

$pixel = fn (): string => fixtureRoot() . '/content/blog/pixel.png';

it('serves images from the content folder', function () use ($pixel) {
    $response = get('/blog/pixel.png');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toBe('image/png')
        ->and($response->getHeaderLine('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getHeaderLine('Cache-Control'))->toBe('public, max-age=3600')
        ->and($response->getHeaderLine('Last-Modified'))->toBe(gmdate('D, d M Y H:i:s', filemtime($pixel())) . ' GMT')
        ->and(body($response))->toBe(file_get_contents($pixel()));
});

it('caches versioned (?timestamp=…) assets forever', function () {
    expect(get('/blog/pixel.png?timestamp=123')->getHeaderLine('Cache-Control'))
        ->toBe('public, max-age=31536000, immutable');
});

it('answers conditional requests with 304', function () use ($pixel) {
    $etag = get('/blog/pixel.png')->getHeaderLine('ETag');

    expect(get('/blog/pixel.png', ['If-None-Match' => $etag])->getStatusCode())->toBe(304)
        ->and(get('/blog/pixel.png', ['If-None-Match' => '"other"'])->getStatusCode())->toBe(200)
        ->and(get('/blog/pixel.png', ['If-Modified-Since' => gmdate('D, d M Y H:i:s', filemtime($pixel())) . ' GMT'])->getStatusCode())->toBe(304);
});

it('never serves content sources as assets', function () {
    expect(get('/hello.php')->getStatusCode())->toBe(404)
        ->and(get('/plain.txt')->getStatusCode())->toBe(404)
        ->and(get('/raw.html')->getStatusCode())->toBe(404)
        ->and(get('/hello.md')->getStatusCode())->toBe(404);
});
