<div>
    @if($recentImports && $recentImports->count() > 0)
        <x-mary-card title="Recent Imports" subtitle="Your previous WordPress imports">
            <div class="space-y-3">
                @foreach($recentImports as $import)
                    <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $import->name }}</h4>
                                <x-mary-badge 
                                    value="{{ $import->status->label() }}" 
                                    class="badge-{{ $import->status->color() }} badge-sm" 
                                />
                            </div>
                            
                            <div class="flex items-center gap-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                <span>{{ $import->total_items }} items</span>
                                @if($import->processed_items > 0)
                                    <span>{{ $import->successful_items }} successful</span>
                                    @if($import->failed_items > 0)
                                        <span class="text-red-600 dark:text-red-400">{{ $import->failed_items }} failed</span>
                                    @endif
                                @endif
                                <span>{{ $import->created_at->diffForHumans() }}</span>
                            </div>
                            
                            @if($import->status->isActive())
                                <div class="mt-2">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all duration-300" 
                                             style="width: {{ $import->progress_percentage }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <span>{{ $import->processed_items }} / {{ $import->total_items }}</span>
                                        <span>{{ $import->progress_percentage }}%</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                        
                        <div class="flex items-center gap-2 ml-4">
                            @if($import->status->isActive())
                                <x-mary-button 
                                    icon="o-eye" 
                                    link="{{ route('import.progress', $import) }}"
                                    class="btn-sm btn-outline"
                                    tooltip="View Progress"
                                />
                            @else
                                <x-mary-button 
                                    icon="o-document-duplicate" 
                                    wire:click="duplicateImport({{ $import->id }})"
                                    class="btn-sm btn-outline"
                                    tooltip="Copy Settings"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($recentImports && $recentImports->count() >= $limit)
                <div class="text-center mt-4">
                    <x-mary-button 
                        label="View All Imports" 
                        link="{{ route('import.dashboard') }}"
                        class="btn-sm btn-outline"
                    />
                </div>
            @endif
        </x-mary-card>
    @else
        <x-mary-card title="Recent Imports">
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <x-mary-icon name="o-document-text" class="w-12 h-12 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
                <p>No imports yet</p>
                <p class="text-sm">Start your first WordPress import above</p>
            </div>
        </x-mary-card>
    @endif
</div>