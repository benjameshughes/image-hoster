<?php

namespace App\Services;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;

class ProgressTrackingS3Adapter implements FilesystemAdapter
{
    private AwsS3V3Adapter $adapter;
    private S3Client $client;
    private string $bucket;
    private $progressCallback = null;

    public function __construct(S3Client $client, string $bucket, string $prefix = '', array $options = [])
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->adapter = new AwsS3V3Adapter($client, $bucket, $prefix, null, null, $options);
    }

    /**
     * Set progress callback for upload tracking
     */
    public function setProgressCallback($callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Write with progress tracking
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $size = strlen($contents);
        
        if ($this->progressCallback && $size > 0) {
            $this->writeWithProgress($path, $contents, $config, $size);
        } else {
            $this->adapter->write($path, $contents, $config);
        }
    }

    /**
     * Write stream with progress tracking
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        if ($this->progressCallback && is_resource($contents)) {
            $this->writeStreamWithProgress($path, $contents, $config);
        } else {
            $this->adapter->writeStream($path, $contents, $config);
        }
    }

    /**
     * Write with progress tracking using AWS SDK directly
     */
    private function writeWithProgress(string $path, string $contents, Config $config, int $totalSize): void
    {
        $uploaded = 0;
        
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $contents,
                'ContentLength' => $totalSize,
                'Progress' => function ($uploadedBytes) use (&$uploaded, $totalSize) {
                    $uploaded = $uploadedBytes;
                    if ($this->progressCallback) {
                        call_user_func($this->progressCallback, [
                            'uploaded' => $uploadedBytes,
                            'total' => $totalSize,
                            'progress' => $totalSize > 0 ? ($uploadedBytes / $totalSize) * 100 : 0,
                        ]);
                    }
                },
            ];

            if ($config->get('ACL')) {
                $params['ACL'] = $config->get('ACL');
            }

            if ($config->get('ContentType')) {
                $params['ContentType'] = $config->get('ContentType');
            }

            $result = $this->client->putObject($params);
        } catch (\Exception $e) {
            if ($this->progressCallback) {
                call_user_func($this->progressCallback, [
                    'error' => $e->getMessage(),
                    'uploaded' => $uploaded,
                    'total' => $totalSize,
                ]);
            }
            throw $e;
        }
    }

    /**
     * Write stream with progress tracking
     */
    private function writeStreamWithProgress(string $path, $contents, Config $config): void
    {
        // Get stream size
        $stats = fstat($contents);
        $totalSize = $stats['size'] ?? 0;
        $uploaded = 0;

        try {
            $uploaderConfig = [
                'bucket' => $this->bucket,
                'key' => $path,
                'before_upload' => function (\Aws\Command $command) use (&$uploaded, $totalSize) {
                    if ($this->progressCallback) {
                        $uploaded += $command['ContentLength'] ?? 0;
                        call_user_func($this->progressCallback, [
                            'uploaded' => $uploaded,
                            'total' => $totalSize,
                            'progress' => $totalSize > 0 ? ($uploaded / $totalSize) * 100 : 0,
                        ]);
                    }
                },
            ];

            if ($config->get('ACL')) {
                $uploaderConfig['ACL'] = $config->get('ACL');
            }

            // Use multipart upload for streams with progress tracking
            $uploader = new \Aws\S3\MultipartUploader($this->client, $contents, $uploaderConfig);
            $result = $uploader->upload();
        } catch (\Exception $e) {
            if ($this->progressCallback) {
                call_user_func($this->progressCallback, [
                    'error' => $e->getMessage(),
                    'uploaded' => $uploaded,
                    'total' => $totalSize,
                ]);
            }
            throw $e;
        }
    }

    // Delegate all other methods to the base adapter
    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists($path);
    }

    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->adapter->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->adapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->adapter->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->adapter->setVisibility($path, $visibility);
    }

    public function visibility(string $path): \League\Flysystem\FileAttributes
    {
        return $this->adapter->visibility($path);
    }

    public function mimeType(string $path): \League\Flysystem\FileAttributes
    {
        return $this->adapter->mimeType($path);
    }

    public function lastModified(string $path): \League\Flysystem\FileAttributes
    {
        return $this->adapter->lastModified($path);
    }

    public function fileSize(string $path): \League\Flysystem\FileAttributes
    {
        return $this->adapter->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->adapter->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->adapter->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
    }
}