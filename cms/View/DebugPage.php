<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\View;

use Throwable;
use Zeitpulse\MiniMaxCMS\Helper\Html;

/**
 * The 500 page shown when `debug` is enabled: the exception (and its previous ones)
 * with the source around each line of the stack trace. Needs no JS.
 */
final readonly class DebugPage
{
    private const int CONTEXT_LINES = 7;

    public function __construct(private string $rootDir) {}

    public function render(Throwable $error, string $method, string $url): string
    {
        $exceptions = '';
        for ($current = $error; $current !== null; $current = $current->getPrevious()) {
            $exceptions .= $this->exception($current, $current !== $error);
        }

        $title = Html::esc($error::class);
        $request = Html::esc("{$method} {$url}");
        $php = Html::esc(PHP_VERSION);

        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>500 · {$title}</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/minimaxcss@latest/minimax.css" />
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/minimaxcss@latest/minimax-alternate-fonts.css" />
                <style>
                    .debug-meta { opacity: .7; }
                    .debug-source { overflow-x: auto; padding: 0; }
                    .debug-source code { display: block; padding: .5rem 0; }
                    .debug-source span { display: block; padding: 0 .75rem; white-space: pre; }
                    .debug-source span[data-line]::before { content: attr(data-line); display: inline-block; width: 4ch; margin-right: 1ch; text-align: right; opacity: .5; }
                    .debug-source .debug-current { background: color-mix(in srgb, red 20%, transparent); }
                    .debug-trace summary { cursor: pointer; overflow-wrap: anywhere; }
                    .debug-trace details { margin-bottom: var(--space); }
                    .debug-trace small { opacity: .7; }
                </style>
            </head>
            <body>
            <main>
                <p class="debug-meta">500 Internal Server Error · {$request} · PHP {$php}</p>
                {$exceptions}
            </main>
            </body>
            </html>
            HTML;
    }

    private function exception(Throwable $error, bool $isPrevious): string
    {
        $heading = $isPrevious ? '<h2>Caused by ' : '<h1>';
        $closing = $isPrevious ? '</h2>' : '</h1>';
        $frames = [['file' => $error->getFile(), 'line' => $error->getLine()], ...$error->getTrace()];

        $trace = '';
        foreach ($frames as $index => $frame) {
            $trace .= $this->frame($frame, open: $index === 0);
        }

        return $heading . Html::esc($error::class) . $closing
            . '<p><strong>' . nl2br(Html::esc($error->getMessage())) . '</strong></p>'
            . '<p class="debug-meta">' . Html::esc($this->location($error->getFile(), $error->getLine())) . '</p>'
            . '<section class="debug-trace">' . $trace . '</section>';
    }

    /**
     * @param array{file?: string, line?: int, class?: string, type?: string, function?: string} $frame
     */
    private function frame(array $frame, bool $open): string
    {
        $file = $frame['file'] ?? null;
        $line = $frame['line'] ?? null;
        $function = isset($frame['function'])
            ? ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()'
            : '';
        $location = $file !== null ? $this->location($file, $line ?? 0) : '[internal]';
        $source = $file !== null && $line !== null ? $this->source($file, $line) : '';

        $summary = $function !== ''
            ? '<code>' . Html::esc($function) . '</code> <small>' . Html::esc($location) . '</small>'
            : '<code>' . Html::esc($location) . '</code>';

        if ($source === '') {
            return "<p>{$summary}</p>";
        }

        return '<details' . ($open ? ' open' : '') . "><summary>{$summary}</summary><section>{$source}</section></details>";
    }

    private function source(string $file, int $line): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $start = max(1, $line - self::CONTEXT_LINES);
        $end = min(count($lines), $line + self::CONTEXT_LINES);

        $html = '';
        for ($number = $start; $number <= $end; $number++) {
            $class = $number === $line ? ' class="debug-current"' : '';
            $html .= "<span data-line=\"{$number}\"{$class}>" . Html::esc($lines[$number - 1]) . '</span>';
        }

        return $html === '' ? '' : "<pre class=\"debug-source\"><code>{$html}</code></pre>";
    }

    private function location(string $file, int $line): string
    {
        $root = rtrim($this->rootDir, '/') . '/';
        $file = str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;

        return "{$file}:{$line}";
    }
}
