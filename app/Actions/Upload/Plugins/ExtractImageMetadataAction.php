<?php

declare(strict_types=1);

namespace App\Actions\Upload\Plugins;

use App\Actions\Upload\AbstractUploadAction;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;

/**
 * Plugin action to extract metadata from image files
 */
class ExtractImageMetadataAction extends AbstractUploadAction
{
    protected int $priority = 30; // Mid priority - after validation, before processing

    protected array $configurationOptions = [
        'extract_exif' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Extract EXIF data from images',
        ],
        'extract_dimensions' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Extract image dimensions',
        ],
        'extract_color_info' => [
            'type' => 'boolean',
            'default' => false,
            'description' => 'Extract color information (experimental)',
        ],
    ];

    public function getName(): string
    {
        return 'extract_image_metadata';
    }

    public function getDescription(): string
    {
        return 'Extracts metadata, EXIF data, and dimensions from image files';
    }

    public function canHandle(UploadContext $context): bool
    {
        return parent::canHandle($context) 
            && $context->extractMetadata 
            && $context->isImage()
            && $this->isEnabled($context);
    }

    public function execute(UploadContext $context): UploadResult
    {
        $this->log('Starting image metadata extraction', [
            'filename' => $context->getOriginalFilename(),
            'mime_type' => $context->getMimeType(),
        ]);

        $metadata = [];

        try {
            // Extract dimensions
            if ($context->getConfiguration('extract_dimensions', true)) {
                $dimensions = $this->extractDimensions($context);
                if ($dimensions) {
                    $metadata = array_merge($metadata, $dimensions);
                }
            }

            // Extract EXIF data
            if ($context->getConfiguration('extract_exif', true)) {
                $exifData = $this->extractExifData($context);
                if ($exifData) {
                    $metadata['exif'] = $exifData;
                }
            }

            // Extract color information (if enabled)
            if ($context->getConfiguration('extract_color_info', false)) {
                $colorInfo = $this->extractColorInfo($context);
                if ($colorInfo) {
                    $metadata['color_info'] = $colorInfo;
                }
            }

            $this->log('Image metadata extraction completed', [
                'filename' => $context->getOriginalFilename(),
                'extracted_fields' => array_keys($metadata),
            ]);

            return $this->success(
                $context,
                'Image metadata extracted successfully',
                $metadata
            );

        } catch (\Exception $e) {
            $this->logError('Failed to extract image metadata: ' . $e->getMessage());
            
            // Non-critical failure - continue processing
            return $this->success(
                $context,
                'Image metadata extraction failed but continuing: ' . $e->getMessage(),
                ['metadata_extraction_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Extract image dimensions
     */
    private function extractDimensions(UploadContext $context): ?array
    {
        try {
            $imageInfo = getimagesize($context->file->getPathname());
            
            if ($imageInfo === false) {
                return null;
            }

            [$width, $height, $type] = $imageInfo;

            return [
                'width' => $width,
                'height' => $height,
                'type_code' => $type,
                'aspect_ratio' => round($width / $height, 3),
                'megapixels' => round(($width * $height) / 1000000, 2),
            ];

        } catch (\Exception $e) {
            $this->logError('Failed to extract dimensions: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract EXIF data from image
     */
    private function extractExifData(UploadContext $context): ?array
    {
        try {
            if (!function_exists('exif_read_data')) {
                return null;
            }

            // Only try to extract EXIF from JPEG files
            if (!str_contains(strtolower($context->getMimeType()), 'jpeg')) {
                return null;
            }

            $exifData = @exif_read_data($context->file->getPathname());
            
            if ($exifData === false) {
                return null;
            }

            // Clean and filter EXIF data
            return $this->cleanExifData($exifData);

        } catch (\Exception $e) {
            $this->logError('Failed to extract EXIF data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clean and filter EXIF data
     */
    private function cleanExifData(array $exifData): array
    {
        $cleaned = [];

        // Fields we're interested in
        $fieldsOfInterest = [
            'Camera' => 'Make',
            'Model' => 'Model',
            'DateTime' => 'DateTime',
            'Software' => 'Software',
            'Orientation' => 'Orientation',
            'XResolution' => 'XResolution',
            'YResolution' => 'YResolution',
            'ColorSpace' => 'ColorSpace',
            'ExposureTime' => 'ExposureTime',
            'FNumber' => 'FNumber',
            'ISO' => 'ISOSpeedRatings',
            'FocalLength' => 'FocalLength',
            'Flash' => 'Flash',
            'GPS' => null, // Will handle GPS separately
        ];

        foreach ($fieldsOfInterest as $friendlyName => $exifField) {
            if ($exifField && isset($exifData[$exifField])) {
                $value = $exifData[$exifField];
                
                // Convert binary data to readable format
                if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                    continue; // Skip binary data
                }
                
                $cleaned[$friendlyName] = $value;
            }
        }

        // Handle GPS data separately
        if (isset($exifData['GPSLatitude'], $exifData['GPSLongitude'])) {
            $cleaned['GPS'] = [
                'Latitude' => $this->convertGpsCoordinate($exifData['GPSLatitude'], $exifData['GPSLatitudeRef'] ?? 'N'),
                'Longitude' => $this->convertGpsCoordinate($exifData['GPSLongitude'], $exifData['GPSLongitudeRef'] ?? 'E'),
            ];
        }

        return $cleaned;
    }

    /**
     * Convert GPS coordinates to decimal degrees
     */
    private function convertGpsCoordinate(array $coordinate, string $hemisphere): float
    {
        if (count($coordinate) !== 3) {
            return 0.0;
        }

        [$degrees, $minutes, $seconds] = $coordinate;
        
        // Convert fractions to decimals
        $degrees = $this->fractionToDecimal($degrees);
        $minutes = $this->fractionToDecimal($minutes);
        $seconds = $this->fractionToDecimal($seconds);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        // Apply hemisphere
        if (in_array($hemisphere, ['S', 'W'])) {
            $decimal *= -1;
        }

        return round($decimal, 6);
    }

    /**
     * Convert EXIF fraction to decimal
     */
    private function fractionToDecimal(string $fraction): float
    {
        if (str_contains($fraction, '/')) {
            [$numerator, $denominator] = explode('/', $fraction);
            return $denominator != 0 ? $numerator / $denominator : 0;
        }

        return (float) $fraction;
    }

    /**
     * Extract color information (experimental)
     */
    private function extractColorInfo(UploadContext $context): ?array
    {
        try {
            // This is a basic implementation - could be enhanced with more sophisticated color analysis
            $imagePath = $context->file->getPathname();
            
            // Only process smaller images to avoid memory issues
            if ($context->getFileSize() > 5 * 1024 * 1024) { // 5MB limit
                return null;
            }

            $image = imagecreatefromstring(file_get_contents($imagePath));
            if (!$image) {
                return null;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            // Sample colors from a grid to get dominant colors
            $colors = [];
            $sampleSize = min(50, min($width, $height)); // Sample up to 50x50 grid
            
            for ($x = 0; $x < $width; $x += max(1, $width / $sampleSize)) {
                for ($y = 0; $y < $height; $y += max(1, $height / $sampleSize)) {
                    $rgb = imagecolorat($image, (int) $x, (int) $y);
                    $colors[] = [
                        'r' => ($rgb >> 16) & 0xFF,
                        'g' => ($rgb >> 8) & 0xFF,
                        'b' => $rgb & 0xFF,
                    ];
                }
            }

            imagedestroy($image);

            // Calculate average color
            $avgColor = [
                'r' => (int) round(array_sum(array_column($colors, 'r')) / count($colors)),
                'g' => (int) round(array_sum(array_column($colors, 'g')) / count($colors)),
                'b' => (int) round(array_sum(array_column($colors, 'b')) / count($colors)),
            ];

            return [
                'average_color' => $avgColor,
                'hex_color' => sprintf('#%02x%02x%02x', $avgColor['r'], $avgColor['g'], $avgColor['b']),
                'brightness' => round(($avgColor['r'] * 0.299 + $avgColor['g'] * 0.587 + $avgColor['b'] * 0.114) / 255, 3),
                'sample_count' => count($colors),
            ];

        } catch (\Exception $e) {
            $this->logError('Failed to extract color info: ' . $e->getMessage());
            return null;
        }
    }
}