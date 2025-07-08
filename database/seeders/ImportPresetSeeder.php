<?php

namespace Database\Seeders;

use App\Models\ImportPreset;
use Illuminate\Database\Seeder;

class ImportPresetSeeder extends Seeder
{
    public function run(): void
    {
        // Global presets available to all users
        $presets = [
            [
                'name' => 'High Quality Images',
                'description' => 'Import only images with full processing, compression, and thumbnails enabled.',
                'config' => [
                    'storage_disk' => 'spaces',
                    'process_images' => true,
                    'generate_thumbnails' => true,
                    'compress_images' => true,
                    'skip_duplicates' => true,
                    'duplicate_strategy' => 'skip',
                    'media_types' => ['image'],
                    'import_path' => 'wordpress/images/{year}/{month}',
                ],
                'is_global' => true,
            ],
            [
                'name' => 'All Media Types',
                'description' => 'Import all media types (images, videos, audio, documents) with basic processing.',
                'config' => [
                    'storage_disk' => 'spaces',
                    'process_images' => true,
                    'generate_thumbnails' => false,
                    'compress_images' => false,
                    'skip_duplicates' => true,
                    'duplicate_strategy' => 'skip',
                    'media_types' => ['image', 'video', 'audio', 'document'],
                    'import_path' => 'wordpress/{year}/{month}',
                ],
                'is_global' => true,
            ],
            [
                'name' => 'Quick Import (No Processing)',
                'description' => 'Fast import with minimal processing - good for large libraries.',
                'config' => [
                    'storage_disk' => 'spaces',
                    'process_images' => false,
                    'generate_thumbnails' => false,
                    'compress_images' => false,
                    'skip_duplicates' => true,
                    'duplicate_strategy' => 'skip',
                    'media_types' => ['image', 'video', 'audio', 'document'],
                    'import_path' => 'wordpress/bulk/{year}',
                ],
                'is_global' => true,
            ],
            [
                'name' => 'Recent Media Only',
                'description' => 'Import only media from the last 12 months with full processing.',
                'config' => [
                    'storage_disk' => 'spaces',
                    'process_images' => true,
                    'generate_thumbnails' => true,
                    'compress_images' => true,
                    'skip_duplicates' => true,
                    'duplicate_strategy' => 'skip',
                    'media_types' => ['image', 'video', 'audio', 'document'],
                    'from_date' => now()->subYear()->toDateString(),
                    'import_path' => 'wordpress/recent/{year}/{month}',
                ],
                'is_global' => true,
            ],
            [
                'name' => 'Documents & PDFs Only',
                'description' => 'Import only document files (PDFs, Word docs, etc.) to a dedicated folder.',
                'config' => [
                    'storage_disk' => 'spaces',
                    'process_images' => false,
                    'generate_thumbnails' => false,
                    'compress_images' => false,
                    'skip_duplicates' => true,
                    'duplicate_strategy' => 'skip',
                    'media_types' => ['document'],
                    'import_path' => 'wordpress/documents/{year}',
                ],
                'is_global' => true,
            ],
        ];

        // Get first user or create a system user for global presets
        $firstUser = \App\Models\User::first();
        if (!$firstUser) {
            // Create a system user for global presets if no users exist
            $firstUser = \App\Models\User::create([
                'name' => 'System',
                'email' => 'system@wordpress-importer.local',
                'password' => bcrypt('system-presets-only'),
            ]);
        }

        foreach ($presets as $preset) {
            ImportPreset::create([
                'user_id' => $firstUser->id,
                'name' => $preset['name'],
                'description' => $preset['description'],
                'config' => $preset['config'],
                'is_global' => $preset['is_global'],
                'is_default' => false,
            ]);
        }
    }
}