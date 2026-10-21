<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Http;

use Dumbo\Dumbo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dumbo, but without its automatic 301 from `/foo/` to `/foo`.
 *
 * Folder URLs end with a slash (`/2026/thoughts/`), so the trailing slash is removed
 * for routing and kept as the request attribute `trailingSlash` instead.
 */
final class Kernel extends Dumbo
{
    public const string TRAILING_SLASH = 'trailingSlash';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if (strlen($path) > 1 && str_ends_with($path, '/')) {
            $request = $request
                ->withUri($uri->withPath(rtrim($path, '/') ?: '/'))
                ->withAttribute(self::TRAILING_SLASH, true);
        }

        return parent::handle($request);
    }
}
