<?php

declare(strict_types=1);

it('lists a folder with .folder.yml using the folder_index template', function () {
    $response = get('/blog/');

    expect($response->getStatusCode())->toBe(200)
        ->and(body($response))
        ->toContain('<h1>Fixture blog</h1>')
        ->toContain('<a href="/blog/first">First post</a>')
        ->toContain('<a href="/blog/second">Second post</a>');
});

it('sorts by sort prefix, then by name, by default', function () {
    expect(array_map(fn ($page) => $page->url(), repository()->folder('/blog')->pages()))
        ->toBe(['/blog/first', '/blog/second', '/blog/third', '/blog/appendix'])
        ->and(body(get('/blog/')))->toMatch('~/blog/first.*/blog/second.*/blog/third.*/blog/appendix~s');
});

it('sorts by the sort option of .folder.yml', function (string $sort, array $expected) {
    $dir = tempSite();
    mkdir("{$dir}/list");
    file_put_contents("{$dir}/list/.folder.yml", "sort: {$sort}\n");
    file_put_contents("{$dir}/list/10_b.md", "---\ndate: 2026-01-03\n---\n");
    file_put_contents("{$dir}/list/9_c.md", "---\ndate: 2026-01-01\n---\n");
    file_put_contents("{$dir}/list/a.md", "---\ndate: 2026-01-02\n---\n");

    $repository = new Zeitpulse\MiniMaxCMS\Content\ContentRepository($dir, new Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer(new Zeitpulse\MiniMaxCMS\Content\Assets($dir)));

    expect(array_map(fn ($page) => $page->slug(), $repository->folder('/list')->pages()))->toBe($expected);
})->with([
    'name' => ['name', ['c', 'b', 'a']],
    'name descending' => ['-name', ['a', 'b', 'c']],
    'date' => ['date', ['c', 'a', 'b']],
    'date descending' => ['-date', ['b', 'a', 'c']],
]);

it('rejects an unknown sort option', function () {
    $dir = tempSite();
    mkdir("{$dir}/list");
    file_put_contents("{$dir}/list/.folder.yml", "sort: random\n");
    $repository = new Zeitpulse\MiniMaxCMS\Content\ContentRepository($dir, new Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer(new Zeitpulse\MiniMaxCMS\Content\Assets($dir)));

    expect(fn () => $repository->folder('/list')->pages())->toThrow(RuntimeException::class, 'Invalid sort');
});

it('applies the folder filter (unquoted globs in .folder.yml work)', function () {
    $folder = repository()->folder('/blog');

    expect($folder->filter())->toBe('*.md')
        ->and(array_map(fn ($page) => $page->url(), $folder->pages()))->toBe(['/blog/first', '/blog/second', '/blog/third', '/blog/appendix'])
        ->and(body(get('/blog/')))->not->toContain('notes');
});

it('uses the default filter without one in .folder.yml', function () {
    $dir = tempSite();
    mkdir("{$dir}/list");
    file_put_contents("{$dir}/list/.folder.yml", "title: List\n");
    foreach (['a.md', 'b.txt', 'c.php', 'd.html', 'e.json', 'index.md'] as $name) {
        file_put_contents("{$dir}/list/{$name}", '');
    }

    $assets = new Zeitpulse\MiniMaxCMS\Content\Assets($dir);
    $repository = new Zeitpulse\MiniMaxCMS\Content\ContentRepository($dir, new Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer($assets));
    $slugs = array_map(fn ($page) => $page->slug(), $repository->folder('/list')->pages());
    sort($slugs);

    expect($slugs)->toBe(['a', 'b', 'c', 'd']);
});

it('redirects the folder URL without trailing slash', function () {
    expect(get('/blog')->getStatusCode())->toBe(302)
        ->and(get('/blog')->getHeaderLine('Location'))->toBe('/blog/');
});
