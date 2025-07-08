<?php

namespace App\Livewire\Media;

use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;

class DetailActions extends Component
{
    use AuthorizesRequests, Toast;

    public Media $media;

    public function mount(Media $media)
    {
        $this->authorize('view', $media);
        $this->media = $media;
    }

    public function reprocessMedia()
    {
        try {
            $this->authorize('update', $this->media);

            // Check if media file exists
            if (! $this->media->exists()) {
                throw new \Exception('Media file not found on storage');
            }

            // Only reprocess if it's an image
            if ($this->media->media_type->value === 'image') {
                $processingService = app(ImageProcessingService::class);
                $results = $processingService->reprocessImage($this->media);

                // Refresh the media model
                $this->media->refresh();

                $message = 'Media reprocessed successfully!';
                if (isset($results['thumbnail'])) {
                    $message .= ' Thumbnail regenerated.';
                }
                if (isset($results['compressed'])) {
                    $message .= ' Compression updated.';
                }

                $this->success($message);
            } else {
                $this->error('Reprocessing is only available for images.');
            }

            // Refresh the page to show updated data
            $this->dispatch('refresh-page');

        } catch (\Exception $e) {
            \Log::error('Media reprocessing failed', [
                'media_id' => $this->media->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to reprocess media: '.$e->getMessage());
        }
    }

    public function toggleSharing()
    {
        try {
            $this->authorize('update', $this->media);

            $this->media->update([
                'is_shareable' => ! $this->media->is_shareable,
            ]);

            $this->media->refresh();

            $status = $this->media->is_shareable ? 'public' : 'private';
            $this->success("Media sharing set to {$status}!");

            // Refresh the page to show updated status
            $this->dispatch('refresh-page');

        } catch (\Exception $e) {
            $this->error('Failed to update sharing status: '.$e->getMessage());
        }
    }

    public function deleteMedia()
    {
        try {
            $this->authorize('delete', $this->media);

            // Store media name for confirmation message
            $mediaName = $this->media->original_name;

            // Delete the media (this will also trigger cleanup of files via model events)
            $this->media->delete();

            $this->success("Media '{$mediaName}' deleted successfully!");

            // Redirect to media index
            return redirect()->route('media.index');

        } catch (\Exception $e) {
            $this->error('Failed to delete media: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.media.detail-actions');
    }
}
