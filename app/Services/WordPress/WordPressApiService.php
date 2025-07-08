<?php

declare(strict_types=1);

namespace App\Services\WordPress;

use App\Exceptions\WordPressApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WordPressApiService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout = 30;
    private int $retryTimes = 3;
    private array $headers = [];

    public function __construct(
        string $baseUrl,
        string $username,
        string $password
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        
        $this->setupHeaders();
    }

    public static function make(string $baseUrl, string $username, string $password): self
    {
        return new self($baseUrl, $username, $password);
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function retries(int $times): self
    {
        $this->retryTimes = $times;
        return $this;
    }

    /**
     * Test connection and authentication
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/wp/v2/users/me');
            
            return $response->successful() && 
                   isset($response->json()['id']);
        } catch (WordPressApiException $e) {
            return false;
        }
    }

    /**
     * Get WordPress site information
     */
    public function getSiteInfo(): array
    {
        $response = $this->makeRequest('GET', '/wp/v2');
        
        if (! $response->successful()) {
            throw WordPressApiException::invalidResponse(
                $this->baseUrl . '/wp-json/wp/v2',
                $response->json() ?? []
            );
        }

        return $response->json();
    }

    /**
     * Fetch media library with pagination
     */
    public function fetchMediaLibrary(
        int $page = 1, 
        int $perPage = 100,
        ?string $mediaType = null,
        ?string $after = null
    ): array {
        $params = [
            'page' => $page,
            'per_page' => min($perPage, 100), // WordPress API limit
            'orderby' => 'date',
            'order' => 'asc',
        ];

        if ($mediaType) {
            $params['media_type'] = $mediaType;
        }

        if ($after) {
            $params['after'] = $after;
        }

        $response = $this->makeRequest('GET', '/wp/v2/media', $params);

        if (! $response->successful()) {
            throw WordPressApiException::invalidResponse(
                $this->buildUrl('/wp/v2/media'),
                $response->json() ?? []
            );
        }

        return [
            'media' => $response->json(),
            'total' => (int) $response->header('X-WP-Total'),
            'total_pages' => (int) $response->header('X-WP-TotalPages'),
            'current_page' => $page,
        ];
    }

    /**
     * Get all media items (handles pagination automatically)
     */
    public function getAllMedia(?string $mediaType = null): \Generator
    {
        $page = 1;
        
        do {
            $result = $this->fetchMediaLibrary($page, 100, $mediaType);
            
            foreach ($result['media'] as $mediaItem) {
                yield $this->normalizeMediaData($mediaItem);
            }
            
            $page++;
            
        } while ($page <= $result['total_pages']);
    }

    /**
     * Get specific media item by ID
     */
    public function getMediaItem(string $mediaId): array
    {
        $response = $this->makeRequest('GET', "/wp/v2/media/{$mediaId}");

        if ($response->status() === 404) {
            throw WordPressApiException::mediaNotFound($mediaId);
        }

        if (! $response->successful()) {
            throw WordPressApiException::invalidResponse(
                $this->buildUrl("/wp/v2/media/{$mediaId}"),
                $response->json() ?? []
            );
        }

        return $this->normalizeMediaData($response->json());
    }

    /**
     * Download media file to temporary storage
     */
    public function downloadMediaFile(string $sourceUrl, ?string $filename = null): array
    {
        if (! $filename) {
            $filename = $this->extractFilenameFromUrl($sourceUrl);
        }

        $tempPath = 'temp/wordpress-import/' . Str::uuid() . '_' . $filename;

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryTimes, 1000)
                ->get($sourceUrl);

            if (! $response->successful()) {
                throw WordPressApiException::downloadFailed(
                    $sourceUrl,
                    "HTTP {$response->status()}: {$response->body()}"
                );
            }

            // Store temporarily
            Storage::disk('local')->put($tempPath, $response->body());

            return [
                'temp_path' => $tempPath,
                'filename' => $filename,
                'size' => Storage::disk('local')->size($tempPath),
                'mime_type' => $response->header('content-type'),
                'source_url' => $sourceUrl,
            ];

        } catch (ConnectionException $e) {
            throw WordPressApiException::downloadFailed($sourceUrl, $e->getMessage());
        }
    }

    /**
     * Clean up temporary file
     */
    public function cleanupTempFile(string $tempPath): void
    {
        if (Storage::disk('local')->exists($tempPath)) {
            Storage::disk('local')->delete($tempPath);
        }
    }

    /**
     * Validate WordPress URL structure
     */
    public static function validateWordPressUrl(string $url): bool
    {
        $url = rtrim($url, '/');
        
        // Try to access the REST API discovery endpoint
        try {
            $response = Http::timeout(10)->get($url . '/wp-json/');
            
            return $response->successful() && 
                   is_array($response->json()) &&
                   isset($response->json()['routes']);
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Get media statistics
     */
    public function getMediaStatistics(): array
    {
        $stats = [
            'total' => 0,
            'by_type' => [],
            'by_month' => [],
        ];

        // Get first page to determine total
        $firstPage = $this->fetchMediaLibrary(1, 1);
        $stats['total'] = $firstPage['total'];

        // Get breakdown by media type
        foreach (['image', 'video', 'audio', 'application'] as $type) {
            $typeResult = $this->fetchMediaLibrary(1, 1, $type);
            $stats['by_type'][$type] = $typeResult['total'];
        }

        return $stats;
    }

    /**
     * Make authenticated request to WordPress API
     */
    private function makeRequest(string $method, string $endpoint, array $params = []): Response
    {
        $url = $this->buildUrl($endpoint);

        try {
            $request = Http::withHeaders($this->headers)
                ->withBasicAuth($this->username, $this->password)
                ->timeout($this->timeout)
                ->retry($this->retryTimes, 1000, function ($exception, $request) {
                    // Retry on connection errors and 5xx server errors
                    return $exception instanceof ConnectionException ||
                           ($request->status() >= 500 && $request->status() < 600);
                });

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $params),
                'POST' => $request->post($url, $params),
                'PUT' => $request->put($url, $params),
                'DELETE' => $request->delete($url, $params),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
            };

            // Handle rate limiting
            if ($response->status() === 429) {
                throw WordPressApiException::rateLimitExceeded($endpoint);
            }

            // Handle authentication errors
            if ($response->status() === 401) {
                throw WordPressApiException::authenticationFailed($this->baseUrl);
            }

            return $response;

        } catch (ConnectionException $e) {
            throw WordPressApiException::connectionFailed($this->baseUrl, $e->getMessage());
        }
    }

    /**
     * Build full API URL
     */
    private function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        return $this->baseUrl . '/wp-json/' . $endpoint;
    }

    /**
     * Setup default headers
     */
    private function setupHeaders(): void
    {
        $this->headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-WordPress-Importer/1.0',
        ];
    }

    /**
     * Normalize WordPress media data to our format
     */
    private function normalizeMediaData(array $mediaData): array
    {
        return [
            'id' => $mediaData['id'],
            'title' => $mediaData['title']['rendered'] ?? '',
            'filename' => $mediaData['media_details']['file'] ?? basename($mediaData['source_url']),
            'source_url' => $mediaData['source_url'],
            'mime_type' => $mediaData['mime_type'],
            'file_size' => $mediaData['media_details']['filesize'] ?? null,
            'width' => $mediaData['media_details']['width'] ?? null,
            'height' => $mediaData['media_details']['height'] ?? null,
            'alt_text' => $mediaData['alt_text'] ?? '',
            'caption' => $mediaData['caption']['rendered'] ?? '',
            'description' => $mediaData['description']['rendered'] ?? '',
            'date_created' => $mediaData['date'],
            'date_modified' => $mediaData['modified'],
            'author' => $mediaData['author'] ?? null,
            'slug' => $mediaData['slug'] ?? '',
            'media_type' => $this->determineMediaType($mediaData['mime_type']),
            'metadata' => $this->extractMetadata($mediaData),
            'raw_data' => $mediaData, // Keep original for reference
        ];
    }

    /**
     * Determine media type from mime type
     */
    private function determineMediaType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_starts_with($mimeType, 'application/pdf') => 'document',
            str_starts_with($mimeType, 'application/msword') => 'document',
            str_starts_with($mimeType, 'application/vnd.') => 'document',
            str_starts_with($mimeType, 'text/') => 'document',
            in_array($mimeType, ['application/zip', 'application/x-rar-compressed']) => 'archive',
            default => 'other',
        };
    }

    /**
     * Extract useful metadata from WordPress media data
     */
    private function extractMetadata(array $mediaData): array
    {
        $metadata = [];

        // Image-specific metadata
        if (isset($mediaData['media_details'])) {
            $details = $mediaData['media_details'];
            
            if (isset($details['image_meta'])) {
                $imageMeta = $details['image_meta'];
                $metadata['exif'] = [
                    'camera' => $imageMeta['camera'] ?? null,
                    'created_timestamp' => $imageMeta['created_timestamp'] ?? null,
                    'iso' => $imageMeta['iso'] ?? null,
                    'aperture' => $imageMeta['aperture'] ?? null,
                    'shutter_speed' => $imageMeta['shutter_speed'] ?? null,
                    'focal_length' => $imageMeta['focal_length'] ?? null,
                ];
            }

            // Available sizes for images
            if (isset($details['sizes'])) {
                $metadata['wordpress_sizes'] = array_keys($details['sizes']);
            }
        }

        // WordPress-specific data
        $metadata['wordpress'] = [
            'post_id' => $mediaData['id'],
            'link' => $mediaData['link'] ?? null,
            'status' => $mediaData['status'] ?? null,
            'comment_status' => $mediaData['comment_status'] ?? null,
            'ping_status' => $mediaData['ping_status'] ?? null,
        ];

        return $metadata;
    }

    /**
     * Extract filename from URL
     */
    private function extractFilenameFromUrl(string $url): string
    {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        
        // Remove query parameters if any
        if (str_contains($filename, '?')) {
            $filename = substr($filename, 0, strpos($filename, '?'));
        }

        return $filename ?: 'unknown-file';
    }
}