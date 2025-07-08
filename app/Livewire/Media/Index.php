<?php

declare(strict_types=1);

namespace App\Livewire\Media;

use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';


    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public array $selectedMedia = [];

    public bool $selectAll = false;

    public string $filterByType = '';

    public int $perPage = 24;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Media::class);
    }

    /**
     * Get media type options as a simple property
     */
    public function getMediaTypeOptionsProperty(): array
    {
        $options = ['' => 'All Types'];
        
        foreach (MediaType::cases() as $type) {
            $options[$type->value] = $type->label();
        }
        
        return $options;
    }

    /**
     * Delete all selected media
     */
    public function deleteSelected(): void
    {
        $media = Media::whereIn('id', $this->selectedMedia)
            ->where('user_id', Auth::id())
            ->get();

        foreach ($media as $mediaItem) {
            $this->authorize('delete', $mediaItem);
            $mediaItem->delete();
        }

        $count = $media->count();
        $this->selectedMedia = [];
        $this->selectAll = false;

        $this->dispatch('media-deleted', [
            'message' => "Deleted {$count} media file(s)",
            'count' => $count,
        ]);
    }

    /**
     * View media in modal or new tab
     */
    public function view(Media $media): void
    {
        $this->authorize('view', $media);

        $this->dispatch('show-media-modal', [
            'media' => [
                'id' => $media->id,
                'url' => $media->url,
                'name' => $media->name,
                'original_name' => $media->original_name,
                'size' => $media->formatted_size,
                'dimensions' => $media->hasDimensions() ? "{$media->width}x{$media->height}" : null,
                'created_at' => $media->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Download media file
     */
    public function download(Media $media): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('download', $media);

        if (! $media->exists()) {
            $this->dispatch('error', 'File not found');

            return response()->streamDownload(function () {}, 'error.txt');
        }

        return Storage::disk($media->disk->value)->download(
            $media->path,
            $media->original_name
        );
    }

    /**
     * Delete a single media file
     */
    public function delete(Media $media): void
    {
        $this->authorize('delete', $media);

        $filename = $media->original_name;
        $media->delete();

        $this->dispatch('media-deleted', [
            'message' => "Deleted {$filename}",
            'filename' => $filename,
        ]);
    }

    /**
     * Sort media by column
     */
    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Update search and reset pagination
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Update filter and reset pagination
     */
    public function updatedFilterByType(): void
    {
        $this->resetPage();
    }

    /**
     * Toggle select all media
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedMedia = $this->media->pluck('id')->toArray();
        } else {
            $this->selectedMedia = [];
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterByType = '';
        $this->selectedMedia = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    /**
     * Get filtered and paginated media
     */
    #[Computed]
    public function media()
    {
        $query = Media::query()
            ->where('user_id', Auth::id())
            ->with(['user'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('original_name', 'like', "%{$this->search}%")
                        ->orWhere('alt_text', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterByType !== '', function ($query) {
                $query->where('media_type', MediaType::from($this->filterByType));
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }


    /**
     * Get statistics for the current user's media
     */
    #[Computed]
    public function stats(): array
    {
        $userMedia = Auth::user()->media();

        return [
            'total' => $userMedia->count(),
            'by_type' => [
                'image' => $userMedia->where('media_type', MediaType::IMAGE)->count(),
                'video' => $userMedia->where('media_type', MediaType::VIDEO)->count(),
                'audio' => $userMedia->where('media_type', MediaType::AUDIO)->count(),
                'document' => $userMedia->where('media_type', MediaType::DOCUMENT)->count(),
                'archive' => $userMedia->where('media_type', MediaType::ARCHIVE)->count(),
                'other' => $userMedia->where('media_type', MediaType::OTHER)->count(),
            ],
            'total_size' => $userMedia->sum('size'),
        ];
    }

    /**
     * Listen for upload completion to refresh the list
     */
    #[On('upload-completed')]
    public function refreshMedia(): void
    {
        $this->dispatch('$refresh');
    }

    /**
     * Format file size for display
     */
    public function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2).' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2).' MB',
            $bytes >= 1024 => round($bytes / 1024, 2).' KB',
            default => $bytes.' B',
        };
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.media.index')
            ->layout('layouts.app');
    }
}
