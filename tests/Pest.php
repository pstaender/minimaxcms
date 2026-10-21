<?php

declare(strict_types=1);

use Dumbo\Dumbo;
use Psr\Http\Message\ResponseInterface;
use Zeitpulse\MiniMaxCMS\App;
use Zeitpulse\MiniMaxCMS\Config\Config;
use Zeitpulse\MiniMaxCMS\Content\Assets;
use Zeitpulse\MiniMaxCMS\Content\ContentRepository;
use Zeitpulse\MiniMaxCMS\Content\Site;
use Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer;
use Zeitpulse\MiniMaxCMS\View\View;

/*
 * Tests run against the fixture site in tests/Fixtures/site, never against content/.
 */

function fixtureRoot(): string
{
    return __DIR__ . '/Fixtures/site';
}

function fixtureConfig(string $environment = Dumbo::ENV_TESTING): Config
{
    return new Config(rootDir: fixtureRoot(), environment: $environment);
}

function app(string $environment = Dumbo::ENV_TESTING): Dumbo
{
    return App::create(fixtureConfig($environment));
}

/**
 * @param array<string, string> $headers
 */
function get(string $path, array $headers = [], string $environment = Dumbo::ENV_TESTING): ResponseInterface
{
    return app($environment)->request($path, 'GET', $headers);
}

function body(ResponseInterface $response): string
{
    return (string) $response->getBody();
}

function view(): View
{
    return new View(fixtureConfig(), new Site(repository()));
}

function repository(): ContentRepository
{
    $assets = new Assets(fixtureRoot() . '/content');

    return new ContentRepository(fixtureRoot() . '/content', new MarkdownRenderer($assets));
}

/**
 * An empty, temporary site root that is removed after the current test.
 */
function tempSite(): string
{
    $dir = sys_get_temp_dir() . '/minimaxcms-' . bin2hex(random_bytes(6));
    mkdir($dir);
    register_shutdown_function(removeDir(...), $dir);

    return $dir;
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    ) as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($dir);
}
