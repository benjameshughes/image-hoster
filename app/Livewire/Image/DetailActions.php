<?php

namespace App\Livewire\Image;

use App\Models\Image;
use App\Services\ImageProcessingService;
use Livewire\Component;
use Mary\Traits\Toast;

class DetailActions extends Component
{
    use Toast;

    public Image $image;

    public function mount(Image $image)
    {
        $this->image = $image;
    }

    public function reprocessImage()
    {
        try {
            // Check if image file exists
            if (!$this->image->exists()) {
                throw new \Exception('Image file not found on storage');
            }
            
            $processingService = app(ImageProcessingService::class);
            $results = $processingService->reprocessImage($this->image);
            
            // Refresh the image model
            $this->image->refresh();
            
            $message = 'Image reprocessed successfully!';
            if (isset($results['thumbnail'])) {
                $message .= ' Thumbnail regenerated.';
            }
            if (isset($results['compressed'])) {
                $message .= ' Compression updated.';
            }
            
            $this->success($message);
            
            // Refresh the page to show updated data
            $this->dispatch('refresh-page');
            
        } catch (\Exception $e) {
            \Log::error('Image reprocessing failed', [
                'image_id' => $this->image->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Failed to reprocess image: ' . $e->getMessage());
        }
    }

    public function toggleSharing()
    {
        try {
            $this->image->update([
                'is_shareable' => !$this->image->is_shareable
            ]);
            
            $this->image->refresh();
            
            $status = $this->image->is_shareable ? 'public' : 'private';
            $this->success("Image sharing set to {$status}!");
            
            // Refresh the page to show updated status
            $this->dispatch('refresh-page');
            
        } catch (\Exception $e) {
            $this->error('Failed to update sharing status: ' . $e->getMessage());
        }
    }

    public function deleteImage()
    {
        try {
            // Store image name for confirmation message
            $imageName = $this->image->original_name;
            
            // Delete the image (this will also trigger cleanup of files via model events)
            $this->image->delete();
            
            $this->success("Image '{$imageName}' deleted successfully!");
            
            // Redirect to images index
            return redirect()->route('images.index');
            
        } catch (\Exception $e) {
            $this->error('Failed to delete image: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.image.detail-actions');
    }
}