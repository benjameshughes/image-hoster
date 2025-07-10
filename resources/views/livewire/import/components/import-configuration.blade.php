<div class="space-y-6">
    {{-- Import Name --}}
    <x-mary-card title="Import Details">
        <x-mary-input 
            label="Import Name" 
            wire:model="importName"
            placeholder="Enter a name for this import"
            hint="Give your import a descriptive name"
        />
    </x-mary-card>

    {{-- Storage Configuration --}}
    <x-mary-card title="Storage Configuration" subtitle="Choose where to store imported media">
        <div class="space-y-4">
            <x-mary-select 
                label="Storage Provider" 
                wire:model.live="storageDisk"
                :options="collect($storageDisks)->map(fn($label, $value) => ['id' => $value, 'name' => $label])->values()->all()"
                option-value="id"
                option-label="name"
            />
            
            <x-mary-input 
                label="Import Path" 
                wire:model="importPath"
                placeholder="wordpress/{year}/{month}"
                hint="Use {year}, {month}, {day} placeholders for dynamic paths"
            />
        </div>
    </x-mary-card>

    {{-- Media Processing Options --}}
    <x-mary-card title="Media Processing" subtitle="Configure how media files are processed">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-checkbox 
                    label="Process Images" 
                    wire:model="processImages"
                    hint="Apply image optimization and processing"
                />
                
                <x-mary-checkbox 
                    label="Generate Thumbnails" 
                    wire:model="generateThumbnails"
                    hint="Create thumbnail versions of images"
                />
                
                <x-mary-checkbox 
                    label="Compress Images" 
                    wire:model="compressImages"
                    hint="Reduce file size while maintaining quality"
                />
                
                <x-mary-checkbox 
                    label="Skip Duplicates" 
                    wire:model="skipDuplicates"
                    hint="Avoid importing duplicate files"
                />
            </div>
            
            @if($skipDuplicates)
                <x-mary-select 
                    label="Duplicate Strategy" 
                    wire:model="duplicateStrategy"
                    :options="collect($duplicateStrategies)->map(fn($label, $value) => ['id' => $value, 'name' => $label])->values()->all()"
                    option-value="id"
                    option-label="name"
                />
            @endif
        </div>
    </x-mary-card>

    {{-- Media Type Filtering --}}
    <x-mary-card title="Media Filtering" subtitle="Choose which types of media to import">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Media Types</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach($availableMediaTypes as $type => $label)
                        <x-mary-checkbox 
                            :label="$label"
                            :value="$type"
                            wire:model.live="selectedMediaTypes"
                        />
                    @endforeach
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input 
                    label="From Date" 
                    type="date" 
                    wire:model="fromDate"
                    hint="Import media uploaded after this date"
                />
                
                <x-mary-input 
                    label="To Date" 
                    type="date" 
                    wire:model="toDate"
                    hint="Import media uploaded before this date"
                />
            </div>
            
            <x-mary-input 
                label="Maximum Items" 
                type="number" 
                wire:model="maxItems"
                placeholder="Leave empty for no limit"
                hint="Limit the number of items to import"
            />
        </div>
    </x-mary-card>

    {{-- Configuration Summary --}}
    <x-mary-card title="Configuration Summary" class="bg-gray-50 dark:bg-gray-800">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700 dark:text-gray-300">
            <div>
                <span class="font-medium">Storage:</span> {{ $storageDisks[$storageDisk] }}
            </div>
            <div>
                <span class="font-medium">Media Types:</span> {{ implode(', ', array_map(fn($type) => $availableMediaTypes[$type], $selectedMediaTypes)) }}
            </div>
            <div>
                <span class="font-medium">Processing:</span> 
                {{ $processImages ? 'Enabled' : 'Disabled' }}
                @if($processImages && $generateThumbnails), Thumbnails @endif
                @if($processImages && $compressImages), Compression @endif
            </div>
            <div>
                <span class="font-medium">Duplicates:</span> {{ $skipDuplicates ? $duplicateStrategies[$duplicateStrategy] : 'Allow' }}
            </div>
        </div>
    </x-mary-card>
</div>