<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class WordPressApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $endpoint = null,
        public readonly ?array $response = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function authenticationFailed(string $url): self
    {
        return new self(
            message: "Failed to authenticate with WordPress site: {$url}",
            endpoint: $url
        );
    }

    public static function connectionFailed(string $url, string $reason): self
    {
        return new self(
            message: "Failed to connect to WordPress site: {$url}. Reason: {$reason}",
            endpoint: $url
        );
    }

    public static function invalidResponse(string $endpoint, array $response): self
    {
        return new self(
            message: "Invalid response from WordPress API endpoint: {$endpoint}",
            endpoint: $endpoint,
            response: $response
        );
    }

    public static function mediaNotFound(string $mediaId): self
    {
        return new self(
            message: "Media item not found: {$mediaId}"
        );
    }

    public static function downloadFailed(string $url, string $reason): self
    {
        return new self(
            message: "Failed to download media from: {$url}. Reason: {$reason}",
            endpoint: $url
        );
    }

    public static function rateLimitExceeded(string $endpoint): self
    {
        return new self(
            message: "Rate limit exceeded for endpoint: {$endpoint}",
            endpoint: $endpoint,
            code: 429
        );
    }
}