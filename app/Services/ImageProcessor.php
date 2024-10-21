<?php

namespace App\Services;

use Imagick;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 * Class ImageProcessor
 *
 * Handles image processing operations using ImageMagick and Laravel's Storage.
 */
class ImageProcessor
{
    private ?Imagick $imagick = null;
    private string $originalPath = '';
    private string $workingPath = '';
    private string $disk;

    /**
     * ImageProcessor constructor.
     *
     * @param string $disk Storage disk to use (default: 'public')
     */
    public function __construct(string $disk = 'public')
    {
        $this->disk = $disk;
    }

    /**
     * Load an image and create a working copy.
     *
     * @param string $inputPath Path to the original image
     * @return self
     * @throws \ImagickException
     */
    public function load(string $inputPath, string $filename = null): self
    {
        $this->originalPath = $inputPath;

        if ($filename === null) {
            $filename = Str::uuid() . '.' . pathinfo($inputPath, PATHINFO_EXTENSION);
        }
        $newFilename = Storage::disk('local')->path('temp') . $filename;
        $this->workingPath = $newFilename;

        Storage::disk($this->disk)->copy($inputPath, $this->workingPath);

        $fullPath = Storage::disk($this->disk)->path($this->workingPath);
        $this->imagick = new Imagick($fullPath);

        return $this;
    }

    /**
     * Resize the image based on provided dimensions or default max width.
     *
     * This method resizes the image according to the following rules:
     * - If both width and height are null, it resizes to the default max width if the current width exceeds it.
     * - If only width is null, it calculates the width based on the given height while maintaining aspect ratio.
     * - If only height is null, it calculates the height based on the given width while maintaining aspect ratio.
     * - If both width and height are provided, it resizes to those exact dimensions.
     *
     * @param int|null $width The desired width of the image. If null, it will be calculated based on height.
     * @param int|null $height The desired height of the image. If null, it will be calculated based on width.
     * @param bool $maintainAspectRatio Whether to maintain the aspect ratio during resizing. Defaults to true.
     * @param int $defaultMaxWidth The default maximum width to use if both width and height are null. Defaults to 1920.
     *
     * @return self Returns the current instance for method chaining.
     * @throws \ImagickException
     */
    public function resize(?int $width = null, ?int $height = null, bool $maintainAspectRatio = true, int $defaultMaxWidth = 3840): self
    {
        $currentWidth = $this->imagick->getImageWidth();
        $currentHeight = $this->imagick->getImageHeight();

        if ($width === null && $height === null) {
            // If both are null, use the default max width
            if ($currentWidth > $defaultMaxWidth) {
                $width = $defaultMaxWidth;
                $height = 0; // 0 means maintain aspect ratio
            } else {
                return $this; // No resizing needed
            }
        } elseif ($width === null) {
            // If only width is null, calculate it based on height
            $aspectRatio = $currentWidth / $currentHeight;
            $width = (int)round($height * $aspectRatio);
        } elseif ($height === null) {
            // If only height is null, calculate it based on width
            $aspectRatio = $currentWidth / $currentHeight;
            $height = (int)round($width / $aspectRatio);
        }

        if ($maintainAspectRatio) {
            $this->imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true);
        } else {
            $this->imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        }

        return $this;
    }

    /**
     * Compress the image.
     *
     * @param int $quality Compression quality (1-100)
     * @return self
     * @throws \ImagickException
     */
    public function compress(int $quality): self
    {
        $this->imagick->setImageCompressionQuality($quality);
        return $this;
    }

    /**
     * Crop the image.
     *
     * @param int $width Width of crop
     * @param int $height Height of crop
     * @param int $x X-coordinate of crop start
     * @param int $y Y-coordinate of crop start
     * @return self
     * @throws \ImagickException
     */
    public function crop(int $width, int $height, int $x, int $y): self
    {
        $this->imagick->cropImage($width, $height, $x, $y);
        return $this;
    }

    /**
     * Add a watermark to the image.
     *
     * @param string $watermarkPath Path to watermark image
     * @param float $opacity Opacity of watermark (0-1)
     * @return self
     * @throws \ImagickException
     */
    public function watermark(string $watermarkPath, float $opacity = 0.5): self
    {
        $watermarkFullPath = Storage::disk($this->disk)->path($watermarkPath);
        $watermark = new Imagick($watermarkFullPath);
        $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);
        $this->imagick->compositeImage($watermark, Imagick::COMPOSITE_OVER, 0, 0);
        $watermark->clear();
        return $this;
    }

    /**
     * Convert the image to a different format.
     *
     * @param string $format Target format (e.g., 'webp', 'png')
     * @return self
     * @throws \ImagickException
     */
    public function convert(string $format): self
    {
        $this->imagick->setImageFormat($format);
        return $this;
    }

    /**
     * Optimize the image by resizing (if needed) and compressing.
     *
     * @param int $maxWidth Maximum width before resizing
     * @param int $quality Compression quality (1-100)
     * @return self
     * @throws \ImagickException
     */
    public function optimize(int $maxWidth = 3840, int $quality = 85): self
    {
        $width = $this->imagick->getImageWidth();
        if ($width > $maxWidth) {
            $this->imagick->resizeImage($maxWidth, 0, Imagick::FILTER_LANCZOS, 1);
        }
        $this->imagick->stripImage();
        $this->imagick->setImageCompressionQuality($quality);
        return $this;
    }

    /**
     * Save the processed image.
     *
     * @param string|null $outputPath Path to save the processed image (null to overwrite working copy)
     * @return string Path of the saved image relative to the storage disk
     */
    public function save(?string $outputPath = null): string
    {
        $savePath = $outputPath ?? $this->workingPath;
        $fullSavePath = Storage::disk($this->disk)->path($savePath);

        // Ensure the directory exists
        $directory = dirname($fullSavePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->imagick->writeImage($fullSavePath);
        $this->imagick->clear();

        if ($outputPath !== null && $outputPath !== $this->workingPath) {
            Storage::disk($this->disk)->delete($this->workingPath);
        }

        return $savePath;
    }

    /**
     * Clean up resources when the object is destroyed.
     */
    public function __destruct()
    {
        if ($this->imagick !== null) {
            $this->imagick->clear();
        }
        Storage::disk($this->disk)->delete($this->workingPath);
    }
}