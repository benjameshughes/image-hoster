<div class="space-y-2">
    <x-mary-button 
        label="{{ __('Reprocess Image') }}" 
        class="btn-outline btn-sm w-full" 
        icon="o-arrow-path"
        wire:click="reprocessImage"
        wire:confirm="{{ __('This will regenerate thumbnails and compression. Continue?') }}"
        spinner="reprocessImage" />
    
    <x-mary-button 
        label="{{ $image->is_shareable ? __('Make Private') : __('Make Public') }}" 
        class="{{ $image->is_shareable ? 'btn-warning' : 'btn-success' }} btn-sm w-full" 
        icon="{{ $image->is_shareable ? 'o-lock-closed' : 'o-share' }}"
        wire:click="toggleSharing"
        spinner="toggleSharing" />
    
    <x-mary-button 
        label="{{ __('Delete Image') }}" 
        class="btn-error btn-sm w-full" 
        icon="o-trash"
        wire:click="deleteImage"
        wire:confirm="{{ __('Are you sure? This action cannot be undone.') }}"
        spinner="deleteImage" />
</div>