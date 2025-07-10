<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Import Progress</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Monitor your WordPress import progress in real-time.</p>
        </div>
        
        <div>
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Progress Area --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Import Overview --}}
                <x-mary-card title="Import Overview" class="mb-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium text-lg text-gray-900 dark:text-gray-100">{{ $import->name }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $import->source }}</p>
                            </div>
                            <x-mary-badge value="{{ $import->status->label() }}" class="badge-{{ $import->status->color() }}" />
                        </div>
                        
                        {{-- Progress Tracker --}}
                        <livewire:import.components.import-progress-tracker 
                            :import="$import"
                            :showEstimatedTime="true"
                            :showRecentItems="true"
                        />
                    </div>
                </x-mary-card>

                {{-- Import Controls --}}
                <x-mary-card title="Import Controls">
                    <livewire:import.components.import-controls :import="$import" />
                </x-mary-card>

                {{-- Connection Status --}}
                @if($import->status->isActive())
                    <x-mary-card title="Connection Status" class="bg-green-50 dark:bg-green-900/20">
                        <div class="flex items-center gap-2 text-sm">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-gray-900 dark:text-gray-100">Real-time updates active</span>
                            <span class="text-gray-500 dark:text-gray-400">• Last updated: {{ $import->updated_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Listening for events on channels: import.{{ $import->id }}
                        </div>
                    </x-mary-card>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Quick Stats --}}
                <x-mary-card title="Quick Stats">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Items:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($import->total_items) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Progress:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($import->progress_percentage, 1) }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Success Rate:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $import->success_rate }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Duration:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $import->formatted_duration }}</span>
                        </div>
                    </div>
                </x-mary-card>

                {{-- Import Configuration --}}
                <x-mary-card title="Configuration">
                    <div class="space-y-2 text-sm">
                        @if($import->config)
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Storage:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ ucfirst($import->config['storage_disk'] ?? 'Unknown') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">WordPress URL:</span>
                                <span class="truncate ml-2 text-gray-900 dark:text-gray-100">{{ $import->config['wordpress_url'] ?? 'Unknown' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Media Types:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ implode(', ', $import->config['media_types'] ?? []) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Process Images:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ ($import->config['process_images'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                        @endif
                    </div>
                </x-mary-card>

                {{-- Help Section --}}
                <x-mary-card title="Import Status">
                    <div class="space-y-3 text-sm">
                        @if($import->status->isActive())
                            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                <span>Import is actively running</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">Items are being processed in the background. You can pause or cancel the import at any time.</p>
                        @elseif($import->status->value === 'paused')
                            <div class="flex items-center gap-2 text-yellow-600 dark:text-yellow-400">
                                <x-mary-icon name="o-pause" class="w-4 h-4" />
                                <span>Import is paused</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">The import has been paused. Click resume to continue processing.</p>
                        @elseif($import->status->value === 'completed')
                            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                <x-mary-icon name="o-check-circle" class="w-4 h-4" />
                                <span>Import completed successfully</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">All items have been processed. Check the statistics above for details.</p>
                        @elseif($import->status->value === 'failed')
                            <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                                <x-mary-icon name="o-x-circle" class="w-4 h-4" />
                                <span>Import failed</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">The import encountered an error. You can retry failed items or start a new import.</p>
                        @elseif($import->status->value === 'cancelled')
                            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                <x-mary-icon name="o-stop" class="w-4 h-4" />
                                <span>Import was cancelled</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">The import was cancelled before completion.</p>
                        @endif
                    </div>
                </x-mary-card>
            </div>
        </div>

        {{-- Debug Information (only in development) --}}
        @if(app()->environment('local'))
            <x-mary-card title="Debug Information" class="mt-6">
                <div class="text-xs text-gray-600 dark:text-gray-400 font-mono">
                    <div>Import ID: {{ $import->id }}</div>
                    <div>Status: {{ $import->status->value }}</div>
                    <div>Total Items in DB: {{ $import->items()->count() }}</div>
                    <div>Pending Items: {{ $import->pendingItems()->count() }}</div>
                    <div>Last Updated: {{ $import->updated_at->format('Y-m-d H:i:s') }}</div>
                    <div>Component Last Refreshed: {{ now()->format('Y-m-d H:i:s') }}</div>
                    @if($import->error_message)
                        <div class="text-red-600 dark:text-red-400 mt-2">Error: {{ $import->error_message }}</div>
                    @endif
                </div>
            </x-mary-card>
        @endif

    @else
        {{-- No Import Found --}}
        <div class="text-center py-12">
            <x-mary-icon name="o-exclamation-triangle" class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Import Not Found</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">The import you're looking for doesn't exist or has been deleted.</p>
            <x-mary-button 
                label="Back to Dashboard" 
                icon="o-arrow-left" 
                link="{{ route('import.dashboard') }}"
                class="btn-primary"
                wire:navigate
            />
        </div>
    @endif
</div>