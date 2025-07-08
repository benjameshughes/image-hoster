<?php

namespace App\Livewire\Media;

use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;

class CompressionComparison extends Component
{
    use AuthorizesRequests, Toast;

    public Media $media;

    public array $compressionLevels = [];

    public bool $isGenerating = false;

    public int $selectedQuality = 85;

    public bool $showComparison = false;

    public function mount(Media $media)
    {
        $this->authorize('view', $media);
        $this->media = $media;
        $this->selectedQuality = $this->getMediaCurrentQuality();

        // Ensure selected quality is valid
        if ($this->selectedQuality < 50 || $this->selectedQuality > 95) {
            $this->selectedQuality = 85;
        }
    }

    public function generateCompressionLevels()
    {
        $this->isGenerating = true;

        try {
            $this->authorize('update', $this->media);

            // Only process images
            if ($this->media->media_type->value !== 'image') {
                throw new \Exception('Compression is only available for images');
            }

            // Check if media file exists
            if (! $this->media->exists()) {
                throw new \Exception('Media file not found on storage');
            }

            $processingService = app(ImageProcessingService::class);
            $this->compressionLevels = $processingService->createCompressionLevels($this->media);
            $this->showComparison = true;
            $this->success('Compression levels generated successfully!');
        } catch (\Exception $e) {
            \Log::error('Compression levels generation failed', [
                'media_id' => $this->media->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to generate compression levels: '.$e->getMessage());
        } finally {
            $this->isGenerating = false;
        }
    }

    public function applyCompression()
    {
        try {
            $this->authorize('update', $this->media);

            // Validate quality value
            if ($this->selectedQuality < 50 || $this->selectedQuality > 95) {
                throw new \Exception('Invalid quality level selected');
            }

            // Check if media file exists
            if (! $this->media->exists()) {
                throw new \Exception('Media file not found on storage');
            }

            $processingService = app(ImageProcessingService::class);
            $result = $processingService->applyCompression($this->media, $this->selectedQuality);

            // Refresh the media model
            $this->media->refresh();

            $this->success("Compression applied! Saved {$result['compression_ratio']}% space.");

            // Refresh parent page
            $this->dispatch('refresh-page');

        } catch (\Exception $e) {
            \Log::error('Compression application failed', [
                'media_id' => $this->media->id,
                'quality' => $this->selectedQuality,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to apply compression: '.$e->getMessage());
        }
    }

    public function clearCompressionLevels()
    {
        // Clean up temporary files
        foreach ($this->compressionLevels as $level) {
            if (isset($level['path']) && \Storage::disk($this->media->disk->value)->exists($level['path'])) {
                \Storage::disk($this->media->disk->value)->delete($level['path']);
            }
        }

        $this->compressionLevels = [];
        $this->showComparison = false;
    }

    public function getCompressionPresets()
    {
        $processingService = app(ImageProcessingService::class);

        return $processingService->getCompressionPresets();
    }

    private function getMediaCurrentQuality(): int
    {
        // Try to estimate current quality based on compression ratio
        if ($this->media->hasCompressed() && $this->media->compressionRatio()) {
            $ratio = $this->media->compressionRatio();

            return match (true) {
                $ratio < 10 => 95,
                $ratio < 25 => 85,
                $ratio < 40 => 75,
                $ratio < 55 => 65,
                default => 50,
            };
        }

        return 85; // Default
    }

    public function getOriginalSizeFormatted(): string
    {
        return $this->formatBytes($this->media->size);
    }

    public function getCurrentCompressedSizeFormatted(): string
    {
        if ($this->media->hasCompressed()) {
            return $this->formatBytes($this->media->compressed_size);
        }

        return 'Not compressed';
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2).' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2).' MB',
            $bytes >= 1024 => round($bytes / 1024, 2).' KB',
            default => $bytes.' B',
        };
    }

    public function render()
    {
        return view('livewire.media.compression-comparison');
    }
}
