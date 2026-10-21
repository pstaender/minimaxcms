<?php

declare(strict_types=1);

it('redirects the prefixed URL to the canonical one', function () {
    $response = get('/blog/01_first?x=1');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaderLine('Location'))->toBe('/blog/first?x=1');
});

it('prefers an unprefixed file over prefixed ones', function () {
    $dir = tempSite();
    mkdir("{$dir}/content");
    file_put_contents("{$dir}/content/1_post.txt", 'prefixed');
    file_put_contents("{$dir}/content/post.txt", 'plain');
    file_put_contents("{$dir}/content/2_other.txt", 'two');
    file_put_contents("{$dir}/content/1_other.txt", 'one');

    $app = Zeitpulse\MiniMaxCMS\App::create(new Zeitpulse\MiniMaxCMS\Config\Config(rootDir: $dir));

    expect(body($app->request('/post')))->toBe('plain')
        ->and(body($app->request('/other')))->toBe('one');
});
