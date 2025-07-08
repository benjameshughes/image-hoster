<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class FileSizeLimitException extends Exception
{
    public function __construct(
        public readonly int $fileSize,
        public readonly int $maxSize,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = "File size {$this->formatBytes($fileSize)} exceeds maximum allowed size of {$this->formatBytes($maxSize)}";
        parent::__construct($message, $code, $previous);
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2) . ' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }
}