<?php

declare(strict_types=1);

namespace App\Livewire\Import;

use App\Enums\ImportStatus;
use App\Jobs\WordPress\AnalyzeWordPressMediaJob;
use App\Models\Import;
use App\Models\ImportPreset;
use App\Services\WordPress\WordPressApiService;
use App\Services\WordPress\WordPressConnectionTester;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

class Dashboard extends Component
{
    use Toast;

    #[Validate('required|url')]
    public string $wordpressUrl = '';

    #[Validate('required|string')]
    public string $username = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('required|string|min:3')]
    public string $importName = '';

    // Storage options
    #[Validate('required|in:spaces,s3,r2,local')]
    public string $storageDisk = 'spaces';

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
    
    public bool $testing = false;
    public bool $importing = false;
    public array $connectionTest = [];
    public array $siteInfo = [];
    public array $mediaStats = [];
    public ?Import $activeImport = null;
    
    // Preset functionality
    public ?int $selectedPresetId = null;
    public bool $showPresetModal = false;
    public string $newPresetName = '';
    public string $newPresetDescription = '';

    public function mount(): void
    {
        $this->activeImport = Auth::user()
            ->imports()
            ->where('status', ImportStatus::RUNNING)
            ->latest()
            ->first();

        if (! $this->importName) {
            $this->importName = 'WordPress Import ' . now()->format('M j, Y g:i A');
        }
        
        // Load default preset if available
        $defaultPreset = ImportPreset::getDefaultForUser(Auth::id());
        if ($defaultPreset) {
            $this->loadPreset($defaultPreset->id);
        }
    }

    public function testConnection(): void
    {
        $this->validate([
            'wordpressUrl' => 'required|url',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $this->testing = true;
        $this->connectionTest = [];
        $this->siteInfo = [];
        $this->mediaStats = [];

        try {
            $tester = WordPressConnectionTester::make(
                $this->wordpressUrl,
                $this->username,
                $this->password
            );

            $results = $tester->test();
            $this->connectionTest = $results;

            if ($results['success']) {
                $this->siteInfo = $results['site_info'] ?? [];
                $this->mediaStats = $results['media_stats'] ?? [];
                
                $this->success(
                    title: 'Connection Successful!',
                    description: "Found {$this->mediaStats['total']} media items ready for import.",
                    position: 'toast-top toast-end',
                    timeout: 4000
                );
            } else {
                $this->error(
                    title: 'Connection Failed',
                    description: 'Please check your credentials and try again.',
                    position: 'toast-top toast-end',
                    timeout: 6000
                );
            }
        } catch (\Exception $e) {
            $this->error(
                title: 'Connection Error',
                description: $e->getMessage(),
                position: 'toast-top toast-end',
                timeout: 6000
            );
        } finally {
            $this->testing = false;
        }
    }

    public function startImport(): void
    {
        $this->validate();

        if (! $this->connectionTest['success'] ?? false) {
            $this->error(
                title: 'Test Connection First',
                description: 'Please test the connection before starting import.',
                position: 'toast-top toast-end'
            );
            return;
        }

        $this->importing = true;

        try {
            // Create the import record
            $this->activeImport = Auth::user()->imports()->create([
                'source' => 'wordpress',
                'name' => $this->importName,
                'config' => [
                    'wordpress_url' => $this->wordpressUrl,
                    'username' => $this->username,
                    'password' => $this->password,
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
                ],
                'total_items' => $this->mediaStats['total'] ?? 0,
                'status' => ImportStatus::PENDING,
            ]);

            // Dispatch the background job to analyze and import media
            AnalyzeWordPressMediaJob::dispatch($this->activeImport)
                ->onQueue('wordpress-import');

            $this->success(
                title: 'Import Started!',
                description: 'Your WordPress media import has been queued for processing.',
                position: 'toast-top toast-end',
                timeout: 4000
            );

            // Reset form but keep storage preferences
            $this->reset(['wordpressUrl', 'username', 'password']);
            $this->importName = 'WordPress Import ' . now()->format('M j, Y g:i A');
            $this->connectionTest = [];
            $this->siteInfo = [];
            $this->mediaStats = [];
            
            // Optionally save successful import config as preset
            if ($this->selectedPresetId === null) {
                $this->info(
                    title: 'Save as Preset?',
                    description: 'Consider saving these settings as a preset for future imports.',
                    position: 'toast-top toast-end',
                    timeout: 8000
                );
            }

        } catch (\Exception $e) {
            $this->error(
                title: 'Import Failed',
                description: 'Failed to start import: ' . $e->getMessage(),
                position: 'toast-top toast-end',
                timeout: 6000
            );
        } finally {
            $this->importing = false;
        }
    }

    public function pauseImport(): void
    {
        if ($this->activeImport && $this->activeImport->status->canBePaused()) {
            $this->activeImport->pause();
            $this->warning(
                title: 'Import Paused',
                description: 'The import has been paused. You can resume it anytime.',
                position: 'toast-top toast-end'
            );
        }
    }

    public function resumeImport(): void
    {
        if ($this->activeImport && $this->activeImport->status->canBeResumed()) {
            $this->activeImport->resume();
            $this->info(
                title: 'Import Resumed',
                description: 'The import has been resumed and will continue processing.',
                position: 'toast-top toast-end'
            );
        }
    }

    public function cancelImport(): void
    {
        if ($this->activeImport && $this->activeImport->status->canBeCancelled()) {
            $this->activeImport->cancel();
            $this->activeImport = null;
            $this->info(
                title: 'Import Cancelled',
                description: 'The import has been cancelled.',
                position: 'toast-top toast-end'
            );
        }
    }

    public function refreshProgress(): void
    {
        if ($this->activeImport) {
            $this->activeImport->refresh();
        }
    }

    public function getConnectionStatusProperty(): string
    {
        if (empty($this->connectionTest)) {
            return 'untested';
        }

        return $this->connectionTest['success'] ? 'success' : 'failed';
    }

    public function getProgressPercentageProperty(): float
    {
        if (! $this->activeImport || $this->activeImport->total_items === 0) {
            return 0;
        }

        return ($this->activeImport->processed_items / $this->activeImport->total_items) * 100;
    }

    public function getFormattedProgressProperty(): string
    {
        if (! $this->activeImport) {
            return '0 / 0';
        }

        return "{$this->activeImport->processed_items} / {$this->activeImport->total_items}";
    }

    public function getRecentImportsProperty()
    {
        return Auth::user()
            ->imports()
            ->latest()
            ->take(5)
            ->get();
    }
    
    public function getAvailablePresetsProperty()
    {
        return ImportPreset::availableForUser(Auth::id())->get();
    }
    
    public function loadPreset(?int $presetId): void
    {
        if (!$presetId) {
            $this->selectedPresetId = null;
            return;
        }
        
        $preset = ImportPreset::availableForUser(Auth::id())
            ->where('id', $presetId)
            ->first();
            
        if (!$preset) {
            return;
        }
        
        $config = $preset->getConfigWithDefaults();
        
        $this->selectedPresetId = $presetId;
        $this->storageDisk = $config['storage_disk'];
        $this->processImages = $config['process_images'];
        $this->generateThumbnails = $config['generate_thumbnails'];
        $this->compressImages = $config['compress_images'];
        $this->skipDuplicates = $config['skip_duplicates'];
        $this->duplicateStrategy = $config['duplicate_strategy'];
        $this->selectedMediaTypes = $config['media_types'];
        $this->fromDate = $config['from_date'];
        $this->toDate = $config['to_date'];
        $this->maxItems = $config['max_items'];
        $this->importPath = $config['import_path'];
        
        $this->info(
            title: 'Preset Loaded',
            description: "Applied settings from '{$preset->name}' preset.",
            position: 'toast-top toast-end'
        );
    }
    
    public function savePreset(): void
    {
        $this->validate([
            'newPresetName' => 'required|string|min:3|max:255',
            'newPresetDescription' => 'nullable|string|max:500',
        ]);
        
        $config = [
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
        
        Auth::user()->importPresets()->create([
            'name' => $this->newPresetName,
            'description' => $this->newPresetDescription ?: null,
            'config' => $config,
        ]);
        
        $this->success(
            title: 'Preset Saved',
            description: "Import preset '{$this->newPresetName}' has been saved.",
            position: 'toast-top toast-end'
        );
        
        $this->reset(['showPresetModal', 'newPresetName', 'newPresetDescription']);
    }
    
    public function deletePreset(int $presetId): void
    {
        $preset = Auth::user()->importPresets()->find($presetId);
        
        if ($preset) {
            $presetName = $preset->name;
            $preset->delete();
            
            if ($this->selectedPresetId === $presetId) {
                $this->selectedPresetId = null;
            }
            
            $this->warning(
                title: 'Preset Deleted',
                description: "Import preset '{$presetName}' has been deleted.",
                position: 'toast-top toast-end'
            );
        }
    }

    public function render()
    {
        return view('livewire.import.dashboard')
            ->layout('layouts.app');
    }
}