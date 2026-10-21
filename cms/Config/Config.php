<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Config;

use Closure;
use Dotenv\Dotenv;
use Dumbo\Dumbo;
use RuntimeException;

/**
 * Site-wide settings, immutable once created.
 *
 * Built from `config/config.php`, overridden by `config/config.<host>.php`.
 * Paths may be relative to the root folder.
 */
final readonly class Config
{
    /** Keys that map to typed properties; all other keys are available via `get()`. */
    private const array SETTINGS = [
        'siteName',
        'tagline',
        'language',
        'environment',
        'publicDir',
        'contentDir',
        'templateDir',
        'redirectsFile',
    ];

    public string $publicDir;
    public string $contentDir;
    public string $templateDir;
    public string $redirectsFile;

    /**
     * @param array<string, mixed> $values every (resolved) config value, including the typed ones
     */
    public function __construct(
        public string $rootDir,
        public string $siteName = '',
        public string $tagline = '',
        public string $language = 'en',
        public string $environment = Dumbo::ENV_PRODUCTION,
        ?string $publicDir = null,
        ?string $contentDir = null,
        ?string $templateDir = null,
        ?string $redirectsFile = null,
        private array $values = [],
    ) {
        $this->publicDir = $this->path($publicDir ?? 'public');
        $this->contentDir = $this->path($contentDir ?? 'content');
        $this->templateDir = $this->path($templateDir ?? 'templates');
        $this->redirectsFile = $this->path($redirectsFile ?? "{$this->contentDir}/redirects.json");
    }

    /**
     * Load `<rootDir>/.env` (if present), then the config files: `config/config.php`
     * and, if it exists, `config/config.<host>.php`, whose keys win. Keys missing in
     * both get their defaults (`environment` from `APP_ENV`, the standard folders).
     * Closures are called after merging, so only the winning ones run.
     * Real environment variables win over values from `.env`.
     */
    public static function load(string $rootDir, ?string $host = null): self
    {
        if (is_file("{$rootDir}/.env")) {
            Dotenv::createImmutable($rootDir)->load();
        }

        $values = array_replace(self::defaults(), self::read("{$rootDir}/config/config.php"));
        $host = self::normalizeHost($host ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null);
        if ($host !== null) {
            $values = array_replace($values, self::read("{$rootDir}/config/config.{$host}.php"));
        }

        return self::fromArray($rootDir, array_map(
            static fn (mixed $value): mixed => $value instanceof Closure ? $value() : $value,
            $values,
        ));
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(string $rootDir, array $values): self
    {
        return new self($rootDir, ...array_intersect_key($values, array_flip(self::SETTINGS)), values: $values);
    }

    /**
     * Any config value, e.g. `$config->get('debug', false)`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isDevelopment(): bool
    {
        return $this->environment === Dumbo::ENV_DEVELOPMENT;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'environment' => static fn (): string => (string) env('APP_ENV', Dumbo::ENV_PRODUCTION),
            'publicDir' => 'public',
            'contentDir' => 'content',
            'templateDir' => 'templates',
        ];
    }

    private function path(string $path): string
    {
        return str_starts_with($path, '/') ? $path : "{$this->rootDir}/{$path}";
    }

    /**
     * @return array<string, mixed>
     */
    private static function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $values = (static fn (): mixed => require $file)();
        if (!is_array($values)) {
            throw new RuntimeException("{$file} must return an array");
        }

        return $values;
    }

    /**
     * `Example.com:8000` → `example.com`; anything that is not a plain host name → null,
     * so the host can't reach files outside `config/`.
     */
    private static function normalizeHost(mixed $host): ?string
    {
        if (!is_string($host)) {
            return null;
        }
        $host = strtolower(preg_replace('/:\d+$/', '', $host));

        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*$/', $host) === 1 ? $host : null;
    }
}
