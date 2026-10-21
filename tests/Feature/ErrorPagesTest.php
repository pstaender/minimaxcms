<?php

declare(strict_types=1);

use Dumbo\Dumbo;

// The error handler logs every exception; keep the test output clean.
beforeEach(fn () => ini_set('error_log', '/dev/null'));
afterEach(fn () => ini_restore('error_log'));

it('renders content/404.php for unknown URLs', function () {
    $response = get('/does-not-exist');

    expect($response->getStatusCode())->toBe(404)
        ->and(body($response))->toContain('<p>Fixture 404 (404)</p>');
});

it('renders content/500.php when rendering fails', function () {
    $response = get('/broken');

    expect($response->getStatusCode())->toBe(500)
        ->and(body($response))->toContain('<p>Fixture 500 (500)</p>')
        ->not->toContain('Template not found');
});

it('shows the error message on the 500 page in development', function () {
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
