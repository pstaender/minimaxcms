<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Routing;

final readonly class Redirect
{
    public function __construct(
        public string $target,
        public int $status,
    ) {}
}
