<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\WebpEncoder;

class ImageProcessingService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Process an uploaded image: create thumbnail and compressed version
     */
    public function processImage(Image $image, ?int $compressionQuality = null): array
    {
        $results = [];
        
        if (!$image->exists()) {
            throw new \Exception('Original image file not found');
        }

        try {
            // Get the original image content
            $originalContent = Storage::disk($image->disk->value)->get($image->path);
            $processedImage = $this->manager->read($originalContent);
            
            // Create thumbnail
            $thumbnailResult = $this->createThumbnail($image, $processedImage);
            if ($thumbnailResult) {
                $results['thumbnail'] = $thumbnailResult;
            }
            
            // Create compressed version
            $compressedResult = $this->createCompressedVersion($image, $processedImage);
            if ($compressedResult) {
                $results['compressed'] = $compressedResult;
            }
            
            return $results;
            
        } catch (\Exception $e) {
            \Log::error('Image processing failed', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create thumbnail version of the image
     */
    private function createThumbnail(Image $image, ImageInterface $processedImage): ?array
    {
        try {
            $thumbnailSizes = $this->getThumbnailSizes();
            
            foreach ($thumbnailSizes as $size => $dimensions) {
                $thumbnail = clone $processedImage;
                
                // Resize maintaining aspect ratio
                $thumbnail->scale(
                    width: $dimensions['width'],
                    height: $dimensions['height']
                );
                
                // Generate thumbnail filename
                $pathInfo = pathinfo($image->path);
                $thumbnailFilename = $pathInfo['filename'] . '_thumb_' . $size . '.' . $pathInfo['extension'];
                $thumbnailPath = $pathInfo['dirname'] . '/' . $thumbnailFilename;
                
                // Save thumbnail
                $encoder = $this->getOptimalEncoder($image->mime_type, 85);
                $thumbnailData = $thumbnail->encode($encoder);
                
                Storage::disk($image->disk->value)->put($thumbnailPath, $thumbnailData);
                
                // Update image record with thumbnail info (using default size)
                if ($size === 'medium') {
                    $image->update([
                        'thumbnail_path' => $thumbnailPath,
                        'thumbnail_width' => $thumbnail->width(),
                        'thumbnail_height' => $thumbnail->height(),
                    ]);
                }
                
                return [
                    'path' => $thumbnailPath,
                    'width' => $thumbnail->width(),
                    'height' => $thumbnail->height(),
                    'size' => strlen($thumbnailData),
                ];
            }
            
        } catch (\Exception $e) {
            \Log::error('Thumbnail creation failed', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
        
        return null;
    }

    /**
     * Create compressed version of the image
     */
    private function createCompressedVersion(Image $image, ImageInterface $processedImage): ?array
    {
        try {
            $compressed = clone $processedImage;
            
            // Use provided quality or determine based on file size
            $compressionQuality = $compressionQuality ?? $this->getCompressionQuality($image->size);
            $maxDimensions = $this->getMaxDimensions($image->size);
            
            // Resize if necessary
            if ($compressed->width() > $maxDimensions['width'] || $compressed->height() > $maxDimensions['height']) {
                $compressed->scale(
                    width: $maxDimensions['width'],
                    height: $maxDimensions['height']
                );
            }
            
            // Generate compressed filename
            $pathInfo = pathinfo($image->path);
            $compressedFilename = $pathInfo['filename'] . '_compressed.' . $pathInfo['extension'];
            $compressedPath = $pathInfo['dirname'] . '/' . $compressedFilename;
            
            // Save compressed version
            $encoder = $this->getOptimalEncoder($image->mime_type, $compressionQuality);
            $compressedData = $compressed->encode($encoder);
            
            Storage::disk($image->disk->value)->put($compressedPath, $compressedData);
            
            // Update image record with compressed info
            $image->update([
                'compressed_path' => $compressedPath,
                'compressed_size' => strlen($compressedData),
            ]);
            
            return [
                'path' => $compressedPath,
                'size' => strlen($compressedData),
                'quality' => $compressionQuality,
                'dimensions' => [
                    'width' => $compressed->width(),
                    'height' => $compressed->height()
                ]
            ];
            
        } catch (\Exception $e) {
            \Log::error('Compression failed', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get thumbnail sizes configuration
     */
    private function getThumbnailSizes(): array
    {
        return [
            'small' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 600, 'height' => 600],
        ];
    }

    /**
     * Get compression quality based on original file size
     */
    private function getCompressionQuality(int $fileSize): int
    {
        return match (true) {
            $fileSize > 5 * 1024 * 1024 => 65,  // > 5MB: aggressive compression
            $fileSize > 2 * 1024 * 1024 => 75,  // > 2MB: medium compression
            $fileSize > 1 * 1024 * 1024 => 85,  // > 1MB: light compression
            default => 90,                       // < 1MB: minimal compression
        };
    }

    /**
     * Get available compression quality presets
     */
    public function getCompressionPresets(): array
    {
        return [
            95 => ['name' => 'Highest Quality', 'description' => 'Minimal compression, largest file'],
            85 => ['name' => 'High Quality', 'description' => 'Light compression, good balance'],
            75 => ['name' => 'Good Quality', 'description' => 'Medium compression, smaller file'],
            65 => ['name' => 'Web Quality', 'description' => 'Higher compression, web optimized'],
            50 => ['name' => 'Maximum Compression', 'description' => 'Aggressive compression, smallest file'],
        ];
    }

    /**
     * Get maximum dimensions for compressed version
     */
    private function getMaxDimensions(int $fileSize): array
    {
        return match (true) {
            $fileSize > 5 * 1024 * 1024 => ['width' => 1920, 'height' => 1080],  // > 5MB: 1080p max
            $fileSize > 2 * 1024 * 1024 => ['width' => 2560, 'height' => 1440],  // > 2MB: 1440p max
            default => ['width' => 3840, 'height' => 2160],                       // < 2MB: 4K max
        };
    }

    /**
     * Get optimal encoder based on mime type and quality
     */
    private function getOptimalEncoder(string $mimeType, int $quality = 85): \Intervention\Image\Interfaces\EncoderInterface
    {
        return match ($mimeType) {
            'image/png' => new PngEncoder(),
            'image/gif' => new GifEncoder(),
            'image/webp' => new WebpEncoder(quality: $quality),
            default => new JpegEncoder(quality: $quality),
        };
    }

    /**
     * Clean up processed files for an image
     */
    public function cleanupProcessedFiles(Image $image): void
    {
        try {
            if ($image->thumbnail_path && Storage::disk($image->disk->value)->exists($image->thumbnail_path)) {
                Storage::disk($image->disk->value)->delete($image->thumbnail_path);
            }
            
            if ($image->compressed_path && Storage::disk($image->disk->value)->exists($image->compressed_path)) {
                Storage::disk($image->disk->value)->delete($image->compressed_path);
            }
            
            // Clear related thumbnail files
            $pathInfo = pathinfo($image->path);
            $thumbnailPattern = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb_*';
            
            // Get all files matching the pattern
            $files = Storage::disk($image->disk->value)->files($pathInfo['dirname']);
            foreach ($files as $file) {
                if (str_contains($file, $pathInfo['filename'] . '_thumb_')) {
                    Storage::disk($image->disk->value)->delete($file);
                }
            }
            
        } catch (\Exception $e) {
            \Log::error('Cleanup failed', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Reprocess an existing image
     */
    public function reprocessImage(Image $image, ?int $compressionQuality = null): array
    {
        // Clean up existing processed files
        $this->cleanupProcessedFiles($image);
        
        // Reset processed file paths
        $image->update([
            'thumbnail_path' => null,
            'compressed_path' => null,
            'thumbnail_width' => null,
            'thumbnail_height' => null,
            'compressed_size' => null,
        ]);
        
        // Process again
        return $this->processImage($image, $compressionQuality);
    }

    /**
     * Create multiple compression levels for comparison
     */
    public function createCompressionLevels(Image $image): array
    {
        if (!$image->exists()) {
            throw new \Exception('Original image file not found');
        }

        try {
            $originalContent = Storage::disk($image->disk->value)->get($image->path);
            $processedImage = $this->manager->read($originalContent);
            
            $levels = [];
            $qualities = [95, 85, 75, 65, 50];
            
            foreach ($qualities as $quality) {
                $compressed = clone $processedImage;
                
                // Generate filename for this quality level
                $pathInfo = pathinfo($image->path);
                $compressedFilename = $pathInfo['filename'] . '_q' . $quality . '.' . $pathInfo['extension'];
                $compressedPath = $pathInfo['dirname'] . '/' . $compressedFilename;
                
                // Save compressed version
                $encoder = $this->getOptimalEncoder($image->mime_type, $quality);
                $compressedData = $compressed->encode($encoder);
                
                Storage::disk($image->disk->value)->put($compressedPath, $compressedData);
                
                $levels[$quality] = [
                    'path' => $compressedPath,
                    'size' => strlen($compressedData),
                    'quality' => $quality,
                    'url' => $image->is_public 
                        ? Storage::disk($image->disk->value)->url($compressedPath)
                        : Storage::disk($image->disk->value)->temporaryUrl($compressedPath, now()->addHour()),
                    'compression_ratio' => round((1 - strlen($compressedData) / $image->size) * 100, 1),
                ];
            }
            
            return $levels;
            
        } catch (\Exception $e) {
            \Log::error('Compression levels creation failed', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Apply specific compression quality to image
     */
    public function applyCompression(Image $image, int $quality): array
    {
        if (!$image->exists()) {
            throw new \Exception('Original image file not found');
        }

        try {
            $originalContent = Storage::disk($image->disk->value)->get($image->path);
            $processedImage = $this->manager->read($originalContent);
            
            $compressed = clone $processedImage;
            
            // Determine max dimensions for this quality
            $maxDimensions = $this->getMaxDimensions($image->size);
            
            // Resize if necessary
            if ($compressed->width() > $maxDimensions['width'] || $compressed->height() > $maxDimensions['height']) {
                $compressed->scale(
                    width: $maxDimensions['width'],
                    height: $maxDimensions['height']
                );
            }
            
            // Generate compressed filename
            $pathInfo = pathinfo($image->path);
            $compressedFilename = $pathInfo['filename'] . '_compressed.' . $pathInfo['extension'];
            $compressedPath = $pathInfo['dirname'] . '/' . $compressedFilename;
            
            // Clean up existing compressed file
            if ($image->compressed_path && Storage::disk($image->disk->value)->exists($image->compressed_path)) {
                Storage::disk($image->disk->value)->delete($image->compressed_path);
            }
            
            // Save compressed version
            $encoder = $this->getOptimalEncoder($image->mime_type, $quality);
            $compressedData = $compressed->encode($encoder);
            
            Storage::disk($image->disk->value)->put($compressedPath, $compressedData);
            
            // Update image record
            $image->update([
                'compressed_path' => $compressedPath,
                'compressed_size' => strlen($compressedData),
            ]);
            
            return [
                'path' => $compressedPath,
                'size' => strlen($compressedData),
                'quality' => $quality,
                'compression_ratio' => round((1 - strlen($compressedData) / $image->size) * 100, 1),
                'url' => $image->is_public 
                    ? Storage::disk($image->disk->value)->url($compressedPath)
                    : Storage::disk($image->disk->value)->temporaryUrl($compressedPath, now()->addHour()),
            ];
            
        } catch (\Exception $e) {
            \Log::error('Compression application failed', [
                'image_id' => $image->id,
                'quality' => $quality,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get processing statistics
     */
    public function getProcessingStats(): array
    {
        $totalImages = Image::count();
        $withThumbnails = Image::whereNotNull('thumbnail_path')->count();
        $withCompressed = Image::whereNotNull('compressed_path')->count();
        
        $compressionStats = Image::whereNotNull('compressed_size')
            ->selectRaw('AVG((size - compressed_size) / size * 100) as avg_compression')
            ->first();
        
        return [
            'total_images' => $totalImages,
            'with_thumbnails' => $withThumbnails,
            'with_compressed' => $withCompressed,
            'thumbnail_coverage' => $totalImages > 0 ? round($withThumbnails / $totalImages * 100, 1) : 0,
            'compression_coverage' => $totalImages > 0 ? round($withCompressed / $totalImages * 100, 1) : 0,
            'avg_compression_ratio' => $compressionStats->avg_compression ? round($compressionStats->avg_compression, 1) : 0,
        ];
    }
}