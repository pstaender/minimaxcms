<?php

declare(strict_types=1);

use Dumbo\Dumbo;

it('renders content/404.php for unknown URLs', function () {
    $response = get('/does-not-exist');

    expect($response->getStatusCode())->toBe(404)
        ->and(body($response))->toContain('<p>Fixture 404 (404)</p>');
});

it('renders content/500.php when rendering fails', function () {
    $this->expectErrorLog(); // the error handler logs every exception
    $response = get('/broken');

    expect($response->getStatusCode())->toBe(500)
        ->and(body($response))->toContain('<p>Fixture 500 (500)</p>')
        ->not->toContain('Template not found');
});

it('shows the error message on the 500 page in development', function () {
    $this->expectErrorLog(); // the error handler logs every exception
    expect(body(get('/broken', environment: Dumbo::ENV_DEVELOPMENT)))
        ->toContain('Template not found: templates/does_not_exist.php');
});

it('falls back to plain text without error pages', function () {
    $dir = tempSite();
    mkdir("{$dir}/content");

    $response = Zeitpulse\MiniMaxCMS\App::create(new Zeitpulse\MiniMaxCMS\Config\Config(rootDir: $dir))->request('/nope');

    expect($response->getStatusCode())->toBe(404)
        ->and(body($response))->toBe('404 Not Found');
});

it('shows a debug page with stack trace when debug is enabled', function () {
    $this->expectErrorLog(); // the error handler logs every exception
    $config = Zeitpulse\MiniMaxCMS\Config\Config::fromArray(fixtureRoot(), ['debug' => true]);
    $response = Zeitpulse\MiniMaxCMS\App::create($config)->request('/broken');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getHeaderLine('Content-Type'))->toContain('text/html')
        ->and(body($response))
        ->toContain('minimaxcss@latest/minimax-starter.css')
        ->toContain('RuntimeException')
        ->toContain('Template not found: templates/does_not_exist.php')
        ->toContain('cms/View/View.php:')
        ->toContain('class="debug-current"')
        ->toContain('GET /broken')
        ->not->toContain('Fixture 500');
});

it('escapes the error message on the debug page', function () {
    $page = new Zeitpulse\MiniMaxCMS\View\DebugPage(fixtureRoot());

    expect($page->render(new RuntimeException('<script>x</script>'), 'GET', '/'))
        ->toContain('&lt;script&gt;x&lt;/script&gt;')
        ->not->toContain('<script>x</script>');
});

it('shows previous exceptions on the debug page', function () {
    $page = new Zeitpulse\MiniMaxCMS\View\DebugPage(fixtureRoot());
    $error = new RuntimeException('outer', previous: new LogicException('inner'));

    expect($page->render($error, 'GET', '/'))
        ->toContain('Caused by LogicException')
        ->toContain('inner');
});
