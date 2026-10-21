<?php

declare(strict_types=1);

use Dumbo\Dumbo;
use Zeitpulse\MiniMaxCMS\Config\Config;

afterEach(function () {
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');
});

it('loads .env when present', function () {
    $dir = tempSite();
    file_put_contents("{$dir}/.env", "APP_ENV=development\n");

    $config = Config::load($dir);

    expect($config->environment)->toBe(Dumbo::ENV_DEVELOPMENT)
        ->and($config->isDevelopment())->toBeTrue();
});

it('works without .env', function () {
    expect(Config::load(tempSite())->environment)->toBe(Dumbo::ENV_PRODUCTION);
});

it('lets real environment variables win over .env', function () {
    $dir = tempSite();
    file_put_contents("{$dir}/.env", "APP_ENV=development\n");
    $_ENV['APP_ENV'] = Dumbo::ENV_TESTING;

    expect(Config::load($dir)->environment)->toBe(Dumbo::ENV_TESTING);
});

it('resolves the site folders', function () {
    $config = new Config('/srv/site');

    expect($config->contentDir)->toBe('/srv/site/content')
        ->and($config->templateDir)->toBe('/srv/site/templates')
        ->and($config->redirectsFile)->toBe('/srv/site/content/redirects.json');
});
