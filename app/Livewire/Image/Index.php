<?php

namespace App\Livewire\Image;

use App\Enums\AllowedImageType;
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

    public string $filterBySize = '';

    public string $filterByDate = '';

    public string $filterByTags = '';

    public string $viewMode = 'grid';

    public int $perPage = 24;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Media::class);
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
            $mediaItem->delete(); // Model handles file deletion
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
     * View a media item in modal or new tab
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
                'size' => $media->formattedSize,
                'dimensions' => $media->hasDimensions() ? "{$media->width}x{$media->height}" : null,
                'created_at' => $media->created_at->diffForHumans(),
                'mime_type' => $media->mime_type,
                'disk' => $media->disk->value,
                'tags' => $media->tags ?? [],
                'alt_text' => $media->alt_text,
                'description' => $media->description,
                'metadata' => $media->metadata ?? [],
            ],
        ]);
    }

    /**
     * Download a media file
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
        $media->delete(); // Model handles file deletion

        $this->dispatch('media-deleted', [
            'message' => "Deleted {$filename}",
            'filename' => $filename,
        ]);
    }

    /**
     * Sort images by column
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
     * Update filter and reset pagination
     */
    public function updatedFilterBySize(): void
    {
        $this->resetPage();
    }

    /**
     * Update filter and reset pagination
     */
    public function updatedFilterByDate(): void
    {
        $this->resetPage();
    }

    /**
     * Update filter and reset pagination
     */
    public function updatedFilterByTags(): void
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
        $this->filterBySize = '';
        $this->filterByDate = '';
        $this->filterByTags = '';
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
                        ->orWhere('alt_text', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%")
                        ->orWhereJsonContains('tags', $this->search);
                });
            })
            ->when($this->filterByType, function ($query) {
                $imageType = AllowedImageType::tryFrom($this->filterByType);
                if ($imageType) {
                    $query->where('mime_type', $imageType->mimeType());
                }
            })
            ->when($this->filterBySize, function ($query) {
                match ($this->filterBySize) {
                    'small' => $query->where('size', '<', 1024 * 1024), // < 1MB
                    'medium' => $query->whereBetween('size', [1024 * 1024, 10 * 1024 * 1024]), // 1MB - 10MB
                    'large' => $query->where('size', '>', 10 * 1024 * 1024), // > 10MB
                    default => null,
                };
            })
            ->when($this->filterByDate, function ($query) {
                match ($this->filterByDate) {
                    'today' => $query->whereDate('created_at', today()),
                    'week' => $query->where('created_at', '>=', now()->subWeek()),
                    'month' => $query->where('created_at', '>=', now()->subMonth()),
                    'year' => $query->where('created_at', '>=', now()->subYear()),
                    default => null,
                };
            })
            ->when($this->filterByTags, function ($query) {
                $query->whereJsonContains('tags', $this->filterByTags);
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
        $media = Media::where('user_id', Auth::id());

        $totalSize = $media->sum('size');
        $compressedSize = $media->sum('compressed_size');
        $shareableCount = $media->where('is_shareable', true)->count();
        $totalViews = $media->sum('view_count');
        $withThumbnails = $media->whereNotNull('thumbnail_path')->count();

        return [
            'total' => $media->count(),
            'total_size' => $totalSize,
            'avg_size' => $media->avg('size') ?? 0,
            'shareable' => $shareableCount,
            'total_views' => $totalViews,
            'with_thumbnails' => $withThumbnails,
            'avg_compression' => $compressedSize && $totalSize 
                ? round((1 - $compressedSize / $totalSize) * 100, 1) 
                : 0,
            'types' => $media->select('mime_type')
                ->groupBy('mime_type')
                ->get()
                ->mapWithKeys(function ($item) {
                    $type = AllowedImageType::fromMimeType($item->mime_type);
                    return [$type?->value ?? 'unknown' => $item->mime_type];
                }),
            'by_size' => [
                'small' => $media->where('size', '<', 1024 * 1024)->count(),
                'medium' => $media->whereBetween('size', [1024 * 1024, 10 * 1024 * 1024])->count(),
                'large' => $media->where('size', '>', 10 * 1024 * 1024)->count(),
            ],
            'recent' => [
                'today' => $media->whereDate('created_at', today())->count(),
                'week' => $media->where('created_at', '>=', now()->subWeek())->count(),
                'month' => $media->where('created_at', '>=', now()->subMonth())->count(),
            ],
        ];
    }

    /**
     * Change view mode
     */
    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->perPage = match ($mode) {
            'grid' => 24,
            'list' => 15,
            'detailed' => 10,
            default => 24,
        };
        $this->resetPage();
    }

    /**
     * Bulk actions
     */
    public function bulkMakePublic(): void
    {
        $this->bulkUpdateVisibility(true);
    }

    public function bulkMakePrivate(): void
    {
        $this->bulkUpdateVisibility(false);
    }

    private function bulkUpdateVisibility(bool $isPublic): void
    {
        $media = Media::whereIn('id', $this->selectedMedia)
            ->where('user_id', Auth::id())
            ->get();

        foreach ($media as $mediaItem) {
            $this->authorize('update', $mediaItem);
            $mediaItem->update(['is_shareable' => $isPublic]);
        }

        $action = $isPublic ? 'made public' : 'made private';
        $count = $media->count();
        
        $this->dispatch('media-updated', [
            'message' => "{$count} media files {$action}",
            'count' => $count,
        ]);
    }

    /**
     * Get available tags for autocomplete
     */
    #[Computed]
    public function availableTags(): array
    {
        return Media::where('user_id', Auth::id())
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Listen for upload completion to refresh the list
     */
    #[On('upload-completed')]
    public function refreshMedia(): void
    {
        // This will trigger a re-render with fresh data
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
        return view('livewire.image.index', [
            'availableTypes' => AllowedImageType::cases(),
        ]);
    }
}
