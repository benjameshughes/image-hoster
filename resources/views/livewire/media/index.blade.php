<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Media Library</h1>
            <p class="text-gray-600 mt-2">Manage your uploaded media files from various sources.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <x-mary-button 
                label="Import from WordPress" 
                icon="o-arrow-down-tray" 
                link="{{ route('import.dashboard') }}"
                class="btn-primary"
                wire:navigate
            />
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow">
            <div class="text-2xl font-bold text-blue-600">{{ $this->stats['total'] }}</div>
            <div class="text-sm text-gray-600">Total Files</div>
        </div>
        @foreach($this->stats['by_type'] as $type => $count)
            @if($count > 0)
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-2xl font-bold text-green-600">{{ $count }}</div>
                    <div class="text-sm text-gray-600 capitalize">{{ $type }}s</div>
                </div>
            @endif
        @endforeach
        <div class="bg-white p-4 rounded-lg shadow">
            <div class="text-lg font-bold text-purple-600">{{ $this->formatBytes($this->stats['total_size']) }}</div>
            <div class="text-sm text-gray-600">Total Size</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <div class="flex-1">
            <x-mary-input 
                wire:model.live.debounce.300ms="search"
                placeholder="Search media files..."
                icon="o-magnifying-glass"
                clearable
            />
        </div>
        
        <x-mary-select 
            wire:model.live="filterByType"
            :options="$this->mediaTypeOptions"
            placeholder="Filter by type..."
            class="w-48"
        />
    </div>

    {{-- Media Grid --}}
    @if($this->media->count() > 0)
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach($this->media as $mediaItem)
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
                    {{-- Media Preview --}}
                    <div class="aspect-square bg-gray-100 rounded-t-lg flex items-center justify-center overflow-hidden">
                        @if($mediaItem->media_type->value === 'image')
                            <img 
                                src="{{ $mediaItem->url }}" 
                                alt="{{ $mediaItem->alt_text ?? $mediaItem->name }}"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            />
                        @else
                            <div class="text-center p-4">
                                <x-mary-icon :name="$mediaItem->media_type->icon()" class="w-12 h-12 text-gray-400 mx-auto mb-2" />
                                <div class="text-xs text-gray-500 uppercase font-medium">{{ $mediaItem->media_type->label() }}</div>
                            </div>
                        @endif
                    </div>
                    
                    {{-- Media Info --}}
                    <div class="p-3">
                        <div class="font-medium text-sm text-gray-900 truncate" title="{{ $mediaItem->original_name }}">
                            {{ $mediaItem->original_name }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $mediaItem->formattedSize }}
                            @if($mediaItem->hasDimensions())
                                • {{ $mediaItem->width }}×{{ $mediaItem->height }}
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            {{ $mediaItem->created_at->diffForHumans() }}
                        </div>
                        
                        {{-- Source Badge --}}
                        @if($mediaItem->source)
                            <x-mary-badge 
                                value="{{ ucfirst($mediaItem->source) }}" 
                                class="badge-sm badge-outline mt-2"
                            />
                        @endif
                        
                        {{-- Duplicate Status --}}
                        @if($mediaItem->duplicate_status && $mediaItem->duplicate_status->value !== 'unique')
                            <x-mary-badge 
                                value="{{ $mediaItem->duplicate_status->label() }}" 
                                class="badge-sm badge-{{ $mediaItem->duplicate_status->color() }} mt-1"
                            />
                        @endif
                    </div>
                    
                    {{-- Action Menu --}}
                    <div class="px-3 pb-3">
                        <div class="flex items-center gap-1">
                            <x-mary-button 
                                icon="o-eye" 
                                link="{{ $mediaItem->url }}"
                                external
                                class="btn-xs btn-ghost"
                                tooltip="View"
                            />

                            <x-mary-button
                                icon="o-clipboard"
                                link="{{ $mediaItem->shareableUrl }}"
                                class="btn-xs btn-ghost"
                                tooltip="Copy link" />

                            <x-mary-button 
                                icon="o-arrow-down-tray" 
                                link="{{ $mediaItem->downloadUrl }}"
                                class="btn-xs btn-ghost"
                                tooltip="Download"
                            />
                            @if($mediaItem->isShareable)
                                <x-mary-button 
                                    icon="o-share" 
                                    link="{{ $mediaItem->shareableUrl }}"
                                    external
                                    class="btn-xs btn-ghost"
                                    tooltip="Share"
                                />
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        {{-- Pagination --}}
        <div class="mt-8">
            {{ $this->media->links() }}
        </div>
    @else
        {{-- Empty State --}}
        <div class="text-center py-12">
            <x-mary-icon name="o-photo" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">No media files found</h3>
            <p class="text-gray-500 mb-6">
                @if($search || $filterByType)
                    Try adjusting your search or filter criteria.
                @else
                    Start by importing media from WordPress or uploading files directly.
                @endif
            </p>
            
            @if(!$search && !$filterByType)
                <div class="flex items-center justify-center gap-3">
                    <x-mary-button 
                        label="Import from WordPress" 
                        icon="o-arrow-down-tray" 
                        link="{{ route('import.dashboard') }}"
                        class="btn-primary"
                        wire:navigate
                    />
                </div>
            @endif
        </div>
    @endif
</div>

@script
<script>
// Format bytes helper function
function formatBytes(bytes) {
    if (bytes >= 1024 ** 3) return Math.round((bytes / (1024 ** 3)) * 100) / 100 + ' GB';
    if (bytes >= 1024 ** 2) return Math.round((bytes / (1024 ** 2)) * 100) / 100 + ' MB';
    if (bytes >= 1024) return Math.round((bytes / 1024) * 100) / 100 + ' KB';
    return bytes + ' B';
}

// Make formatBytes available to Livewire
window.formatBytes = formatBytes;
</script>
@endscript