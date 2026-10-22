<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Media;

use Imagick;
use Throwable;

/**
 * Converts content images to AVIF and caches them in `public/media/images/<hash>.avif`.
 *
 * The hash covers the image's SHA-256, `maxFileSize` and `quality`, so a changed image or
 * different settings get a new file (and URL). Without Imagick or GD (with AVIF support)
 * the image is used as is.
 */
final readonly class ImageOptimizer
{
    public const string URL_PATH = '/media/images';

    /** Sources that are converted; anything else (SVG, …) is used as is. */
    private const array CONVERTIBLE = ['avif', 'bmp', 'gif', 'jpeg', 'jpg', 'png', 'webp'];

    /** Target size (KB) in auto mode; also when WebP/AVIF sources are small enough to keep. */
    private const int AUTO_MAX_FILE_SIZE = 100;

    /** Sizes are rough targets: allow this much above `maxFileSize`. */
    private const float SIZE_TOLERANCE = 1.1;

    /** Lowest quality used to reach `maxFileSize`; below that the image is scaled down instead. */
    private const int MIN_QUALITY = 30;

    private const int QUALITY_STEP = 8;

    /** Never scale the longer side below this (px) to reach `maxFileSize`. */
    private const int MIN_DIMENSION = 640;

    public function __construct(
        private string $contentDir,
        private string $publicDir,
    ) {}

    /**
     * URL of the optimized AVIF for a content image (e.g. `2026/hello.png`), or `$file`
     * unchanged if it can't or needn't be converted.
     *
     * @param int|null $maxFileSize roughly, in KB (null: auto)
     * @param int|null $quality 0–100 (null: auto, depending on the resolution)
     */
    public function url(string $file, ?int $maxFileSize = null, ?int $quality = null): string
    {
        $source = $this->sourceFile($file);
        if ($source === null) {
            return $file;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $auto = $maxFileSize === null && $quality === null;
        if ($auto && in_array($extension, ['avif', 'webp'], true)
            && filesize($source) <= self::AUTO_MAX_FILE_SIZE * 1024 * self::SIZE_TOLERANCE) {
            return $file;
        }

        if ($quality !== null) {
            $quality = max(0, min(100, $quality));
        }
        if ($maxFileSize !== null) {
            $maxFileSize = max(1, $maxFileSize);
        }

        $hash = hash('sha256', hash_file('sha256', $source) . "|{$maxFileSize}|{$quality}");
        $url = self::URL_PATH . "/{$hash}.avif";
        $target = $this->publicDir . $url;

        if (is_file($target)) {
            return $url;
        }

        $avif = $this->convert($source, $auto ? self::AUTO_MAX_FILE_SIZE : $maxFileSize, $quality);

        return $avif !== null && $this->write($target, $avif) ? $url : $file;
    }

    /**
     * Point the `src` of every local `<img>` in `$html` (e.g. `/2026/hello.png?timestamp=…`)
     * to the URL `$optimize` returns for its file, e.g. `optimized_content_image(...)`.
     * External images and those whose URL doesn't change stay as they are.
     *
     * @param callable(string): string $optimize
     */
    public static function replaceImages(string $html, callable $optimize): string
    {
        return (string) preg_replace_callback(
            '~(<img\b[^>]*?\ssrc\s*=\s*)(["\'])(.*?)\2~is',
            static function (array $match) use ($optimize): string {
                $src = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5);
                if (!str_starts_with($src, '/') || str_starts_with($src, '//')) {
                    return $match[0];
                }

                $file = rawurldecode((string) parse_url($src, PHP_URL_PATH));
                $url = $optimize($file);

                return $url === $file
                    ? $match[0]
                    : $match[1] . $match[2] . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5) . $match[2];
            },
            $html,
        );
    }

    public static function isSupported(): bool
    {
        return self::imagickSupported() || self::gdSupported();
    }

    private function sourceFile(string $file): ?string
    {
        $path = '/' . ltrim($file, '/');
        if (preg_match('~(^|/)\.\.?(/|$)~', $path) === 1) {
            return null;
        }

        $source = $this->contentDir . $path;
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        return is_file($source) && in_array($extension, self::CONVERTIBLE, true) ? $source : null;
    }

    /**
     * Encode `$source` as AVIF: lower the quality (down to MIN_QUALITY) and then the
     * resolution until it fits `$maxFileSize` (KB).
     */
    private function convert(string $source, ?int $maxFileSize, ?int $quality): ?string
    {
        try {
            $image = self::imagickSupported() ? new ImagickImage($source) : (self::gdSupported() ? GdImageFile::open($source) : null);
            if ($image === null) {
                return null;
            }

            $quality ??= self::autoQuality($image->width() * $image->height());
            $limit = $maxFileSize !== null ? (int) ($maxFileSize * 1024 * self::SIZE_TOLERANCE) : null;

            while (true) {
                $avif = $image->avif($quality);
                if ($limit === null || strlen($avif) <= $limit) {
                    return $avif;
                }

                if ($quality > self::MIN_QUALITY) {
                    $quality = max(self::MIN_QUALITY, $quality - self::QUALITY_STEP);
                } elseif (max($image->width(), $image->height()) * 0.8 >= self::MIN_DIMENSION) {
                    $image->scale(0.8);
                } else {
                    return $avif;
                }
            }
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Small images keep more quality; large ones (where artifacts are less visible
     * per pixel) are compressed more: 75 at ≤ 0.25 MP down to 50 at ≥ 8 MP (log scale).
     */
    private static function autoQuality(int $pixels): int
    {
        [$small, $large] = [250_000, 8_000_000];
        $position = (log(max($small, min($large, $pixels))) - log($small)) / (log($large) - log($small));

        return (int) round(75 - 25 * $position);
    }

    private function write(string $target, string $content): bool
    {
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        // Write to a temporary file first, so a concurrent request never serves a partial file.
        $temporary = "{$target}." . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $content) === false) {
            return false;
        }

        if (!@rename($temporary, $target)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    private static function imagickSupported(): bool
    {
        return extension_loaded('imagick') && Imagick::queryFormats('AVIF') !== [];
    }

    private static function gdSupported(): bool
    {
        return function_exists('imageavif') && (gd_info()['AVIF Support'] ?? false);
    }
}
