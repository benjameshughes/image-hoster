<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Media;
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
    public function processImage(Media $image, ?int $compressionQuality = null): array
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
    private function createThumbnail(Media $image, ImageInterface $processedImage): ?array
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
    private function createCompressedVersion(Media $image, ImageInterface $processedImage): ?array
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
            ['quality' => 95, 'label' => 'Highest Quality', 'description' => 'Minimal compression, largest file'],
            ['quality' => 85, 'label' => 'High Quality', 'description' => 'Light compression, good balance'],
            ['quality' => 75, 'label' => 'Good Quality', 'description' => 'Medium compression, smaller file'],
            ['quality' => 65, 'label' => 'Web Quality', 'description' => 'Higher compression, web optimized'],
            ['quality' => 50, 'label' => 'Maximum Compression', 'description' => 'Aggressive compression, smallest file'],
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
    public function cleanupProcessedFiles(Media $image): void
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
    public function reprocessImage($image, ?int $compressionQuality = null): array
    {
        if (!$image->exists()) {
            throw new \Exception('Image file not found');
        }

        try {
            // Extract metadata
            $metadata = $this->extractMetadata($image);
            
            // Generate thumbnail
            $thumbnailPath = $this->generateThumbnail($image);
            
            // Update image record with new metadata
            $image->update([
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'thumbnail_path' => $thumbnailPath,
            ]);
            
            return [
                'metadata' => $metadata,
                'thumbnail' => $thumbnailPath,
            ];
            
        } catch (\Exception $e) {
            \Log::error('Image reprocessing failed', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create multiple compression levels for comparison
     */
    public function createCompressionLevels($image): array
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
                
                $size = strlen($compressedData);
                $compressionRatio = round((1 - $size / $image->size) * 100, 1);
                
                $url = null;
                try {
                    $url = $image->is_public 
                        ? Storage::disk($image->disk->value)->url($compressedPath)
                        : Storage::disk($image->disk->value)->temporaryUrl($compressedPath, now()->addHour());
                } catch (\Exception $e) {
                    // Some drivers (like local in testing) don't support temporary URLs
                    $url = '/storage/' . $compressedPath;
                }
                
                $levels[] = [
                    'quality' => $quality,
                    'path' => $compressedPath,
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'compression_ratio' => $compressionRatio,
                    'url' => $url,
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
    public function applyCompression($image, int $quality): array
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
            
            $newSize = strlen($compressedData);
            $compressionRatio = round((1 - $newSize / $image->size) * 100, 1);
            
            // Update image record
            $image->update([
                'compressed_path' => $compressedPath,
                'compressed_size' => $newSize,
            ]);
            
            return [
                'compression_ratio' => $compressionRatio,
                'original_size' => $image->size,
                'new_size' => $newSize,
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

    /**
     * Extract metadata from image file
     */
    public function extractMetadata($image): array
    {
        if (!$image->exists()) {
            return [];
        }

        try {
            $content = Storage::disk($image->disk->value)->get($image->path);
            $processedImage = $this->manager->read($content);
            
            return [
                'width' => $processedImage->width(),
                'height' => $processedImage->height(),
                'mime_type' => $image->mime_type,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate thumbnail for image
     */
    public function generateThumbnail($image): string
    {
        if (!$image->exists()) {
            throw new \Exception('Image file not found');
        }

        $content = Storage::disk($image->disk->value)->get($image->path);
        $processedImage = $this->manager->read($content);
        
        // Resize to thumbnail
        $processedImage->scaleDown(300, 300);
        
        // Generate thumbnail path
        $pathInfo = pathinfo($image->path);
        $thumbnailPath = $pathInfo['dirname'] . '/thumbs/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
        
        // Encode and store
        $encoder = $this->getOptimalEncoder($image->mime_type, 85);
        $encodedImage = $processedImage->encode($encoder);
        
        Storage::disk($image->disk->value)->put($thumbnailPath, $encodedImage->toString());
        
        return $thumbnailPath;
    }

    /**
     * Compress image
     */
    public function compressImage($image, int $quality): array
    {
        if (!$image->exists()) {
            throw new \Exception('Image file not found');
        }

        $originalSize = $image->size;
        $content = Storage::disk($image->disk->value)->get($image->path);
        $processedImage = $this->manager->read($content);
        
        // Generate compressed path
        $pathInfo = pathinfo($image->path);
        $compressedPath = $pathInfo['dirname'] . '/compressed/' . $pathInfo['filename'] . '_compressed.' . $pathInfo['extension'];
        
        // Encode with specified quality
        $encoder = $this->getOptimalEncoder($image->mime_type, $quality);
        $encodedImage = $processedImage->encode($encoder);
        
        Storage::disk($image->disk->value)->put($compressedPath, $encodedImage->toString());
        
        $compressedSize = Storage::disk($image->disk->value)->size($compressedPath);
        $compressionRatio = round((($originalSize - $compressedSize) / $originalSize) * 100, 2);
        
        return [
            'compressed_path' => $compressedPath,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'compression_ratio' => $compressionRatio,
        ];
    }

    /**
     * Check if image format is supported
     */
    public function isImageFormatSupported(string $mimeType): bool
    {
        $supportedFormats = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/tiff',
        ];

        return in_array($mimeType, $supportedFormats);
    }

    /**
     * Optimize image orientation
     */
    public function optimizeOrientation($image): array
    {
        try {
            if (!$image->exists()) {
                throw new \Exception('Image file not found');
            }

            $content = Storage::disk($image->disk->value)->get($image->path);
            $processedImage = $this->manager->read($content);
            
            $originalWidth = $processedImage->width();
            $originalHeight = $processedImage->height();
            
            // Auto-orient based on EXIF data if available
            // This is a simplified version - real implementation would check EXIF orientation
            
            return [
                'rotated' => false, // Would be true if rotation was applied
                'original_dimensions' => ['width' => $originalWidth, 'height' => $originalHeight],
                'new_dimensions' => ['width' => $originalWidth, 'height' => $originalHeight],
            ];
        } catch (\Exception $e) {
            // If file doesn't exist or there's an error, return default values
            return [
                'rotated' => false,
                'original_dimensions' => ['width' => 0, 'height' => 0],
                'new_dimensions' => ['width' => 0, 'height' => 0],
            ];
        }
    }

    /**
     * Clean up temporary files
     */
    public function cleanupTemporaryFiles(array $files, ?string $disk = null): void
    {
        // If no disk specified, try to determine from files or use default
        if (!$disk) {
            $disk = 'local';
        }
        
        foreach ($files as $file) {
            if (isset($file['path'])) {
                try {
                    if (Storage::disk($disk)->exists($file['path'])) {
                        Storage::disk($disk)->delete($file['path']);
                    }
                } catch (\Exception $e) {
                    // Try other common disks if the default fails
                    $fallbackDisks = ['local', 'spaces', 's3'];
                    foreach ($fallbackDisks as $fallbackDisk) {
                        if ($fallbackDisk !== $disk) {
                            try {
                                if (Storage::disk($fallbackDisk)->exists($file['path'])) {
                                    Storage::disk($fallbackDisk)->delete($file['path']);
                                    break;
                                }
                            } catch (\Exception $fallbackException) {
                                // Continue to next disk
                                continue;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        return match (true) {
            $size >= 1024 ** 3 => round($size / (1024 ** 3), $precision) . ' GB',
            $size >= 1024 ** 2 => round($size / (1024 ** 2), $precision) . ' MB',
            $size >= 1024 => round($size / 1024, $precision) . ' KB',
            default => $size . ' B',
        };
    }
}