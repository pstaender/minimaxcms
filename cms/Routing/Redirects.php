<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Routing;

use JsonException;
use RuntimeException;
use Zeitpulse\MiniMaxCMS\Content\UrlPath;

/**
 * Redirects from `redirects.json`, grouped by status code:
 *
 *     { "301": { "old-path": "/new-path" }, "302": { "test": "/a_php_test_page" } }
 *
 * Source paths match with or without leading/trailing slash; targets are used as given.
 */
final class Redirects
{
    private const array STATUSES = [301, 302, 303, 307, 308];

    /** @var array<string, Redirect>|null source path → redirect */
    private ?array $redirects = null;

    public function __construct(private readonly string $file) {}

    public function find(string $urlPath): ?Redirect
    {
        $path = UrlPath::normalize($urlPath);

        return $path === null ? null : $this->all()[$path] ?? null;
    }

    /**
     * @return array<string, Redirect>
     */
    public function all(): array
    {
        return $this->redirects ??= $this->load();
    }

    /**
     * @return array<string, Redirect>
     */
    private function load(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        try {
            $groups = json_decode((string) file_get_contents($this->file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid {$this->file}: {$e->getMessage()}", previous: $e);
        }

        $redirects = [];
        foreach (is_array($groups) ? $groups : [] as $status => $rules) {
            if (!in_array((int) $status, self::STATUSES, true) || !is_array($rules)) {
                throw new RuntimeException("Invalid {$this->file}: unsupported redirect status \"{$status}\"");
            }
            foreach ($rules as $from => $to) {
                $source = UrlPath::normalize((string) $from);
                if ($source === null || !is_string($to) || $to === '') {
                    throw new RuntimeException("Invalid {$this->file}: bad redirect \"{$from}\"");
                }
                $redirects[$source] = new Redirect($to, (int) $status);
            }
        }

        return $redirects;
    }
}
