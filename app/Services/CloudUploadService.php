<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CloudUploadProgressUpdated;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;

class CloudUploadService
{
    private const MULTIPART_THRESHOLD = 100 * 1024 * 1024; // 100MB
    private const PROGRESS_FREQUENCY = 1024 * 1024; // 1MB

    /**
     * Upload file to cloud storage with progress tracking
     */
    public function uploadWithProgress(
        UploadedFile|SplFileInfo $file,
        string $disk,
        string $path,
        string $filename,
        int $userId,
        string $sessionId,
        string $visibility = 'public'
    ): array {
        $diskConfig = config("filesystems.disks.{$disk}");
        
        if (!$diskConfig || $diskConfig['driver'] !== 's3') {
            throw new \InvalidArgumentException("Disk '{$disk}' is not a valid S3 disk");
        }

        $s3Client = $this->createS3Client($diskConfig);
        $fullPath = $path . '/' . $filename;
        $fileSize = $file->getSize();

        // Progress tracking variables
        $lastReportedBytes = 0;
        $startTime = microtime(true);

        // Progress callback
        $progressCallback = function ($downloadedBytes, $totalBytes) use (
            $userId,
            $sessionId,
            $filename,
            &$lastReportedBytes,
            $startTime
        ) {
            if ($totalBytes === 0) {
                return;
            }

            // Only report progress at certain intervals to avoid spam
            if ($downloadedBytes - $lastReportedBytes < self::PROGRESS_FREQUENCY && $downloadedBytes < $totalBytes) {
                return;
            }

            $lastReportedBytes = $downloadedBytes;
            $percentage = ($downloadedBytes / $totalBytes) * 100;
            
            // Calculate speed and ETA
            $elapsed = microtime(true) - $startTime;
            $speed = $elapsed > 0 ? $downloadedBytes / $elapsed : 0;
            $eta = $speed > 0 ? (int) (($totalBytes - $downloadedBytes) / $speed) : null;

            // Don't dispatch events during testing
            if (app()->environment() !== 'testing') {
                CloudUploadProgressUpdated::dispatch(
                    $userId,
                    $sessionId,
                    $filename,
                    $downloadedBytes,
                    $totalBytes,
                    $percentage,
                    $speed,
                    $eta
                );
            }
        };

        try {
            if ($fileSize > self::MULTIPART_THRESHOLD) {
                return $this->uploadMultipart($s3Client, $file, $diskConfig['bucket'], $fullPath, $visibility, $progressCallback);
            } else {
                return $this->uploadSingle($s3Client, $file, $diskConfig['bucket'], $fullPath, $visibility, $progressCallback);
            }
        } catch (\Exception $e) {
            throw new \Exception("Cloud upload failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Upload single file (under 100MB)
     */
    private function uploadSingle(
        S3Client $s3Client,
        UploadedFile|SplFileInfo $file,
        string $bucket,
        string $key,
        string $visibility,
        callable $progressCallback
    ): array {
        $fileStream = fopen($file->getRealPath(), 'r');
        
        if (!$fileStream) {
            throw new \Exception('Cannot open file for reading');
        }

        try {
            $result = $s3Client->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => $fileStream,
                'ContentType' => $file->getMimeType(),
                'ACL' => $visibility === 'public' ? 'public-read' : 'private',
                '@http' => [
                    'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) use ($progressCallback) {
                        $progressCallback($uploadedBytes, $uploadTotal);
                    }
                ]
            ]);

            return [
                'path' => $key,
                'url' => $result['ObjectURL'] ?? $this->generateUrl($s3Client, $bucket, $key, $visibility),
                'etag' => $result['ETag'] ?? null,
            ];
        } finally {
            fclose($fileStream);
        }
    }

    /**
     * Upload large file using multipart upload (over 100MB)
     */
    private function uploadMultipart(
        S3Client $s3Client,
        UploadedFile|SplFileInfo $file,
        string $bucket,
        string $key,
        string $visibility,
        callable $progressCallback
    ): array {
        $uploader = new \Aws\S3\MultipartUploader($s3Client, $file->getRealPath(), [
            'bucket' => $bucket,
            'key' => $key,
            'ACL' => $visibility === 'public' ? 'public-read' : 'private',
            'before_initiate' => function (\Aws\Command $command) use ($file) {
                $command['ContentType'] = $file->getMimeType();
            },
            'before_upload' => function (\Aws\Command $command) use ($progressCallback) {
                // Track progress for multipart uploads
                if (isset($command['@http']['progress'])) {
                    $originalProgress = $command['@http']['progress'];
                    $command['@http']['progress'] = function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) use ($progressCallback, $originalProgress) {
                        $progressCallback($uploadedBytes, $uploadTotal);
                        if ($originalProgress) {
                            $originalProgress($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes);
                        }
                    };
                } else {
                    $command['@http']['progress'] = function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) use ($progressCallback) {
                        $progressCallback($uploadedBytes, $uploadTotal);
                    };
                }
            }
        ]);

        try {
            $result = $uploader->upload();
            
            return [
                'path' => $key,
                'url' => $result['ObjectURL'] ?? $this->generateUrl($s3Client, $bucket, $key, $visibility),
                'etag' => $result['ETag'] ?? null,
            ];
        } catch (\Aws\Exception\MultipartUploadException $e) {
            // Cleanup incomplete multipart upload
            $uploader->getState()->abort();
            throw $e;
        }
    }

    /**
     * Create S3 client from disk configuration
     */
    private function createS3Client(array $diskConfig): S3Client
    {
        $config = [
            'credentials' => [
                'key' => $diskConfig['key'],
                'secret' => $diskConfig['secret'],
            ],
            'region' => $diskConfig['region'],
            'version' => $diskConfig['version'] ?? 'latest',
        ];

        // Add endpoint for services like Cloudflare R2
        if (isset($diskConfig['endpoint'])) {
            $config['endpoint'] = $diskConfig['endpoint'];
        }

        if (isset($diskConfig['use_path_style_endpoint'])) {
            $config['use_path_style_endpoint'] = $diskConfig['use_path_style_endpoint'];
        }

        return new S3Client($config);
    }

    /**
     * Generate URL for uploaded file
     */
    private function generateUrl(S3Client $s3Client, string $bucket, string $key, string $visibility): string
    {
        if ($visibility === 'public') {
            return $s3Client->getObjectUrl($bucket, $key);
        } else {
            // Generate temporary URL for private files
            $command = $s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $key
            ]);
            return (string) $s3Client->createPresignedRequest($command, '+1 hour')->getUri();
        }
    }
}