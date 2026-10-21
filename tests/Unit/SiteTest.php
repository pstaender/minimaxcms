<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\Content\Site;

beforeEach(function () {
    $this->site = new Site(repository());
});

it('lists the listable subfolders, sorted by name', function () {
    expect(array_map(fn ($folder) => $folder->url(), $this->site->folders()))->toBe(['/2025/', '/2026/', '/blog/'])
        ->and(array_map(fn ($folder) => $folder->name(), $this->site->folders('/blog/')))->toBe([]);
});

it('finds pages and folders by URL', function () {
    expect($this->site->page('/about')?->title())->toBe('About the fixture')
        ->and($this->site->page('/docs/')?->title())->toBe('Docs index')
        ->and($this->site->page('/missing'))->toBeNull()
        ->and($this->site->folder('/blog/')?->title())->toBe('Fixture blog');
});

it('is available as $site in templates', function () {
    expect(body(get('/')))->toMatch('~<h2>2026</h2>.*/2026/year_two.*/2026/year_one.*<h2>2025</h2>.*/2025/old_news~s')
        ->not->toContain('<h2>blog</h2>');
});
