<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Media;

use Imagick;

/**
 * An image backed by Imagick.
 */
final class ImagickImage implements Image
{
    private Imagick $image;

    public function __construct(string $file)
    {
        $this->image = new Imagick($file);
        // Animated GIFs: only the first frame.
        $this->image->setIteratorIndex(0);
        $this->image = $this->image->getImage();
        if (method_exists($this->image, 'autoOrient')) {
            $this->image->autoOrient();
        }
        $this->image->stripImage();
    }

    public function width(): int
    {
        return $this->image->getImageWidth();
    }

    public function height(): int
    {
        return $this->image->getImageHeight();
    }

    public function scale(float $factor): void
    {
        $this->image->resizeImage(
            max(1, (int) round($this->width() * $factor)),
            max(1, (int) round($this->height() * $factor)),
            Imagick::FILTER_LANCZOS,
            1,
        );
    }

    public function avif(int $quality): string
    {
        $image = clone $this->image;
        $image->setImageFormat('avif');
        $image->setImageCompressionQuality($quality);

        return $image->getImageBlob();
    }
}
