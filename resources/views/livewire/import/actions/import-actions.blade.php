<div class="space-y-4">
    {{-- Active Import Status --}}
    @if($activeImport)
        <x-mary-card title="Active Import" class="border-l-4 border-l-blue-500 dark:border-l-blue-400">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $activeImport->name }}</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $activeImport->source }}</p>
                    </div>
                    <x-mary-badge 
                        value="{{ $activeImport->status->label() }}" 
                        class="badge-{{ $activeImport->status->color() }}" 
                    />
                </div>

                {{-- Progress --}}
                <livewire:import.import-status :import="$activeImport" />

                {{-- Action Buttons --}}
                <div class="flex items-center gap-2">
                    @if($activeImport->status->canBePaused())
                        <x-mary-button 
                            icon="o-pause" 
                            wire:click="pauseImport"
                            class="btn-sm btn-warning"
                            tooltip="Pause Import"
                        />
                    @endif
                    
                    @if($activeImport->status->canBeResumed())
                        <x-mary-button 
                            icon="o-play" 
                            wire:click="resumeImport"
                            class="btn-sm btn-info"
                            tooltip="Resume Import"
                        />
                    @endif
                    
                    @if($activeImport->status->canBeCancelled())
                        <x-mary-button 
                            icon="o-x-mark" 
                            wire:click="cancelImport"
                            class="btn-sm btn-error"
                            tooltip="Cancel Import"
                            wire:confirm="Are you sure you want to cancel this import?"
                        />
                    @endif

                    @if($activeImport->status->isCompleted() && $activeImport->failed_items > 0)
                        <x-mary-button 
                            icon="o-arrow-path" 
                            wire:click="retryFailedItems({{ $activeImport->id }})"
                            class="btn-sm btn-outline"
                            tooltip="Retry Failed Items"
                        />
                    @endif

                    <x-mary-button 
                        icon="o-trash" 
                        wire:click="deleteImport({{ $activeImport->id }})"
                        class="btn-sm btn-ghost text-red-600"
                        tooltip="Delete Import"
                        wire:confirm="Are you sure you want to delete this import? This action cannot be undone."
                    />
                </div>
            </div>
        </x-mary-card>
    @endif

    {{-- Start New Import --}}
    <x-mary-card title="Start Import" subtitle="Begin importing WordPress media">
        <div class="space-y-4">
            @if(!$connectionTested)
                <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                    <div class="flex items-center gap-2 text-yellow-800 dark:text-yellow-200">
                        <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5" />
                        <span class="font-medium">Connection not tested</span>
                    </div>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                        Please test your WordPress connection before starting the import.
                    </p>
                </div>
            @endif

            @if($activeImport && $activeImport->status->isActive())
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                    <div class="flex items-center gap-2 text-blue-800 dark:text-blue-200">
                        <x-mary-icon name="o-information-circle" class="w-5 h-5" />
                        <span class="font-medium">Import in progress</span>
                    </div>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        You have an active import running. Please wait for it to complete or cancel it before starting a new one.
                    </p>
                </div>
            @endif

            <div class="flex justify-end">
                <x-mary-button 
                    label="Start Import" 
                    icon="o-play"
                    wire:click="startImport"
                    class="btn-primary"
                    :loading="$importing"
                    loading-text="Starting..."
                    :disabled="!$this->canStartImport()"
                />
            </div>
        </div>
    </x-mary-card>
</div>