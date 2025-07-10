<div class="space-y-4"
     x-data="{
         importId: {{ $import->id }},
         processedItems: {{ $import->processed_items }},
         successfulItems: {{ $import->successful_items }},
         failedItems: {{ $import->failed_items }},
         duplicateItems: {{ $import->duplicate_items }},
         totalItems: {{ $import->total_items }},
         status: '{{ $import->status->value }}',
         progressPercentage: {{ $import->progress_percentage }},
         
         init() {
             // Listen to Echo events directly without Livewire
             Echo.private('import.' + this.importId)
                 .listen('.import.status.changed', (e) => {
                     this.status = e.new_status;
                     this.processedItems = e.processed_items;
                     this.successfulItems = e.successful_items;
                     this.failedItems = e.failed_items;
                     this.duplicateItems = e.duplicate_items;
                     this.totalItems = e.total_items;
                     this.updateProgress();
                 })
                 .listen('.import.item.processed', (e) => {
                     // Increment counters locally
                     this.processedItems++;
                     if (e.successful) {
                         this.successfulItems++;
                     } else {
                         this.failedItems++;
                     }
                     this.updateProgress();
                 });
         },
         
         updateProgress() {
             this.progressPercentage = this.totalItems > 0 ? Math.round((this.processedItems / this.totalItems) * 100) : 0;
         }
     }"
>
    {{-- Progress Bar --}}
    <div class="w-full">
        <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
            <span>Progress</span>
            <span x-text="progressPercentage + '%'"></span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
            <div class="bg-blue-600 dark:bg-blue-500 h-2.5 rounded-full transition-all duration-300" 
                 :style="'width: ' + progressPercentage + '%'"></div>
        </div>
    </div>
    
    {{-- Statistics --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100" x-text="processedItems"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Processed</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400" x-text="successfulItems"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Successful</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-red-600 dark:text-red-400" x-text="failedItems"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400" x-text="duplicateItems"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Duplicates</div>
        </div>
    </div>
    
    {{-- Additional Info --}}
    <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
        <span>
            @if($import->status->isActive())
                <span class="flex items-center gap-1">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    Processing...
                </span>
            @else
                Status: {{ $import->status->label() }}
            @endif
        </span>
        <span>Duration: {{ $import->formatted_duration }}</span>
    </div>
    
    {{-- Success Rate --}}
    @if($import->processed_items > 0)
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Success Rate: {{ $import->success_rate }}%
        </div>
    @endif
</div>