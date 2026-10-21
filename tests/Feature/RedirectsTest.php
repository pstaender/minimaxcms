<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\Routing\Redirects;

it('applies redirects from redirects.json with their status code', function (string $path, int $status, string $location) {
    $response = get($path);

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getHeaderLine('Location'))->toBe($location);
})->with([
    'permanent' => ['/old-about', 301, '/about'],
    'temporary' => ['/temp', 302, '/blog/'],
    'trailing slash' => ['/temp/', 302, '/blog/'],
    'external' => ['/external', 302, 'https://example.com/'],
]);

it('works without a redirects.json', function () {
    expect((new Redirects('/does/not/exist.json'))->all())->toBe([]);
});

it('rejects an unsupported status code', function () {
    $file = tempnam(sys_get_temp_dir(), 'redirects');
    file_put_contents($file, '{"200": {"a": "/b"}}');

    expect(fn () => (new Redirects($file))->all())->toThrow(RuntimeException::class, 'unsupported redirect status');

    unlink($file);
});
