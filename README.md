# MiniMax CMS

A flat file, markdown first, minimal CMS for PHP.

- No database and no admin UI: every file in `content/` is a page, written in Markdown, PHP, HTML or plain text
- Plain PHP templates, styled with [minimax.css](https://www.npmjs.com/package/minimaxcss)
- Content images converted to AVIF on the fly
- PHP 8.3+, routed with [Dumbo](https://github.com/notrab/dumbo)

## Getting started

Create a new site from [Packagist](https://packagist.org/packages/zeitpulse/minimaxcms):

```sh
composer create-project zeitpulse/minimaxcms my-site
cd my-site
cp .env.example .env          # optional
composer start                # dev server with APP_ENV=development
```

Open http://localhost:8000, then edit `content/index.md`.

### Configuration

Settings live in `config/`:

- `config/config.php`: the defaults for every host.
- `config/config.<host>.php` (optional): overrides for one host, e.g. `config/config.localhost.php`. The host comes from
  the request, without its port.

Each file returns an associative array. The host file replaces single keys of the default file. A value can be a
closure; it is called once both files are merged, after `.env` has been loaded, so only the winning closure runs:

```php
'siteName' => fn () => trim(env('SITE_NAME', 'my site')),
```

`env()` reads a variable from the real environment or the optional `.env` file in the project root; real environment
variables take precedence over the file.

| Key             | Default                       | Description                                         |
|-----------------|-------------------------------|-----------------------------------------------------|
| `siteName`      | `''`                          | Shown in the header and page titles                 |
| `tagline`       | `''`                          | Shown below the site name                           |
| `language`      | `en`                          | `lang` attribute of the page                        |
| `environment`   | `APP_ENV`, else `production`  | `development` shows error messages on the 500 page. |
| `debug`         | `false`                       | `true` shows a detailed error page with stack trace |
| `publicDir`     | `public`                      | Web root (`static_file_url()` looks up files here)  |
| `contentDir`    | `content`                     | Content folder                                      |
| `templateDir`   | `templates`                   | Template folder                                     |
| `redirectsFile` | `<contentDir>/redirects.json` | Redirects                                           |

Keys missing from both files get these defaults. Relative paths are relative to the project root. Any other key is
available in templates via `$config->get('key')`.

### Deployment

Point the web server's document root at `public/` and send every request that doesn't match an existing file to
`public/index.php` (for Apache, `public/.htaccess` already does this). Run `composer install --no-dev` on the server,
and make sure the web server can write to `public/media/images/` for optimized images.

## Project layout

| Path         | Purpose                                                                                                   |
|--------------|-----------------------------------------------------------------------------------------------------------|
| `public/`    | Web root: `index.php` front controller, `assets/` (static CSS, JS, images) and `media/` (optimized images) |
| `config/`    | Site settings, per host                                                                                    |
| `content/`   | The site's pages, folders and media                                                                        |
| `templates/` | PHP templates and `partials/`                                                                              |
| `cms/`       | The CMS itself (namespace `Zeitpulse\MiniMaxCMS`)                                                          |
| `tests/`     | Pest tests and their fixture site                                                                          |

## Writing content

Each file in `content/` becomes a URL without its extension:

| File                                | URL                   |
|-------------------------------------|-----------------------|
| `content/index.md`                  | `/`                   |
| `content/about.md`                  | `/about`              |
| `content/2026/my_post.md`           | `/2026/my_post`       |
| `content/2026/01_my_post.md`        | `/2026/my_post`       |
| `content/2026/02_my_other_post.php` | `/2026/my_other_post` |
| `content/2026/thoughts/index.md`    | `/2026/thoughts/`     |

Supported types, checked in this order: `.md`, `.php`, `.html`, `.txt`.

File names can start with a number prefix that controls sorting, such as `1_`, `01_` or `001_`. The prefix isn't part of
the URL:

- If both `my_post.md` and `01_my_post.md` exist, the file without a prefix wins.
- The prefixed URL (`/2026/01_my_post`) redirects permanently to `/2026/my_post`.

- **Markdown (`.md`)**
    - Written in GitHub flavoured Markdown: tables, task lists, strikethrough, autolinks and footnotes (`text[^1]` …
      `[^1]: note`) all work.
    - Inline HTML is allowed, including `<style>` tags for page-specific CSS. `<script>`, `<iframe>` and similar tags
      are escaped.
    - A file can start with optional YAML front matter.
    - Pages are rendered with the template named in `template`, or `templates/default.php` if none is set.
- **PHP (`.php`)**
    - Runs as is.
    - `$page`, `$config`, `$site`, `partial()` and `esc()` are available.
- **HTML (`.html`) and text (`.txt`)**
    - Output as is (text as `text/plain`).
    - Both can have front matter. They only use a template if `template` is set.

Example front matter:

```markdown
---
template: blog
title: Hello world
date: 2026-10-10
---

# My first post

![A photo](photo.jpg)
```

### Markdown source

You can also get the Markdown source of a `.md` page, with the front matter removed, in two ways:

- Add `.md` to the URL: `/2026/my_post.md`. For a folder's index page, use `/2026/thoughts/index.md`.
- Request the normal URL with an `Accept: text/markdown` header, as in `curl -H 'Accept: text/markdown' …`.

Browsers still get HTML, because a wildcard in the `Accept` header (`*/*`) doesn't count as asking for Markdown.

### Images and media

You can put images, audio, video, PDFs and fonts next to your content and link them relatively. In Markdown, local
images automatically get `?timestamp=<file mtime>` added to the URL, and they are served with long-lived caching
headers. When you replace a file, the URL changes with it.

In templates, `optimized_content_image('2026/hello.png', maxFileSize: 100, quality: 60)` converts a content image to
AVIF and returns its URL, e.g. `/media/images/7abed….avif`. The file is cached in `public/media/images/`, named after a
SHA-256 of the image, `maxFileSize` and `quality`, so it is only converted once. A changed image or different settings
get a new file.

- `quality` is a value from 0 to 100. `maxFileSize` is a rough target in KB (up to 10% more is accepted). To reach it,
  the quality is lowered (down to 30), then the image is scaled down (to no less than 640 px on its longer side).
- If both are `null` (auto), the quality depends on the resolution: 75 for small images (≤ 0.25 MP), going down to 50
  for large ones (≥ 8 MP), with a target of about 100 KB. WebP and AVIF images that are already about 100 KB or smaller
  are returned unchanged.
- If neither Imagick nor GD can write AVIF, or the file isn't a raster image (SVG, …), the path is returned unchanged.

The URL is not escaped, so wrap it in `esc()`. Start the path with `/`: if the image can't be converted, the path is
returned unchanged and is then used as the URL.

`optimized_content_images($html, …)` does the same for every local `<img>` in rendered HTML. `templates/default.php`
uses it, so images in Markdown pages are served as optimized AVIFs automatically.

#### Example: optimizing images

A single image in a template, e.g. a header image named in the front matter (`image: /blog/header.jpg`):

```php
<?php if ($image = $page->meta('image')): ?>
    <img src="<?= esc(optimized_content_image($image, maxFileSize: 150)) ?>" alt="">
<?php endif; ?>
```

Every image of a Markdown page, as in `templates/default.php`:

```php
<?= partial('head') ?>
<article>
<?= optimized_content_images($page->content()->md()) ?>
</article>
<?= partial('footer') ?>
```

With settings for all images of the page, e.g. smaller files for a gallery:

```php
<?= optimized_content_images($page->content()->md(), maxFileSize: 60) ?>
```

In the Markdown itself nothing changes: `![A photo](photo.jpg)` in `content/blog/index.md` becomes
`<img src="/media/images/c52b….avif" alt="A photo" />`.

### Folders

These rules decide what a folder URL shows:

- A folder URL ends with a slash, for example `/2026/thoughts/`.
- If the folder contains an `index.*` file, the folder URL shows it.
- If there's no index file but a `.folder.yml`, the folder URL lists the folder's pages.
- `/2026/thoughts` redirects to `/2026/thoughts/`, unless a page `content/2026/thoughts.*` exists, which is then served
  instead.

`.folder.yml` options:

```yaml
title: Blog entries 2026     # listing title (default: folder name)
filter: *.md                 # which files to list (default: *.{txt,md,php,html})
template: folder_index       # listing template (default: folder_index)
sort: name                   # name (default), -name, date, -date
```

A `.folder.yml` applies only to its own folder, not to subfolders. The listing contains the matching pages in that
folder, ordered by `sort`:

- `name`: files with a number prefix come first, in number order. The rest follow, sorted by name.
- `date`: sorted by the `date` front matter, or the file's modification time if that's missing.
- A leading `-` reverses the order. For example, `-date` lists the newest first. Index files and subfolders aren't
  listed.

### Error pages

- `content/404.php` is shown for unknown URLs.
- `content/500.php` is shown when something goes wrong. It gets `$status`, plus `$error` in development.
- With `'debug' => true` in the config, errors show a built-in debug page instead of `content/500.php`: the exception,
  its previous exceptions and the stack trace, with the source around each line. Never enable it in production.
- Neither page can be opened directly.

### Redirects

`content/redirects.json` (or the configured `redirectsFile`) groups redirects by status code (301, 302, 303, 307 or
308):

```json
{
  "301": {
    "old-post": "/2026/new-post"
  },
  "302": {
    "test": "/a_php_test_page"
  }
}
```

Source paths match with or without the leading and trailing slash. Targets can be paths or full URLs.

### Hidden files

Files and folders that start with a dot (like `.folder.yml`) are never served. Content sources such as `.md` and `.php`
files are never served as downloads either.

## Templates

Templates are plain PHP files in `templates/`. These variables and helpers are available in them:

| API                                                 | Description                                                                                                             |
|-----------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| `$page->title()`                                    | Front matter `title`, else the first `# heading`, else `null`                                                           |
| `$page->content()`                                  | Best default output: Markdown rendered (`md()`), text escaped (`esc()`), HTML as is (`raw()`)                           |
| `->md()` / `->esc()` / `->raw()`                    | Render as Markdown, HTML-escape, or the unprocessed body (without front matter)                                         |
| `$page->meta('key', $default)`                      | Any front matter value                                                                                                  |
| `$page->date()`, `->slug()`, `->order()`, `->url()` | Date (`DateTimeImmutable`), file name without prefix, sort prefix number, URL                                           |
| `$folder->title()`, `->pages()`, `->url()`          | In folder listings (there, `$page` is the folder too)                                                                   |
| `$site->folders('/')`                               | Listable subfolders (with `.folder.yml`) of a URL, sorted by name; `$folder->name()` is the folder name                 |
| `$site->page('/about')`, `$site->folder('/2026/')`  | Look up any page or folder                                                                                              |
| `$config`                                           | Site settings (`siteName`, `tagline`, `get('key')`, …)                                                                  |
| `partial('head', [...])`                            | Renders `templates/partials/head.php` with the current variables                                                        |
| `esc($value)`                                       | Escapes a value for HTML output; use it for every value you print                                                       |
| `static_file_url('/assets/css/app.css')`            | URL of a file in `public/` with `?timestamp=<mtime>` for cache busting, already HTML-escaped (don't wrap it in `esc()`) |
| `optimized_content_image('2026/hello.png')`         | URL of the content image converted to AVIF and cached in `public/media/images/` (see *Images and media*); not escaped   |
| `optimized_content_images($page->content()->md())`  | The rendered HTML with local `<img>` sources pointing to their optimized AVIFs                                          |
| `dd(...$vars)` / `dump(...$vars)`                   | Debugging: dump variables (and stop); dev only, via symfony/var-dumper                                                  |

```php
<?= partial('head') ?>
<article>
  <?= $page->content() ?>
</article>
<?= partial('footer') ?>
```

## Styling

`templates/partials/head.php` loads [minimax.css](https://www.npmjs.com/package/minimaxcss)
(`minimax-starter.css`) from a CDN, then `public/assets/css/layout.css` for the site's own styles. Customize the look in
`layout.css`, preferably by overriding minimax's custom properties such as `--space` and `--max-readable-width`.
The example site is grayscale and follows the system's light or dark mode: its colors are the custom properties at the
top of `layout.css` (`--text-color`, `--muted-color`, `--line-color`, `--surface-color`, `--background-color`), and the
front page styles are scoped to `.frontpage` (`templates/frontpage.php`).
Markdown pages can also bring their own `<style>` tag.

## Tests

```sh
composer test          # or: vendor/bin/pest
```

The tests use [Pest](https://pestphp.com/) and run against the fixture site in `tests/Fixtures/site`, so changes to
`content/` never break them.
