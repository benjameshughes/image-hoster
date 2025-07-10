<?php

declare(strict_types=1);

namespace App\Livewire\Import\Components;

use Livewire\Attributes\Validate;
use Livewire\Component;

class ImportConfiguration extends Component
{
    #[Validate('required|string|min:3')]
    public string $importName = '';

    // Storage options
    #[Validate('required|in:spaces,r2,local')]
    public string $storageDisk = 'r2';

    public array $storageDisks = [
        'spaces' => 'DigitalOcean Spaces',
        'r2' => 'Cloudflare R2',
        'local' => 'Local Storage',
    ];

    // Import options
    public bool $processImages = true;
    public bool $generateThumbnails = true;
    public bool $compressImages = true;
    public bool $skipDuplicates = true;
    public string $duplicateStrategy = 'skip'; // skip, replace, rename
    
    // Filtering options
    public array $selectedMediaTypes = ['image', 'video', 'audio', 'document'];
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public ?int $maxItems = null;
    public string $importPath = 'wordpress/{year}/{month}';

    public array $availableMediaTypes = [
        'image' => 'Images',
        'video' => 'Videos', 
        'audio' => 'Audio',
        'document' => 'Documents'
    ];

    public array $duplicateStrategies = [
        'skip' => 'Skip duplicates',
        'replace' => 'Replace existing',
        'rename' => 'Rename new files'
    ];

    public function mount(): void
    {
        if (! $this->importName) {
            $this->importName = 'WordPress Import ' . now()->format('M j, Y g:i A');
        }
    }

    public function getConfigurationProperty(): array
    {
        return [
            'storage_disk' => $this->storageDisk,
            'process_images' => $this->processImages,
            'generate_thumbnails' => $this->generateThumbnails,
            'compress_images' => $this->compressImages,
            'skip_duplicates' => $this->skipDuplicates,
            'duplicate_strategy' => $this->duplicateStrategy,
            'media_types' => $this->selectedMediaTypes,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'max_items' => $this->maxItems,
            'import_path' => $this->importPath,
        ];
    }

    public function loadConfiguration(array $config): void
    {
        $this->storageDisk = $config['storage_disk'] ?? 'r2';
        $this->processImages = $config['process_images'] ?? true;
        $this->generateThumbnails = $config['generate_thumbnails'] ?? true;
        $this->compressImages = $config['compress_images'] ?? true;
        $this->skipDuplicates = $config['skip_duplicates'] ?? true;
        $this->duplicateStrategy = $config['duplicate_strategy'] ?? 'skip';
        $this->selectedMediaTypes = $config['media_types'] ?? ['image'];
        $this->fromDate = $config['from_date'] ?? null;
        $this->toDate = $config['to_date'] ?? null;
        $this->maxItems = $config['max_items'] ?? null;
        $this->importPath = $config['import_path'] ?? 'wordpress/{year}/{month}';
    }

    public function updatedSelectedMediaTypes(): void
    {
        // Emit configuration change to parent
        $this->dispatch('configuration-changed', $this->configuration);
    }

    public function updatedStorageDisk(): void
    {
        $this->dispatch('configuration-changed', $this->configuration);
    }

    public function render()
    {
        return view('livewire.import.components.import-configuration');
    }
}