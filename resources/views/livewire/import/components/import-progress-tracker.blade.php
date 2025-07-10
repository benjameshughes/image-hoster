<div class="space-y-6">
    {{-- Progress Overview --}}
    <div class="space-y-4">
        {{-- Progress Bar --}}
        <div class="w-full">
            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                <span>Progress</span>
                <span>{{ number_format($this->progressPercentage, 1) }}%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                <div class="bg-blue-600 dark:bg-blue-500 h-2.5 rounded-full transition-all duration-300" 
                     style="width: {{ $this->progressPercentage }}%"></div>
            </div>
        </div>
        
        {{-- Statistics Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($import->processed_items) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Processed</div>
            </div>
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($import->successful_items) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Successful</div>
            </div>
            <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($import->failed_items) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
            </div>
            <div class="text-center p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($import->duplicate_items) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Duplicates</div>
            </div>
        </div>
        
        {{-- Status Information --}}
        <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center gap-2">
                <x-mary-icon name="{{ $import->status->icon() }}" class="w-4 h-4" />
                <span>
                    @if($import->status->isActive())
                        <span class="flex items-center gap-1">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            {{ $import->status->label() }}...
                        </span>
                    @else
                        Status: {{ $import->status->label() }}
                    @endif
                </span>
            </div>
            <div class="text-right">
                <div>Duration: {{ $import->formatted_duration }}</div>
                @if($this->estimatedTimeRemaining)
                    <div class="text-xs">Est. remaining: {{ $this->estimatedTimeRemaining }}</div>
                @endif
            </div>
        </div>
        
        {{-- Success Rate --}}
        @if($import->processed_items > 0)
            <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                <span>Success Rate: {{ $import->success_rate }}%</span>
                <span>{{ number_format($import->processed_items) }} / {{ number_format($import->total_items) }} items</span>
            </div>
        @endif
    </div>

    {{-- Recent Activity --}}
    @if($showRecentItems && $this->recentItems->count() > 0)
        <div class="space-y-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Activity</h3>
            <div class="space-y-2 max-h-60 overflow-y-auto">
                @foreach($this->recentItems as $item)
                    <div class="flex items-center justify-between p-3 rounded-lg transition-all duration-300 
                                {{ $item->status === 'completed' ? 'bg-green-50 dark:bg-green-900/20' : ($item->status === 'failed' ? 'bg-red-50 dark:bg-red-900/20' : 'bg-gray-50 dark:bg-gray-800') }}
                                hover:shadow-sm">
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm truncate text-gray-900 dark:text-gray-100">{{ $item->title }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                <span>{{ $item->mime_type }}</span>
                                <span>•</span>
                                <span>{{ $this->formatBytes($item->file_size) }}</span>
                                @if($item->error_message)
                                    <span>•</span>
                                    <span class="text-red-500 dark:text-red-400 truncate">{{ $item->error_message }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <x-mary-badge 
                                value="{{ ucfirst($item->status) }}" 
                                class="badge-{{ $item->status === 'completed' ? 'success' : ($item->status === 'failed' ? 'error' : 'warning') }} badge-sm" 
                            />
                            <div class="text-xs text-gray-500 dark:text-gray-400 text-right">
                                {{ $item->processed_at?->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Empty State --}}
    @if($showRecentItems && $this->recentItems->count() === 0)
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-mary-icon name="o-clock" class="w-12 h-12 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
            <p>No items processed yet</p>
            @if($import->status->value === 'running' && $import->items()->count() === 0)
                <p class="text-sm mt-1">The import is analyzing your WordPress media library...</p>
            @endif
        </div>
    @endif
</div>