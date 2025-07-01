<?php

namespace App\Livewire\Image;

use App\Models\Image;
use App\Services\ImageProcessingService;
use Livewire\Component;
use Mary\Traits\Toast;

class CompressionComparison extends Component
{
    use Toast;

    public Image $image;
    public array $compressionLevels = [];
    public bool $isGenerating = false;
    public int $selectedQuality = 85;
    public bool $showComparison = false;

    public function mount(Image $image)
    {
        $this->image = $image;
        $this->selectedQuality = $this->getImageCurrentQuality();
        
        // Ensure selected quality is valid
        if ($this->selectedQuality < 50 || $this->selectedQuality > 95) {
            $this->selectedQuality = 85;
        }
    }

    public function generateCompressionLevels()
    {
        $this->isGenerating = true;
        
        try {
            // Check if image file exists
            if (!$this->image->exists()) {
                throw new \Exception('Image file not found on storage');
            }
            
            $processingService = app(ImageProcessingService::class);
            $this->compressionLevels = $processingService->createCompressionLevels($this->image);
            $this->showComparison = true;
            $this->success('Compression levels generated successfully!');
        } catch (\Exception $e) {
            \Log::error('Compression levels generation failed', [
                'image_id' => $this->image->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Failed to generate compression levels: ' . $e->getMessage());
        } finally {
            $this->isGenerating = false;
        }
    }

    public function applyCompression()
    {
        try {
            // Validate quality value
            if ($this->selectedQuality < 50 || $this->selectedQuality > 95) {
                throw new \Exception('Invalid quality level selected');
            }
            
            // Check if image file exists
            if (!$this->image->exists()) {
                throw new \Exception('Image file not found on storage');
            }
            
            $processingService = app(ImageProcessingService::class);
            $result = $processingService->applyCompression($this->image, $this->selectedQuality);
            
            // Refresh the image model
            $this->image->refresh();
            
            $this->success("Compression applied! Saved {$result['compression_ratio']}% space.");
            
            // Refresh parent page
            $this->dispatch('refresh-page');
            
        } catch (\Exception $e) {
            \Log::error('Compression application failed', [
                'image_id' => $this->image->id,
                'quality' => $this->selectedQuality,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Failed to apply compression: ' . $e->getMessage());
        }
    }

    public function clearCompressionLevels()
    {
        // Clean up temporary files
        foreach ($this->compressionLevels as $level) {
            if (isset($level['path']) && \Storage::disk($this->image->disk->value)->exists($level['path'])) {
                \Storage::disk($this->image->disk->value)->delete($level['path']);
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

    private function getImageCurrentQuality(): int
    {
        // Try to estimate current quality based on compression ratio
        if ($this->image->hasCompressed() && $this->image->compressionRatio()) {
            $ratio = $this->image->compressionRatio();
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
        return $this->formatBytes($this->image->size);
    }

    public function getCurrentCompressedSizeFormatted(): string
    {
        if ($this->image->hasCompressed()) {
            return $this->formatBytes($this->image->compressed_size);
        }
        return 'Not compressed';
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2) . ' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }

    public function render()
    {
        return view('livewire.image.compression-comparison');
    }
}