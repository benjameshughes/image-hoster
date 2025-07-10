<?php

namespace App\Console\Commands;

use App\Enums\ImportStatus;
use App\Events\ImportItemProcessed;
use App\Events\ImportProgressUpdated;
use App\Events\ImportStatusChanged;
use App\Models\Import;
use App\Models\ImportItem;
use App\Models\User;
use Illuminate\Console\Command;

class TestRealtimeImportEvents extends Command
{
    protected $signature = 'test:realtime-import-events {--import-id= : ID of existing import to test with}';

    protected $description = 'Test real-time import events for debugging WebSocket connections';

    public function handle(): int
    {
        $this->info('Testing real-time import events...');

        $import = $this->getTestImport();
        
        if (!$import) {
            $this->error('No import found to test with.');
            return 1;
        }

        $this->info("Testing with import: {$import->name} (ID: {$import->id})");

        // Test 1: Import Progress Updated
        $this->info("\n1. Testing ImportProgressUpdated event...");
        ImportProgressUpdated::dispatch($import, collect([
            'test_event' => true,
            'timestamp' => now()->toISOString(),
            'latest_item' => [
                'title' => 'Test Image.jpg',
                'type' => 'image',
                'size' => 2048576,
                'status' => 'completed'
            ]
        ]));
        $this->info('✓ ImportProgressUpdated event dispatched');

        sleep(1);

        // Test 2: Import Status Changed
        $this->info("\n2. Testing ImportStatusChanged event...");
        ImportStatusChanged::dispatch($import, ImportStatus::PENDING, ImportStatus::RUNNING, 'Import is now processing media files');
        $this->info('✓ ImportStatusChanged event dispatched');

        sleep(1);

        // Test 3: Import Item Processed (Success)
        $this->info("\n3. Testing ImportItemProcessed event (success)...");
        $testItem = $this->createTestImportItem($import);
        ImportItemProcessed::dispatch($testItem, true);
        $this->info('✓ ImportItemProcessed (success) event dispatched');

        sleep(1);

        // Test 4: Import Item Processed (Failure)
        $this->info("\n4. Testing ImportItemProcessed event (failure)...");
        ImportItemProcessed::dispatch($testItem, false, 'Test error: File format not supported');
        $this->info('✓ ImportItemProcessed (failure) event dispatched');

        $this->info("\n🎉 All test events dispatched successfully!");
        $this->info("Open your browser to the import dashboard or progress page to see if events are received.");
        $this->newLine();
        $this->table(['Channel', 'Event'], [
            ["import.{$import->id}", 'import.progress.updated'],
            ["import.{$import->id}", 'import.status.changed'],
            ["import.{$import->id}", 'import.item.processed'],
            ["user.{$import->user_id}.imports", 'import.status.changed'],
        ]);

        return 0;
    }

    private function getTestImport(): ?Import
    {
        $importId = $this->option('import-id');

        if ($importId) {
            return Import::find($importId);
        }

        // Try to find any existing import
        $import = Import::latest()->first();

        if (!$import) {
            // Create a test import if none exists
            $user = User::first();
            if (!$user) {
                $this->error('No users found. Please create a user first.');
                return null;
            }

            $import = Import::create([
                'user_id' => $user->id,
                'name' => 'Test Import for Real-time Events',
                'wordpress_url' => 'https://example.com',
                'total_items' => 100,
                'processed_items' => 45,
                'successful_items' => 42,
                'failed_items' => 2,
                'duplicate_items' => 1,
                'status' => ImportStatus::RUNNING,
                'configuration' => [
                    'storage_disk' => 'spaces',
                    'process_images' => true,
                    'generate_thumbnails' => true,
                    'compress_images' => true,
                    'duplicate_strategy' => 'skip',
                    'selected_media_types' => ['image', 'video']
                ],
                'started_at' => now()->subMinutes(30)
            ]);

            $this->info("Created test import: {$import->id}");
        }

        return $import;
    }

    private function createTestImportItem(Import $import): ImportItem
    {
        return ImportItem::firstOrCreate([
            'import_id' => $import->id,
            'source_id' => '999999',
        ], [
            'title' => 'Test Real-time Event Item',
            'source_url' => 'https://example.com/wp-content/uploads/test-image.jpg',
            'source_metadata' => ['test' => true],
            'mime_type' => 'image/jpeg',
            'file_size' => 1024000,
            'status' => 'pending'
        ]);
    }
}
