<div class="flex items-center gap-2 flex-wrap">
    {{-- Pause/Resume Controls --}}
    @if($import->status->canBePaused())
        <x-mary-button 
            icon="o-pause" 
            wire:click="pauseImport"
            class="btn-sm btn-warning"
            tooltip="Pause Import"
        />
    @endif
    
    @if($import->status->canBeResumed())
        <x-mary-button 
            icon="o-play" 
            wire:click="resumeImport"
            class="btn-sm btn-info"
            tooltip="Resume Import"
        />
    @endif
    
    {{-- Cancel Control --}}
    @if($import->status->canBeCancelled())
        <x-mary-button 
            icon="o-x-mark" 
            wire:click="cancelImport"
            class="btn-sm btn-error"
            tooltip="Cancel Import"
            wire:confirm="Are you sure you want to cancel this import?"
        />
    @endif

    {{-- Retry Failed Items --}}
    @if($import->status->isCompleted() && $import->failed_items > 0)
        <x-mary-button 
            icon="o-arrow-path" 
            wire:click="retryFailedItems"
            class="btn-sm btn-outline"
            tooltip="Retry Failed Items"
        />
    @endif

    {{-- View Progress (if not on progress page) --}}
    @if(!request()->routeIs('import.progress'))
        <x-mary-button 
            icon="o-eye" 
            link="{{ route('import.progress', $import) }}"
            class="btn-sm btn-outline"
            tooltip="View Detailed Progress"
        />
    @endif

    {{-- Delete Import --}}
    <x-mary-button 
        icon="o-trash" 
        wire:click="deleteImport"
        class="btn-sm btn-ghost text-red-600"
        tooltip="Delete Import"
        wire:confirm="Are you sure you want to delete this import? This action cannot be undone."
    />
</div>