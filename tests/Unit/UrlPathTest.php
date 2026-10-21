<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\Content\UrlPath;

it('normalizes paths', function (string $path, ?string $expected) {
    expect(UrlPath::normalize($path))->toBe($expected);
})->with([
    ['/', '/'],
    ['', '/'],
    ['/a//b/', '/a/b'],
    ['/a/./b/../c', '/a/c'],
    ['/../etc/passwd', null],
    ['/.env', null],
    ['/a/.folder.yml', null],
    ["/a\0b", null],
    ['/a\\b', null],
]);

it('returns the directory of a URL', function (string $url, string $expected) {
    expect(UrlPath::directory($url))->toBe($expected);
})->with([
    ['/2026/post', '/2026/'],
    ['/2026/thoughts/', '/2026/thoughts/'],
    ['/post', '/'],
    ['/', '/'],
]);
