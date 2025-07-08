<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class StorageException extends Exception
{
    public function __construct(
        public readonly string $operation,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $fullMessage = "Storage operation '{$operation}' failed: {$message}";
        parent::__construct($fullMessage, $code, $previous);
    }
}