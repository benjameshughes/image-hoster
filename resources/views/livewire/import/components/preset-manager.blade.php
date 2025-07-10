<div class="space-y-4">
    {{-- Load Existing Presets --}}
    @if($this->availablePresets->count() > 0)
        <x-mary-card title="Import Presets" subtitle="Load or manage saved import configurations">
            <div class="space-y-4">
                {{-- Preset Selection --}}
                <x-mary-select 
                    label="Select Preset" 
                    wire:model.live="selectedPresetId"
                    placeholder="Choose a preset or create new settings"
                    :options="$this->availablePresets->map(fn($preset) => [
                        'id' => $preset->id, 
                        'name' => $preset->name . ($preset->is_default ? ' (Default)' : '')
                    ])->prepend(['id' => '', 'name' => 'Custom Configuration'])->toArray()"
                    option-value="id"
                    option-label="name"
                />

                {{-- Preset List --}}
                <div class="space-y-2">
                    @foreach($this->availablePresets as $preset)
                        <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 {{ $selectedPresetId == $preset->id ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700' : '' }}">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $preset->name }}</h4>
                                    @if($preset->is_default)
                                        <x-mary-badge value="Default" class="badge-primary badge-sm" />
                                    @endif
                                </div>
                                @if($preset->description)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $preset->description }}</p>
                                @endif
                                <p class="text-xs text-gray-500 dark:text-gray-500">
                                    Created {{ $preset->created_at->diffForHumans() }}
                                    @if($preset->updated_at->ne($preset->created_at))
                                        • Updated {{ $preset->updated_at->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($selectedPresetId != $preset->id)
                                    <x-mary-button 
                                        icon="o-arrow-down-tray" 
                                        wire:click="loadPreset({{ $preset->id }})"
                                        class="btn-sm btn-outline"
                                        tooltip="Load Preset"
                                    />
                                @endif
                                @if(!$preset->is_default)
                                    <x-mary-button 
                                        icon="o-star" 
                                        wire:click="setAsDefault({{ $preset->id }})"
                                        class="btn-sm btn-ghost"
                                        tooltip="Set as Default"
                                    />
                                @endif
                                <x-mary-button 
                                    icon="o-trash" 
                                    wire:click="deletePreset({{ $preset->id }})"
                                    class="btn-sm btn-ghost text-red-600"
                                    tooltip="Delete Preset"
                                    wire:confirm="Are you sure you want to delete this preset?"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-mary-card>
    @endif

    {{-- Save Current Configuration as Preset --}}
    <x-mary-card title="Save Configuration" subtitle="Save current settings as a reusable preset">
        <div class="space-y-4">
            @if(empty($currentConfiguration))
                <div class="p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-center">
                    <x-mary-icon name="o-cog-6-tooth" class="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
                    <p class="text-gray-600 dark:text-gray-400">Configure your import settings to save them as a preset</p>
                </div>
            @else
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
                    <div class="flex items-center gap-2 text-green-800 dark:text-green-200 mb-2">
                        <x-mary-icon name="o-check-circle" class="w-5 h-5" />
                        <span class="font-medium">Configuration Ready</span>
                    </div>
                    <p class="text-sm text-green-700 dark:text-green-300">Current configuration can be saved as a preset</p>
                </div>
                
                <div class="flex justify-end">
                    <x-mary-button 
                        label="Save as Preset" 
                        icon="o-bookmark"
                        wire:click="$set('showPresetModal', true)"
                        class="btn-primary btn-sm"
                    />
                </div>
            @endif
        </div>
    </x-mary-card>

    {{-- Save Preset Modal --}}
    <x-mary-modal wire:model="showPresetModal" title="Save Import Preset">
        <div class="space-y-4">
            <x-mary-input 
                label="Preset Name" 
                wire:model="newPresetName"
                placeholder="e.g. My WordPress Import Settings"
                hint="Give your preset a descriptive name"
            />
            
            <x-mary-textarea 
                label="Description (Optional)" 
                wire:model="newPresetDescription"
                placeholder="Describe what this preset is for..."
                hint="Optional description to help you remember this preset's purpose"
                rows="3"
            />

            {{-- Configuration Preview --}}
            @if(!empty($currentConfiguration))
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Configuration Preview</label>
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-xs space-y-1 text-gray-700 dark:text-gray-300">
                        @if(isset($currentConfiguration['storage_disk']))
                            <div><span class="font-medium">Storage:</span> {{ ucfirst($currentConfiguration['storage_disk']) }}</div>
                        @endif
                        @if(isset($currentConfiguration['media_types']))
                            <div><span class="font-medium">Media Types:</span> {{ implode(', ', $currentConfiguration['media_types']) }}</div>
                        @endif
                        @if(isset($currentConfiguration['process_images']))
                            <div><span class="font-medium">Process Images:</span> {{ $currentConfiguration['process_images'] ? 'Yes' : 'No' }}</div>
                        @endif
                        @if(isset($currentConfiguration['duplicate_strategy']))
                            <div><span class="font-medium">Duplicates:</span> {{ ucfirst($currentConfiguration['duplicate_strategy']) }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showPresetModal', false)" />
            <x-mary-button label="Save Preset" class="btn-primary" wire:click="savePreset" />
        </x-slot:actions>
    </x-mary-modal>
</div>