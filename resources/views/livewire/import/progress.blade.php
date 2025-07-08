<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Import Progress</h1>
            <p class="text-gray-600 mt-2">Monitor your WordPress import progress in real-time.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <x-mary-button 
                icon="o-arrow-path" 
                wire:click="$refresh"
                class="btn-ghost"
                tooltip="Refresh"
            />
            <x-mary-button 
                label="Back to Dashboard" 
                icon="o-arrow-left" 
                link="{{ route('import.dashboard') }}"
                class="btn-outline"
                wire:navigate
            />
        </div>
    </div>

    @if($import)
        {{-- Import Overview --}}
        <x-mary-card title="Import Overview" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $import->total_items }}</div>
                    <div class="text-sm text-blue-700">Total Items</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $import->processed_items }}</div>
                    <div class="text-sm text-green-700">Processed</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">{{ $import->items()->count() }}</div>
                    <div class="text-sm text-yellow-700">Items Discovered</div>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">{{ number_format($import->progressPercentage, 1) }}%</div>
                    <div class="text-sm text-purple-700">Complete</div>
                </div>
            </div>

            {{-- Progress Bar --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">Overall Progress</span>
                    <span class="text-sm text-gray-600">{{ $import->processed_items }} / {{ $import->total_items }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div 
                        class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-500" 
                        style="width: {{ $import->progressPercentage }}%"
                    ></div>
                </div>
            </div>

            {{-- Status and Controls --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-mary-badge value="{{ $import->status->label() }}" class="badge-{{ $import->status->color() }}" />
                    @if($import->status->isActive())
                        <div class="flex items-center gap-1 text-sm text-gray-600">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            Active
                        </div>
                    @endif
                </div>
                
                <div class="flex items-center gap-2">
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
                    
                    @if($import->status->canBeCancelled())
                        <x-mary-button 
                            icon="o-x-mark" 
                            wire:click="cancelImport"
                            class="btn-sm btn-error"
                            tooltip="Cancel Import"
                        />
                    @endif
                </div>
            </div>
        </x-mary-card>

        {{-- Detailed Statistics --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Processing Statistics --}}
            <x-mary-card title="Processing Statistics">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Successful</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-green-600">{{ $import->successful_items }}</span>
                            @if($import->processed_items > 0)
                                <span class="text-xs text-gray-500">({{ number_format($import->successRate, 1) }}%)</span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Failed</span>
                        <span class="font-semibold text-red-600">{{ $import->failed_items }}</span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Duplicates</span>
                        <span class="font-semibold text-yellow-600">{{ $import->duplicate_items }}</span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Pending</span>
                        <span class="font-semibold text-blue-600">{{ $import->pendingItems()->count() }}</span>
                    </div>
                </div>
            </x-mary-card>

            {{-- Time Information --}}
            <x-mary-card title="Time Information">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Started</span>
                        <span class="font-semibold">{{ $import->started_at ? $import->started_at->format('M j, Y g:i A') : 'Not started' }}</span>
                    </div>
                    
                    @if($import->duration)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Duration</span>
                            <span class="font-semibold">{{ $import->formattedDuration }}</span>
                        </div>
                    @endif
                    
                    @if($import->estimatedTimeRemaining && $import->status->isActive())
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Estimated Remaining</span>
                            <span class="font-semibold">{{ gmdate('H:i:s', $import->estimatedTimeRemaining) }}</span>
                        </div>
                    @endif
                    
                    @if($import->completed_at)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Completed</span>
                            <span class="font-semibold">{{ $import->completed_at->format('M j, Y g:i A') }}</span>
                        </div>
                    @endif
                </div>
            </x-mary-card>
        </div>

        {{-- Recent Activity --}}
        <x-mary-card title="Recent Activity">
            <div class="space-y-3">
                @forelse($import->items()->latest()->limit(10)->get() as $item)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex-1">
                            <div class="font-medium text-sm">{{ $item->title }}</div>
                            <div class="text-xs text-gray-500">{{ $item->mime_type }} • {{ $this->formatBytes($item->file_size) }}</div>
                        </div>
                        <div class="text-right">
                            <x-mary-badge 
                                value="{{ ucfirst($item->status) }}" 
                                class="badge-{{ $item->status === 'completed' ? 'success' : ($item->status === 'failed' ? 'error' : 'warning') }} badge-sm" 
                            />
                            <div class="text-xs text-gray-500 mt-1">{{ $item->updated_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <x-mary-icon name="o-clock" class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                        <p>No items processed yet</p>
                        @if($import->status->value === 'running' && $import->items()->count() === 0)
                            <p class="text-sm mt-1">The import is analyzing your WordPress media library...</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </x-mary-card>

        {{-- Debug Information (only in development) --}}
        @if(app()->environment('local'))
            <x-mary-card title="Debug Information" class="mt-6">
                <div class="text-xs text-gray-600 font-mono">
                    <div>Import ID: {{ $import->id }}</div>
                    <div>Status: {{ $import->status->value }}</div>
                    <div>Total Items in DB: {{ $import->items()->count() }}</div>
                    <div>Pending Items: {{ $import->pendingItems()->count() }}</div>
                    <div>Last Updated: {{ $import->updated_at->format('Y-m-d H:i:s') }}</div>
                    @if($import->error_message)
                        <div class="text-red-600 mt-2">Error: {{ $import->error_message }}</div>
                    @endif
                </div>
            </x-mary-card>
        @endif

    @else
        {{-- No Import Found --}}
        <div class="text-center py-12">
            <x-mary-icon name="o-exclamation-triangle" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">Import Not Found</h3>
            <p class="text-gray-500 mb-6">The import you're looking for doesn't exist or has been deleted.</p>
            <x-mary-button 
                label="Back to Dashboard" 
                icon="o-arrow-left" 
                link="{{ route('import.dashboard') }}"
                class="btn-primary"
                wire:navigate
            />
        </div>
    @endif

    {{-- Auto-refresh for active imports --}}
    @if($import && $import->status->isActive())
        <div wire:poll.3s="$refresh"></div>
    @endif
</div>