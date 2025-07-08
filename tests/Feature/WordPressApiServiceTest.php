<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\WordPressApiException;
use App\Services\WordPress\WordPressApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressApiServiceTest extends TestCase
{
    private WordPressApiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = WordPressApiService::make(
            'https://example-wp.com',
            'testuser',
            'testpass'
        );
    }

    public function test_can_validate_wordpress_url(): void
    {
        Http::fake([
            'https://valid-wp.com/wp-json/' => Http::response([
                'routes' => ['/wp/v2' => []]
            ]),
            'https://invalid-site.com/wp-json/' => Http::response([], 404),
        ]);

        $this->assertTrue(WordPressApiService::validateWordPressUrl('https://valid-wp.com'));
        $this->assertFalse(WordPressApiService::validateWordPressUrl('https://invalid-site.com'));
    }

    public function test_can_test_connection(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/users/me' => Http::response([
                'id' => 1,
                'name' => 'Test User',
            ]),
        ]);

        $this->assertTrue($this->service->testConnection());
    }

    public function test_connection_fails_with_invalid_credentials(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/users/me' => Http::response([], 401),
        ]);

        $this->assertFalse($this->service->testConnection());
    }

    public function test_can_fetch_media_library(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/media*' => Http::response(
                [
                    [
                        'id' => 123,
                        'title' => ['rendered' => 'Test Image'],
                        'source_url' => 'https://example-wp.com/wp-content/uploads/test.jpg',
                        'mime_type' => 'image/jpeg',
                        'media_details' => [
                            'file' => 'test.jpg',
                            'width' => 1920,
                            'height' => 1080,
                            'filesize' => 500000,
                        ],
                        'alt_text' => 'Test alt text',
                        'caption' => ['rendered' => 'Test caption'],
                        'description' => ['rendered' => 'Test description'],
                        'date' => '2023-01-01T00:00:00',
                        'modified' => '2023-01-01T00:00:00',
                        'slug' => 'test-image',
                    ]
                ],
                200,
                [
                    'X-WP-Total' => '1',
                    'X-WP-TotalPages' => '1',
                ]
            ),
        ]);

        $result = $this->service->fetchMediaLibrary();

        $this->assertArrayHasKey('media', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['media']);
    }

    public function test_can_get_specific_media_item(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/media/123' => Http::response([
                'id' => 123,
                'title' => ['rendered' => 'Test Image'],
                'source_url' => 'https://example-wp.com/wp-content/uploads/test.jpg',
                'mime_type' => 'image/jpeg',
                'media_details' => [
                    'file' => 'test.jpg',
                    'width' => 1920,
                    'height' => 1080,
                ],
                'alt_text' => 'Test alt text',
                'caption' => ['rendered' => ''],
                'description' => ['rendered' => ''],
                'date' => '2023-01-01T00:00:00',
                'modified' => '2023-01-01T00:00:00',
                'slug' => 'test-image',
            ]),
        ]);

        $media = $this->service->getMediaItem('123');

        $this->assertEquals(123, $media['id']);
        $this->assertEquals('Test Image', $media['title']);
        $this->assertEquals('image/jpeg', $media['mime_type']);
        $this->assertEquals('image', $media['media_type']);
    }

    public function test_throws_exception_for_non_existent_media(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/media/999' => Http::response([], 404),
        ]);

        $this->expectException(WordPressApiException::class);
        $this->expectExceptionMessage('Media item not found: 999');

        $this->service->getMediaItem('999');
    }

    public function test_can_download_media_file(): void
    {
        Http::fake([
            'https://example-wp.com/wp-content/uploads/test.jpg' => Http::response(
                'fake-image-content',
                200,
                ['content-type' => 'image/jpeg']
            ),
        ]);

        $result = $this->service->downloadMediaFile(
            'https://example-wp.com/wp-content/uploads/test.jpg',
            'test.jpg'
        );

        $this->assertArrayHasKey('temp_path', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertEquals('test.jpg', $result['filename']);
        $this->assertEquals('image/jpeg', $result['mime_type']);

        // Cleanup
        $this->service->cleanupTempFile($result['temp_path']);
    }

    public function test_handles_download_failure(): void
    {
        Http::fake([
            'https://example-wp.com/wp-content/uploads/missing.jpg' => Http::response([], 404),
        ]);

        $this->expectException(WordPressApiException::class);
        $this->expectExceptionMessage('Failed to download media from');

        $this->service->downloadMediaFile('https://example-wp.com/wp-content/uploads/missing.jpg');
    }

    public function test_can_get_media_statistics(): void
    {
        // Mock the initial call to get total
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/media?page=1&per_page=1&orderby=date&order=asc' => Http::response(
                [['id' => 1]],
                200,
                ['X-WP-Total' => '150', 'X-WP-TotalPages' => '150']
            ),
            'https://example-wp.com/wp-json/wp/v2/media?page=1&per_page=1&orderby=date&order=asc&media_type=image' => Http::response(
                [['id' => 1]],
                200,
                ['X-WP-Total' => '100', 'X-WP-TotalPages' => '100']
            ),
            'https://example-wp.com/wp-json/wp/v2/media?page=1&per_page=1&orderby=date&order=asc&media_type=video' => Http::response(
                [['id' => 2]],
                200,
                ['X-WP-Total' => '30', 'X-WP-TotalPages' => '30']
            ),
            'https://example-wp.com/wp-json/wp/v2/media?page=1&per_page=1&orderby=date&order=asc&media_type=audio' => Http::response(
                [['id' => 3]],
                200,
                ['X-WP-Total' => '15', 'X-WP-TotalPages' => '15']
            ),
            'https://example-wp.com/wp-json/wp/v2/media?page=1&per_page=1&orderby=date&order=asc&media_type=application' => Http::response(
                [['id' => 4]],
                200,
                ['X-WP-Total' => '5', 'X-WP-TotalPages' => '5']
            ),
        ]);

        $stats = $this->service->getMediaStatistics();

        $this->assertEquals(150, $stats['total']);
        $this->assertEquals(100, $stats['by_type']['image']);
        $this->assertEquals(30, $stats['by_type']['video']);
        $this->assertEquals(15, $stats['by_type']['audio']);
        $this->assertEquals(5, $stats['by_type']['application']);
    }

    public function test_normalizes_media_data_correctly(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/media/123' => Http::response([
                'id' => 123,
                'title' => ['rendered' => 'My Test Image'],
                'source_url' => 'https://example-wp.com/wp-content/uploads/2023/01/test.jpg',
                'mime_type' => 'image/jpeg',
                'media_details' => [
                    'file' => '2023/01/test.jpg',
                    'width' => 1920,
                    'height' => 1080,
                    'filesize' => 500000,
                    'image_meta' => [
                        'camera' => 'Canon EOS R5',
                        'iso' => '100',
                        'aperture' => '2.8',
                    ],
                ],
                'alt_text' => 'Beautiful landscape',
                'caption' => ['rendered' => 'A beautiful landscape photo'],
                'description' => ['rendered' => 'This is a detailed description'],
                'date' => '2023-01-15T12:30:00',
                'modified' => '2023-01-16T08:45:00',
                'slug' => 'my-test-image',
                'author' => 1,
            ]),
        ]);

        $media = $this->service->getMediaItem('123');

        $this->assertEquals(123, $media['id']);
        $this->assertEquals('My Test Image', $media['title']);
        $this->assertEquals('2023/01/test.jpg', $media['filename']);
        $this->assertEquals('image/jpeg', $media['mime_type']);
        $this->assertEquals('image', $media['media_type']);
        $this->assertEquals(500000, $media['file_size']);
        $this->assertEquals(1920, $media['width']);
        $this->assertEquals(1080, $media['height']);
        $this->assertEquals('Beautiful landscape', $media['alt_text']);
        $this->assertEquals('A beautiful landscape photo', $media['caption']);
        $this->assertEquals('This is a detailed description', $media['description']);
        $this->assertEquals('2023-01-15T12:30:00', $media['date_created']);
        $this->assertEquals('my-test-image', $media['slug']);
        $this->assertEquals(1, $media['author']);

        // Check metadata
        $this->assertArrayHasKey('exif', $media['metadata']);
        $this->assertEquals('Canon EOS R5', $media['metadata']['exif']['camera']);
        $this->assertEquals('100', $media['metadata']['exif']['iso']);
        $this->assertEquals('2.8', $media['metadata']['exif']['aperture']);

        $this->assertArrayHasKey('wordpress', $media['metadata']);
        $this->assertEquals(123, $media['metadata']['wordpress']['post_id']);
    }

    public function test_handles_rate_limiting(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/media' => Http::response([], 429),
        ]);

        $this->expectException(WordPressApiException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->service->fetchMediaLibrary();
    }

    public function test_retries_on_server_errors(): void
    {
        Http::fake([
            'https://example-wp.com/wp-json/wp/v2/users/me' => Http::sequence()
                ->push('', 500)  // First attempt fails
                ->push('', 500)  // Second attempt fails  
                ->push(['id' => 1], 200), // Third attempt succeeds
        ]);

        $result = $this->service->testConnection();

        $this->assertTrue($result);

        // Verify exactly 3 requests were made
        Http::assertSentCount(3);
    }
}