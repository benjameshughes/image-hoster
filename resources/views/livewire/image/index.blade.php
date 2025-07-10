<div>
    <!-- Header with Actions -->
    <x-mary-header title="{{ __('My Images') }}" subtitle="{{ __('Manage your uploaded images') }}">
        <x-slot:middle class="!justify-end">
            <x-mary-input 
                placeholder="{{ __('Search images...') }}" 
                wire:model.live.debounce="search" 
                clearable 
                icon="o-magnifying-glass" 
                class="w-64" />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="{{ __('Clear Filters') }}" wire:click="clearFilters" class="btn-ghost" icon="o-x-mark" />
            @if(count($selectedImages) > 0)
                <x-mary-button label="{{ __('Delete Selected') }} ({{ count($selectedImages) }})" 
                    wire:click="deleteSelected" 
                    class="btn-error" 
                    icon="o-trash"
                    wire:confirm="{{ __('Are you sure you want to delete the selected images?') }}" />
            @endif
        </x-slot:actions>
    </x-mary-header>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <x-mary-stat
            title="{{ __('Total Images') }}"
            description="{{ __('Your collection') }}"
            value="{{ $this->stats['total'] }}"
            icon="o-photo"
            color="text-blue-500" />
        
        <x-mary-stat
            title="{{ __('Total Size') }}"
            description="{{ __('Storage used') }}"
            value="{{ $this->formatBytes($this->stats['total_size']) }}"
            icon="o-server"
            color="text-green-500" />
        
        <x-mary-stat
            title="{{ __('Shareable') }}"
            description="{{ __('Public images') }}"
            value="{{ $this->stats['shareable'] ?? 0 }}"
            icon="o-share"
            color="text-purple-500" />
            
        <x-mary-stat
            title="{{ __('Total Views') }}"
            description="{{ __('Image views') }}"
            value="{{ $this->stats['total_views'] ?? 0 }}"
            icon="o-eye"
            color="text-orange-500" />
        
        <x-mary-stat
            title="{{ __('With Thumbnails') }}"
            description="{{ __('Processed') }}"
            value="{{ $this->stats['with_thumbnails'] ?? 0 }}"
            icon="o-photo"
            color="text-indigo-500" />
        
        <x-mary-stat
            title="{{ __('Compression') }}"
            description="{{ __('Avg saved') }}"
            value="{{ $this->stats['avg_compression'] ?? 0 }}%"
            icon="o-archive-box"
            color="text-cyan-500" />
    </div>

    <!-- Filters -->
    <x-mary-card class="mb-6">
        <div class="flex flex-wrap gap-4 items-center">
            <x-mary-select 
                placeholder="{{ __('Filter by type') }}" 
                wire:model.live="filterByType" 
                :options="collect($availableTypes)->map(fn($type) => ['id' => $type->value, 'name' => strtoupper($type->value)])->toArray()" 
                option-value="id"
                option-label="name"
                clearable />
            
            <x-mary-select 
                placeholder="{{ __('Sort by') }}" 
                wire:model.live="sortBy" 
                :options="[
                    ['id' => 'created_at', 'name' => 'Upload Date'],
                    ['id' => 'name', 'name' => 'Filename'],
                    ['id' => 'size', 'name' => 'File Size'],
                    ['id' => 'original_name', 'name' => 'Original Name']
                ]" 
                option-value="id"
                option-label="name" />
            
            <x-mary-toggle 
                label="{{ __('Newest First') }}" 
                wire:model.live="sortDirection" 
                wire:click="sortBy('{{ $sortBy }}')" />

            <x-mary-checkbox 
                label="{{ __('Select All') }}" 
                wire:model.live="selectAll" />
        </div>
    </x-mary-card>

    <!-- Images Grid -->
    @if($this->images->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($this->images as $image)
                <x-mary-card class="group hover:shadow-lg transition-all duration-200 {{ in_array($image->id, $selectedImages) ? 'ring-2 ring-primary' : '' }}">
                    <!-- Image Preview -->
                    <div class="relative aspect-square mb-4 rounded-lg overflow-hidden bg-gray-100">
                        <a href="{{ route('images.view', $image) }}">
                            <img src="{{ $image->hasThumbnail() ? $image->thumbnail_url : $image->url }}" 
                                 alt="{{ $image->alt_text ?? $image->original_name }}"
                                 class="w-full h-full object-cover cursor-pointer hover:opacity-95 transition-opacity"
                                 loading="lazy" />
                        </a>
                        
                        <!-- Overlay Actions -->
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <div class="flex gap-2">
                                <x-mary-button 
                                    icon="o-eye" 
                                    class="btn-sm btn-circle bg-white text-black"
                                    link="{{ route('images.view', $image) }}"
                                    tooltip="{{ __('View Details') }}" />

                                <x-mary-button
                                        icon="o-clipboard"
                                        class="btn-xs btn-ghost bg-white text-black"
                                        onclick="navigator.clipboard.writeText('{{ $image->url }}')"
                                        tooltip="{{ __('Copy Link') }}" />
                                
                                <x-mary-button 
                                    icon="o-trash" 
                                    class="btn-sm btn-circle bg-red-500 text-white justify-end"
                                    wire:click="delete({{ $image->id }})" 
                                    wire:confirm="{{ __('Are you sure you want to delete this image?') }}"
                                    tooltip="{{ __('Delete') }}" />
                            </div>
                        </div>

                        <!-- Selection Checkbox -->
                        <div class="absolute top-2 left-2">
                            <x-mary-checkbox 
                                wire:model.live="selectedImages" 
                                value="{{ $image->id }}"
                                class="checkbox-primary" />
                        </div>

                        <!-- Badges -->
                        <div class="absolute top-2 right-2 flex flex-col gap-1">
                            <x-mary-badge value="{{ strtoupper($image->image_type?->value ?? 'Unknown') }}" class="badge-primary badge-sm" />
                            @if($image->is_shareable)
                                <x-mary-badge value="{{ __('PUBLIC') }}" class="badge-success badge-sm" />
                            @endif
                            @if($image->hasCompressed())
                                <x-mary-badge value="{{ $image->compressionRatio() }}%" class="badge-info badge-sm" />
                            @endif
                        </div>
                    </div>

                    <!-- Image Info -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-sm truncate" title="{{ $image->original_name }}">
                                {{ $image->original_name }}
                            </h3>
                        </div>
                        
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>{{ $image->formatted_size }}</span>
                            @if($image->hasDimensions())
                                <span>{{ $image->width }}×{{ $image->height }}</span>
                            @endif
                        </div>
                        
                        <div class="text-xs text-gray-400">
                            {{ $image->created_at->diffForHumans() }}
                        </div>
                    </div>
                </x-mary-card>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $this->images->links() }}
        </div>
    @else
        <!-- Empty State -->
        <x-mary-card class="text-center py-12">
            <x-mary-icon name="o-photo" class="w-24 h-24 mx-auto text-gray-300 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No images found') }}</h3>
            <p class="text-gray-500 mb-6">
                @if($search || $filterByType)
                    {{ __('No images match your current filters. Try adjusting your search criteria.') }}
                @else
                    {{ __('You haven\'t uploaded any images yet. Start by uploading your first image!') }}
                @endif
            </p>
            
            @if($search || $filterByType)
                <x-mary-button label="{{ __('Clear Filters') }}" wire:click="clearFilters" class="btn-primary" />
            @else
                <x-mary-button label="{{ __('Upload Images') }}" link="{{ route('dashboard') }}" class="btn-primary" icon="o-cloud-arrow-up" />
            @endif
        </x-mary-card>
    @endif

    <!-- Toast Messages -->
    <x-mary-toast />
</div>

@script
<script>
    // Handle image modal events
    Livewire.on('show-image-modal', (event) => {
        const image = event.image;
        // You can implement a modal here or handle the image view as needed
        console.log('Show image modal:', image);
    });

    // Handle copy to clipboard feedback
    document.addEventListener('click', function(e) {
        if (e.target.closest('[onclick*="clipboard"]')) {
            @this.dispatch('success', { message: 'URL copied to clipboard!' });
        }
    });
</script>
@endscript