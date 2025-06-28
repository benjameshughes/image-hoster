<?php

namespace App\Exceptions;

use Exception;

abstract class UploadException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class InvalidFileTypeException extends UploadException
{
    public function __construct(string $actualType, array $allowedTypes)
    {
        $message = "Invalid file type '{$actualType}'. Allowed types: ".implode(', ', $allowedTypes);
        parent::__construct($message);
    }
}

class FileSizeLimitException extends UploadException
{
    public function __construct(int $actualSize, int $maxSize)
    {
        $actualMB = number_format($actualSize / 1024 / 1024, 2);
        $maxMB = number_format($maxSize / 1024 / 1024, 2);
        $message = "File size {$actualMB}MB exceeds maximum allowed size of {$maxMB}MB";
        parent::__construct($message);
    }
}

class StorageException extends UploadException
{
    public function __construct(string $operation, string $details = '')
    {
        $message = "Storage operation '{$operation}' failed";
        if ($details) {
            $message .= ": {$details}";
        }
        parent::__construct($message);
    }
}

class DuplicateFileException extends UploadException
{
    public function __construct(string $hash)
    {
        parent::__construct("File with hash '{$hash}' already exists");
    }
}
