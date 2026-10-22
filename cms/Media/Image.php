<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Media;

/**
 * A decoded image that can be scaled and encoded as AVIF.
 */
interface Image
{
    public function width(): int;

    public function height(): int;

    public function scale(float $factor): void;

    /**
     * The image as AVIF, with `$quality` from 0 to 100.
     */
    public function avif(int $quality): string;
}
