<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Filesystem\FilesystemManager;
use League\Flysystem\Filesystem;

class ProgressTrackingFilesystemManager extends FilesystemManager
{
    private $progressCallback = null;

    /**
     * Set progress callback for uploads
     */
    public function setProgressCallback($callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Create a custom S3 driver with progress tracking
     */
    public function createProgressTrackingS3Driver(array $config): Filesystem
    {
        $s3Config = $this->formatS3Config($config);
        $client = new S3Client($s3Config);
        
        $adapter = new ProgressTrackingS3Adapter(
            $client,
            $config['bucket'],
            $config['prefix'] ?? '',
            $config['options'] ?? []
        );

        if ($this->progressCallback) {
            $adapter->setProgressCallback($this->progressCallback);
        }

        return new Filesystem($adapter, $config);
    }

    /**
     * Format S3 configuration
     */
    protected function formatS3Config(array $config): array
    {
        $s3Config = [
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'region' => $config['region'],
            'version' => $config['version'] ?? 'latest',
            'ua_append' => 'Laravel/' . app()->version(),
        ];

        if (isset($config['endpoint'])) {
            $s3Config['endpoint'] = $config['endpoint'];
        }

        if (isset($config['use_path_style_endpoint'])) {
            $s3Config['use_path_style_endpoint'] = $config['use_path_style_endpoint'];
        }

        return $s3Config;
    }

    /**
     * Get a progress-tracking filesystem instance
     */
    public function progressDisk(string $name, $progressCallback = null): Filesystem
    {
        if ($progressCallback) {
            $this->setProgressCallback($progressCallback);
        }

        $config = $this->app['config']["filesystems.disks.{$name}"];
        
        // Only use progress tracking for S3-compatible drivers
        if (($config['driver'] ?? '') === 's3') {
            return $this->createProgressTrackingS3Driver($config);
        }

        // Fall back to regular disk for non-S3 drivers
        return $this->disk($name);
    }
}