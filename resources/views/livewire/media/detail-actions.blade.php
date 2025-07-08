<div class="bg-white rounded-lg shadow-lg p-6">
    <h3 class="text-lg font-semibold mb-4">Media Actions</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Edit Details --}}
        <div class="border rounded-lg p-4">
            <h4 class="font-medium mb-2">Edit Details</h4>
            <p class="text-sm text-gray-600 mb-3">Update alt text, description, and tags</p>
            <x-mary-button 
                label="Edit" 
                icon="o-pencil" 
                class="btn-primary btn-sm w-full"
                x-on:click="$wire.dispatch('open-edit-modal', { media: {{ $media->id }} })"
            />
        </div>

        {{-- Reprocess (Images only) --}}
        @if($media->media_type->value === 'image')
            <div class="border rounded-lg p-4">
                <h4 class="font-medium mb-2">Reprocess</h4>
                <p class="text-sm text-gray-600 mb-3">Regenerate thumbnails and optimize</p>
                <x-mary-button 
                    label="Reprocess" 
                    icon="o-arrow-path" 
                    class="btn-outline btn-sm w-full"
                    wire:click="reprocessMedia"
                    wire:loading.attr="disabled"
                    wire:loading.class="loading"
                />
            </div>
        @endif

        {{-- Sharing Toggle --}}
        <div class="border rounded-lg p-4">
            <h4 class="font-medium mb-2">Sharing</h4>
            <p class="text-sm text-gray-600 mb-3">
                Currently: <span class="font-medium">{{ $media->is_shareable ? 'Public' : 'Private' }}</span>
            </p>
            <x-mary-button 
                :label="$media->is_shareable ? 'Make Private' : 'Make Public'" 
                :icon="$media->is_shareable ? 'o-lock-closed' : 'o-globe-alt'" 
                :class="$media->is_shareable ? 'btn-warning' : 'btn-success'"
                class="btn-sm w-full"
                wire:click="toggleSharing"
                wire:loading.attr="disabled"
            />
        </div>

        {{-- Compression (Images only) --}}
        @if($media->media_type->value === 'image')
            <div class="border rounded-lg p-4">
                <h4 class="font-medium mb-2">Compression</h4>
                <p class="text-sm text-gray-600 mb-3">Optimize file size</p>
                <x-mary-button 
                    label="Compress" 
                    icon="o-arrow-down-circle" 
                    class="btn-outline btn-sm w-full"
                    x-on:click="$wire.dispatch('open-compression-modal', { media: {{ $media->id }} })"
                />
            </div>
        @endif

        {{-- Delete --}}
        <div class="border border-red-200 rounded-lg p-4">
            <h4 class="font-medium text-red-700 mb-2">Delete</h4>
            <p class="text-sm text-red-600 mb-3">Permanently remove this media</p>
            <x-mary-button 
                label="Delete" 
                icon="o-trash" 
                class="btn-error btn-sm w-full"
                wire:click="deleteMedia"
                wire:confirm="Are you sure you want to delete this media file? This action cannot be undone."
                wire:loading.attr="disabled"
            />
        </div>
    </div>
</div>