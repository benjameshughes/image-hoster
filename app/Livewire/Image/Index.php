<?php

namespace App\Livewire\Image;

use App\Enums\AllowedImageType;
use App\Models\Image;
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

    public array $selectedImages = [];

    public bool $selectAll = false;

    public string $filterByType = '';

    public int $perPage = 12;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Image::class);
    }

    /**
     * Delete all selected images
     */
    public function deleteSelected(): void
    {
        $images = Image::whereIn('id', $this->selectedImages)
            ->where('user_id', Auth::id())
            ->get();

        foreach ($images as $image) {
            $this->authorize('delete', $image);
            $image->delete(); // Model handles file deletion
        }

        $count = $images->count();
        $this->selectedImages = [];
        $this->selectAll = false;

        $this->dispatch('images-deleted', [
            'message' => "Deleted {$count} image(s)",
            'count' => $count,
        ]);
    }

    /**
     * View an image in modal or new tab
     */
    public function view(Image $image): void
    {
        $this->authorize('view', $image);

        $this->dispatch('show-image-modal', [
            'image' => [
                'id' => $image->id,
                'url' => $image->url,
                'name' => $image->name,
                'original_name' => $image->original_name,
                'size' => $image->formatted_size,
                'dimensions' => $image->hasDimensions() ? "{$image->width}x{$image->height}" : null,
                'created_at' => $image->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Download an image
     */
    public function download(Image $image): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('download', $image);

        if (! $image->exists()) {
            $this->dispatch('error', 'File not found');

            return response()->streamDownload(function () {}, 'error.txt');
        }

        return Storage::disk($image->disk->value)->download(
            $image->path,
            $image->original_name
        );
    }

    /**
     * Delete a single image
     */
    public function delete(Image $image): void
    {
        $this->authorize('delete', $image);

        $filename = $image->original_name;
        $image->delete(); // Model handles file deletion

        $this->dispatch('image-deleted', [
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
     * Toggle select all images
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedImages = $this->images->pluck('id')->toArray();
        } else {
            $this->selectedImages = [];
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterByType = '';
        $this->selectedImages = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    /**
     * Get filtered and paginated images
     */
    #[Computed]
    public function images()
    {
        $query = Image::query()
            ->where('user_id', Auth::id())
            ->with(['user'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('original_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterByType, function ($query) {
                $imageType = AllowedImageType::tryFrom($this->filterByType);
                if ($imageType) {
                    $query->where('mime_type', $imageType->mimeType());
                }
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    /**
     * Get statistics for the current user's images
     */
    #[Computed]
    public function stats(): array
    {
        $images = Image::where('user_id', Auth::id());

        return [
            'total' => $images->count(),
            'total_size' => $images->sum('size'),
            'avg_size' => $images->avg('size') ?? 0,
            'types' => $images->select('mime_type')
                ->groupBy('mime_type')
                ->get()
                ->mapWithKeys(function ($item) {
                    $type = AllowedImageType::fromMimeType($item->mime_type);

                    return [$type?->value ?? 'unknown' => $item->mime_type];
                }),
        ];
    }

    /**
     * Listen for upload completion to refresh the list
     */
    #[On('upload-completed')]
    public function refreshImages(): void
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
