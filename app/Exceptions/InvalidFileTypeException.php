<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidFileTypeException extends Exception
{
    public function __construct(
        string $fileType,
        public readonly array $allowedTypes,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = "Invalid file type '{$fileType}'. Allowed types: " . implode(', ', $allowedTypes);
        parent::__construct($message, $code, $previous);
    }
}