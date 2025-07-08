<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Import;
use App\Services\WordPress\WordPressApiService;
use Illuminate\Console\Command;

class DebugImportCommand extends Command
{
    protected $signature = 'import:debug {import : The import ID to debug}';
    protected $description = 'Debug a WordPress import';

    public function handle(): int
    {
        $importId = $this->argument('import');
        $import = Import::find($importId);

        if (!$import) {
            $this->error("Import {$importId} not found");
            return 1;
        }

        $this->info("Debugging import: {$import->name}");
        $this->info("Status: {$import->status->value}");
        $this->info("Total items: {$import->total_items}");
        $this->info("Processed items: {$import->processed_items}");
        $this->info("Import items in DB: " . $import->items()->count());

        // Test WordPress connection
        $config = $import->config;
        $this->info("Testing WordPress connection...");

        try {
            $apiService = WordPressApiService::make(
                $config['wordpress_url'],
                $config['username'],
                $config['password']
            )->timeout(10)->retries(1);

            if ($apiService->testConnection()) {
                $this->info("✓ WordPress connection successful");
                
                // Test getting media stats
                $this->info("Getting media statistics...");
                $stats = $apiService->getMediaStatistics();
                $this->info("Media stats: " . json_encode($stats, JSON_PRETTY_PRINT));
                
                // Test getting first few media items
                $this->info("Getting first 5 media items...");
                $count = 0;
                foreach ($apiService->getAllMedia() as $mediaData) {
                    $this->info("Media {$count}: {$mediaData['title']} ({$mediaData['id']})");
                    $count++;
                    if ($count >= 5) break;
                }
                
            } else {
                $this->error("✗ WordPress connection failed");
            }
        } catch (\Exception $e) {
            $this->error("WordPress API error: " . $e->getMessage());
        }

        return 0;
    }
}