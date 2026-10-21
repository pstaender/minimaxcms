<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Helper;

final class Html
{
    /**
     * Escape a value for safe output in HTML text and attributes.
     */
    public static function esc(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
