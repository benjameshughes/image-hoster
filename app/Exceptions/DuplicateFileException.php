<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DuplicateFileException extends Exception
{
    public function __construct(
        public readonly string $fileHash,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = "Duplicate file detected with hash: {$fileHash}";
        parent::__construct($message, $code, $previous);
    }
}