<?php

declare(strict_types=1);

namespace App\Services\WordPress;

use App\Exceptions\WordPressApiException;

class WordPressConnectionTester
{
    public function __construct(
        private readonly WordPressApiService $apiService
    ) {}

    public static function make(string $url, string $username, string $password): self
    {
        return new self(
            WordPressApiService::make($url, $username, $password)
        );
    }

    /**
     * Perform comprehensive connection test
     */
    public function test(): array
    {
        $results = [
            'success' => false,
            'tests' => [],
            'site_info' => null,
            'media_stats' => null,
            'errors' => [],
        ];

        // Test 1: Basic Connection
        $results['tests']['connection'] = $this->testBasicConnection();
        
        // Test 2: Authentication
        if ($results['tests']['connection']['success']) {
            $results['tests']['authentication'] = $this->testAuthentication();
        }

        // Test 3: API Access
        if ($results['tests']['authentication']['success'] ?? false) {
            $results['tests']['api_access'] = $this->testApiAccess();
            
            if ($results['tests']['api_access']['success']) {
                // Get site information
                try {
                    $results['site_info'] = $this->apiService->getSiteInfo();
                } catch (WordPressApiException $e) {
                    $results['errors'][] = 'Failed to get site info: ' . $e->getMessage();
                }

                // Get media statistics
                try {
                    $results['media_stats'] = $this->apiService->getMediaStatistics();
                } catch (WordPressApiException $e) {
                    $results['errors'][] = 'Failed to get media stats: ' . $e->getMessage();
                }
            }
        }

        $results['success'] = $this->allTestsPassed($results['tests']);

        return $results;
    }

    /**
     * Test basic connection to WordPress site
     */
    private function testBasicConnection(): array
    {
        try {
            $this->apiService->getSiteInfo();
            
            return [
                'success' => true,
                'message' => 'Successfully connected to WordPress site',
            ];
        } catch (WordPressApiException $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test authentication with WordPress
     */
    private function testAuthentication(): array
    {
        try {
            $connected = $this->apiService->testConnection();
            
            if ($connected) {
                return [
                    'success' => true,
                    'message' => 'Authentication successful',
                ];
            }

            return [
                'success' => false,
                'message' => 'Authentication failed - invalid credentials',
            ];
        } catch (WordPressApiException $e) {
            return [
                'success' => false,
                'message' => 'Authentication error: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test API access permissions
     */
    private function testApiAccess(): array
    {
        try {
            // Try to fetch first page of media
            $result = $this->apiService->fetchMediaLibrary(1, 1);
            
            return [
                'success' => true,
                'message' => "Successfully accessed media library. Found {$result['total']} media items.",
                'total_media' => $result['total'],
            ];
        } catch (WordPressApiException $e) {
            return [
                'success' => false,
                'message' => 'Failed to access media library: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if all tests passed
     */
    private function allTestsPassed(array $tests): bool
    {
        foreach ($tests as $test) {
            if (! $test['success']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get connection recommendations
     */
    public function getRecommendations(array $testResults): array
    {
        $recommendations = [];

        if (! $testResults['tests']['connection']['success'] ?? false) {
            $recommendations[] = [
                'type' => 'error',
                'title' => 'Connection Failed',
                'message' => 'Verify the WordPress URL is correct and the site is accessible.',
                'action' => 'Check URL format: https://yoursite.com (no trailing slash)',
            ];
        }

        if (! $testResults['tests']['authentication']['success'] ?? false) {
            $recommendations[] = [
                'type' => 'error',
                'title' => 'Authentication Failed',
                'message' => 'The username/password combination is invalid.',
                'action' => 'Create an Application Password in WordPress (Users → Profile → Application Passwords)',
            ];
        }

        if (! $testResults['tests']['api_access']['success'] ?? false) {
            $recommendations[] = [
                'type' => 'error',
                'title' => 'API Access Denied',
                'message' => 'The user account does not have permission to access the media library.',
                'action' => 'Ensure the user has at least Editor role or custom permissions for media access',
            ];
        }

        if ($testResults['success']) {
            $mediaCount = $testResults['media_stats']['total'] ?? 0;
            
            if ($mediaCount === 0) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'No Media Found',
                    'message' => 'The WordPress media library appears to be empty.',
                    'action' => 'Upload some media files to WordPress to test the import process',
                ];
            } elseif ($mediaCount > 1000) {
                $recommendations[] = [
                    'type' => 'info',
                    'title' => 'Large Media Library',
                    'message' => "Found {$mediaCount} media items. Import may take significant time.",
                    'action' => 'Consider importing in smaller batches or during off-peak hours',
                ];
            } else {
                $recommendations[] = [
                    'type' => 'success',
                    'title' => 'Ready to Import',
                    'message' => "Connection successful! Found {$mediaCount} media items ready for import.",
                    'action' => 'You can proceed with the full import process',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get detailed diagnostics
     */
    public function getDiagnostics(): array
    {
        $diagnostics = [];

        try {
            $siteInfo = $this->apiService->getSiteInfo();
            $diagnostics['wordpress_version'] = $siteInfo['wp_version'] ?? 'Unknown';
            $diagnostics['api_version'] = $siteInfo['api_version'] ?? 'Unknown';
        } catch (WordPressApiException) {
            $diagnostics['wordpress_version'] = 'Unable to determine';
            $diagnostics['api_version'] = 'Unable to determine';
        }

        try {
            $mediaStats = $this->apiService->getMediaStatistics();
            $diagnostics['media_breakdown'] = $mediaStats['by_type'] ?? [];
            $diagnostics['total_media'] = $mediaStats['total'] ?? 0;
        } catch (WordPressApiException) {
            $diagnostics['media_breakdown'] = [];
            $diagnostics['total_media'] = 'Unable to determine';
        }

        return $diagnostics;
    }
}