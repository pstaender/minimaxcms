<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\View\View;

it('renders partials with the variables of the calling template', function () {
    $html = (view())->renderPage(repository()->page('/about'));

    expect($html)->toStartWith("<!doctype html>\n<title>About the fixture | ")
        ->toEndWith("</body>\n");
});

it('lets partial() override variables', function () {
    $html = (view())->renderPage(repository()->page('/hello'));

    expect($html)->toContain('Hello PHP from /hello');
});

it('versions files in public/ with static_file_url()', function () {
    $mtime = filemtime(fixtureRoot() . '/public/assets/app.css');

    expect(body(get('/about')))->toContain("<link rel=\"stylesheet\" href=\"/assets/app.css?timestamp={$mtime}\">");
});

it('leaves unknown or unsafe paths unchanged (but escaped) in static_file_url()', function () {
    expect(body(get('/static')))->toBe("<p>/assets/missing.css</p>\n<p>/../composer.json</p>\n<p>/assets/a&amp;b&quot;c.css</p>\n");
});

it('only allows static_file_url() inside templates', function () {
    expect(fn () => static_file_url('/assets/app.css'))->toThrow(LogicException::class);
});

it('only allows partial() inside templates', function () {
    expect(fn () => partial('head'))->toThrow(LogicException::class);
});

it('rejects template names that leave the templates folder', function () {
    expect(fn () => (view())->render('../content/hello'))->toThrow(InvalidArgumentException::class);
});

it('escapes with esc()', function () {
    expect(esc('<a href="x">\'&'))->toBe('&lt;a href=&quot;x&quot;&gt;&apos;&amp;');
});
