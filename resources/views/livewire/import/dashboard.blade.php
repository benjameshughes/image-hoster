<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">WordPress Media Importer</h1>
            <p class="text-gray-600 mt-2">Import your WordPress media library with duplicate detection and real-time progress tracking.</p>
        </div>
        
        @if($activeImport)
            <div class="flex items-center gap-2">
                <x-mary-badge value="Import Active" class="badge-info" />
                <x-mary-badge value="{{ $activeImport->status->label() }}" class="badge-{{ $activeImport->status->color() }}" />
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Import Form --}}
        <div class="lg:col-span-2">
            <x-mary-card title="Start New Import" subtitle="Connect to your WordPress site and import media files">
                <x-slot:menu>
                    @if($this->connectionStatus === 'success')
                        <x-mary-badge value="Connected" class="badge-success" />
                    @elseif($this->connectionStatus === 'failed')
                        <x-mary-badge value="Failed" class="badge-error" />
                    @else
                        <x-mary-badge value="Not Tested" class="badge-neutral" />
                    @endif
                </x-slot:menu>

                <form wire:submit="testConnection" class="space-y-4">
                    {{-- WordPress URL --}}
                    <x-mary-input 
                        label="WordPress Site URL" 
                        wire:model="wordpressUrl"
                        placeholder="https://your-wordpress-site.com"
                        hint="Enter your WordPress site URL (without trailing slash)"
                        icon="o-globe-alt"
                        :disabled="$testing || $importing"
                    />

                    {{-- Credentials --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-mary-input 
                            label="Username" 
                            wire:model="username"
                            placeholder="your-username"
                            icon="o-user"
                            :disabled="$testing || $importing"
                        />
                        <x-mary-input 
                            label="Application Password" 
                            wire:model="password"
                            type="password"
                            placeholder="xxxx xxxx xxxx xxxx"
                            hint="Generate in WordPress: Users → Profile → Application Passwords"
                            icon="o-key"
                            :disabled="$testing || $importing"
                        />
                    </div>

                    {{-- Import Name --}}
                    <x-mary-input 
                        label="Import Name" 
                        wire:model="importName"
                        placeholder="WordPress Import"
                        hint="Give your import a descriptive name for tracking"
                        icon="o-tag"
                        :disabled="$testing || $importing"
                    />
                    
                    {{-- Preset Selection --}}
                    @if(count($this->availablePresets) > 0)
                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <x-mary-select
                                    label="Load Preset"
                                    wire:model.live="selectedPresetId"
                                    wire:change="loadPreset($event.target.value)"
                                    :options="$this->availablePresets->map(fn($preset) => [
                                        'value' => $preset->id,
                                        'label' => $preset->name . ($preset->is_global ? ' (Global)' : '') . ($preset->is_default ? ' (Default)' : '')
                                    ])->prepend(['value' => '', 'label' => 'No Preset'])->toArray()"
                                    icon="o-bookmark"
                                    hint="Load saved import settings"
                                    :disabled="$testing || $importing"
                                />
                            </div>
                            <x-mary-button
                                icon="o-plus"
                                wire:click="$set('showPresetModal', true)"
                                class="btn-outline btn-sm"
                                tooltip="Save Current Settings as Preset"
                                :disabled="$testing || $importing"
                            />
                        </div>
                    @else
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <x-mary-icon name="o-light-bulb" class="w-8 h-8 text-blue-500 mx-auto mb-2" />
                            <p class="text-sm text-blue-700 mb-2">Save time with Import Presets!</p>
                            <x-mary-button
                                wire:click="$set('showPresetModal', true)"
                                icon="o-plus"
                                class="btn-sm btn-primary"
                                :disabled="$testing || $importing"
                            >
                                Create Your First Preset
                            </x-mary-button>
                        </div>
                    @endif

                    {{-- Storage Options --}}
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg space-y-4">
                        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                            <x-mary-icon name="o-server" class="w-5 h-5" />
                            Storage Options
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-mary-select
                                label="Storage Destination"
                                wire:model="storageDisk"
                                :options="[
                                    ['value' => 'spaces', 'label' => 'DigitalOcean Spaces'],
                                    ['value' => 's3', 'label' => 'AWS S3'],
                                    ['value' => 'r2', 'label' => 'Cloudflare R2'],
                                    ['value' => 'local', 'label' => 'Local Storage']
                                ]"
                                icon="o-cloud"
                                :disabled="$testing || $importing"
                            />
                            
                            <x-mary-input
                                label="Import Path Pattern"
                                wire:model="importPath"
                                placeholder="wordpress/{year}/{month}"
                                hint="Available: {year}, {month}, {day}, {user_id}"
                                icon="o-folder"
                                :disabled="$testing || $importing"
                            />
                        </div>
                    </div>

                    {{-- Processing Options --}}
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg space-y-4">
                        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                            <x-mary-icon name="o-cog" class="w-5 h-5" />
                            Processing Options
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-mary-toggle
                                label="Process Images"
                                wire:model="processImages"
                                hint="Extract metadata and optimize images"
                                :disabled="$testing || $importing"
                            />
                            
                            <x-mary-toggle
                                label="Generate Thumbnails"
                                wire:model="generateThumbnails"
                                hint="Create thumbnail versions"
                                :disabled="$testing || $importing || !$processImages"
                            />
                            
                            <x-mary-toggle
                                label="Compress Images"
                                wire:model="compressImages"
                                hint="Optimize file sizes"
                                :disabled="$testing || $importing || !$processImages"
                            />
                            
                            <x-mary-select
                                label="Duplicate Handling"
                                wire:model="duplicateStrategy"
                                :options="[
                                    ['value' => 'skip', 'label' => 'Skip Duplicates'],
                                    ['value' => 'replace', 'label' => 'Replace Existing'],
                                    ['value' => 'rename', 'label' => 'Rename & Import']
                                ]"
                                icon="o-document-duplicate"
                                :disabled="$testing || $importing"
                            />
                        </div>
                    </div>

                    {{-- Filter Options --}}
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg space-y-4">
                        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                            <x-mary-icon name="o-funnel" class="w-5 h-5" />
                            Import Filters
                        </h3>
                        
                        <div class="space-y-4">
                            {{-- Media Types --}}
                            <div>
                                <label class="text-sm font-medium text-gray-700 mb-2 block">Media Types to Import</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    @foreach(['image' => 'Images', 'video' => 'Videos', 'audio' => 'Audio', 'document' => 'Documents'] as $value => $label)
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" 
                                                wire:model="selectedMediaTypes" 
                                                value="{{ $value }}"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                :disabled="$testing || $importing">
                                            <span class="text-sm text-gray-700">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            
                            {{-- Date Range --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-mary-datetime
                                    label="From Date"
                                    wire:model="fromDate"
                                    icon="o-calendar"
                                    hint="Import media from this date"
                                    :disabled="$testing || $importing"
                                />
                                
                                <x-mary-datetime
                                    label="To Date"
                                    wire:model="toDate"
                                    icon="o-calendar"
                                    hint="Import media up to this date"
                                    :disabled="$testing || $importing"
                                />
                            </div>
                            
                            {{-- Max Items --}}
                            <x-mary-input
                                label="Maximum Items (Optional)"
                                wire:model="maxItems"
                                type="number"
                                min="1"
                                placeholder="Leave empty for all items"
                                hint="Limit the number of items to import"
                                icon="o-hashtag"
                                :disabled="$testing || $importing"
                            />
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-3 pt-6 border-t">
                        <x-mary-button 
                            type="submit"
                            icon="o-wifi"
                            class="btn-outline"
                            :loading="$testing"
                            :disabled="$importing"
                        >
                            Test Connection
                        </x-mary-button>

                        @if($this->connectionStatus === 'success')
                            <x-mary-button 
                                wire:click="startImport"
                                icon="o-arrow-down-tray"
                                class="btn-primary"
                                :loading="$importing"
                            >
                                Start Import
                            </x-mary-button>
                        @endif
                    </div>
                </form>

                {{-- Connection Test Results --}}
                @if(!empty($connectionTest))
                    <div class="mt-6 p-4 border border-gray-200 rounded-lg">
                        <h3 class="font-semibold text-gray-900 mb-3">Connection Test Results</h3>
                        
                        <div class="space-y-2">
                            @foreach($connectionTest['tests'] as $testName => $result)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $testName) }}</span>
                                    @if($result['success'])
                                        <x-mary-badge value="✓ Passed" class="badge-success badge-sm" />
                                    @else
                                        <x-mary-badge value="✗ Failed" class="badge-error badge-sm" />
                                    @endif
                                </div>
                                @if(!$result['success'] && isset($result['message']))
                                    <p class="text-xs text-red-600 ml-4">{{ $result['message'] }}</p>
                                @endif
                            @endforeach
                        </div>

                        {{-- Site Information --}}
                        @if(!empty($siteInfo))
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <h4 class="font-medium text-gray-900 mb-2">Site Information</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    @if(isset($siteInfo['name']))
                                        <div><span class="text-gray-600">Site Name:</span> <span class="font-medium">{{ $siteInfo['name'] }}</span></div>
                                    @endif
                                    @if(isset($siteInfo['description']))
                                        <div><span class="text-gray-600">Description:</span> <span class="font-medium">{{ $siteInfo['description'] }}</span></div>
                                    @endif
                                    @if(isset($siteInfo['url']))
                                        <div><span class="text-gray-600">URL:</span> <span class="font-medium">{{ $siteInfo['url'] }}</span></div>
                                    @endif
                                    @if(isset($siteInfo['wp_version']))
                                        <div><span class="text-gray-600">WordPress:</span> <span class="font-medium">{{ $siteInfo['wp_version'] }}</span></div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Media Statistics --}}
                        @if(!empty($mediaStats))
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <h4 class="font-medium text-gray-900 mb-2">Media Library Statistics</h4>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
                                    <div class="text-center p-2 bg-gray-50 rounded">
                                        <div class="font-bold text-lg text-blue-600">{{ $mediaStats['total'] ?? 0 }}</div>
                                        <div class="text-gray-600">Total Items</div>
                                    </div>
                                    @foreach($mediaStats['by_type'] ?? [] as $type => $count)
                                        <div class="text-center p-2 bg-gray-50 rounded">
                                            <div class="font-bold text-lg text-green-600">{{ $count }}</div>
                                            <div class="text-gray-600 capitalize">{{ $type }}s</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </x-mary-card>

            {{-- Active Import Progress --}}
            @if($activeImport)
                <x-mary-card title="Import Progress" subtitle="Real-time import status and progress" class="mt-6">
                    <x-slot:menu>
                        <div class="flex items-center gap-2">
                            <x-mary-button 
                                icon="o-chart-bar" 
                                link="{{ route('import.progress', $activeImport) }}"
                                class="btn-sm btn-primary"
                                tooltip="View Detailed Progress"
                                wire:navigate
                            />
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
                                />
                            @endif
                            
                            <x-mary-button 
                                icon="o-arrow-path" 
                                wire:click="refreshProgress"
                                class="btn-sm btn-ghost"
                                tooltip="Refresh"
                            />
                        </div>
                    </x-slot:menu>

                    {{-- Import Details --}}
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-semibold text-gray-900">{{ $activeImport->name }}</h3>
                            <x-mary-badge value="{{ $activeImport->status->label() }}" class="badge-{{ $activeImport->status->color() }}" />
                        </div>
                        <p class="text-sm text-gray-600">Started {{ $activeImport->created_at->diffForHumans() }}</p>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Overall Progress</span>
                            <span class="text-sm text-gray-600">{{ $this->formattedProgress }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div 
                                class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                style="width: {{ $this->progressPercentage }}%"
                            ></div>
                        </div>
                        <div class="text-right text-xs text-gray-500 mt-1">{{ number_format($this->progressPercentage, 1) }}%</div>
                    </div>

                    {{-- Statistics Grid --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-3 bg-blue-50 rounded">
                            <div class="font-bold text-xl text-blue-600">{{ $activeImport->processed_items }}</div>
                            <div class="text-sm text-blue-700">Processed</div>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded">
                            <div class="font-bold text-xl text-green-600">{{ $activeImport->successful_items }}</div>
                            <div class="text-sm text-green-700">Successful</div>
                        </div>
                        <div class="text-center p-3 bg-red-50 rounded">
                            <div class="font-bold text-xl text-red-600">{{ $activeImport->failed_items }}</div>
                            <div class="text-sm text-red-700">Failed</div>
                        </div>
                        <div class="text-center p-3 bg-yellow-50 rounded">
                            <div class="font-bold text-xl text-yellow-600">{{ $activeImport->duplicate_items }}</div>
                            <div class="text-sm text-yellow-700">Duplicates</div>
                        </div>
                    </div>

                    {{-- Time Information --}}
                    @if($activeImport->duration)
                        <div class="mt-4 text-sm text-gray-600">
                            <div class="flex justify-between">
                                <span>Duration:</span>
                                <span>{{ $activeImport->formattedDuration }}</span>
                            </div>
                            @if($activeImport->estimatedTimeRemaining)
                                <div class="flex justify-between">
                                    <span>Estimated remaining:</span>
                                    <span>{{ gmdate('H:i:s', $activeImport->estimatedTimeRemaining) }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </x-mary-card>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Quick Stats --}}
            <x-mary-card title="Quick Stats">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Total Imports</span>
                        <span class="font-semibold">{{ Auth::user()->imports()->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Active Imports</span>
                        <span class="font-semibold">{{ Auth::user()->imports()->where('status', 'running')->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Total Media</span>
                        <span class="font-semibold">{{ Auth::user()->media()->count() }}</span>
                    </div>
                </div>
            </x-mary-card>

            {{-- Recent Imports --}}
            <x-mary-card title="Recent Imports">
                <div class="space-y-3">
                    @forelse($this->recentImports as $import)
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-sm">{{ $import->name }}</div>
                                <div class="text-xs text-gray-500">{{ $import->created_at->diffForHumans() }}</div>
                            </div>
                            <x-mary-badge value="{{ $import->status->label() }}" class="badge-{{ $import->status->color() }} badge-sm" />
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">No imports yet</p>
                    @endforelse
                </div>
            </x-mary-card>

            {{-- Help & Tips --}}
            <x-mary-card title="Tips & Help">
                <div class="space-y-3 text-sm">
                    <div>
                        <h4 class="font-medium text-gray-900">Application Passwords</h4>
                        <p class="text-gray-600">Use WordPress Application Passwords for secure authentication. Generate one in your WordPress admin: Users → Profile → Application Passwords.</p>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">Duplicate Detection</h4>
                        <p class="text-gray-600">The importer automatically detects duplicates and lets you review them manually for the best results.</p>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">Import Time</h4>
                        <p class="text-gray-600">Large media libraries may take some time to import. You can pause and resume imports at any time.</p>
                    </div>
                </div>
            </x-mary-card>
        </div>
    </div>

    {{-- Auto-refresh for active imports --}}
    @if($activeImport && $activeImport->status->isActive())
        <div wire:poll.2s="refreshProgress"></div>
    @endif
    
    {{-- Preset Modal --}}
    <x-mary-modal wire:model="showPresetModal" title="Save Import Preset" separator>
        <div class="space-y-4">
            <x-mary-input
                label="Preset Name"
                wire:model="newPresetName"
                placeholder="e.g., High Quality Images Only"
                hint="Give your preset a descriptive name"
                icon="o-bookmark"
            />
            
            <x-mary-textarea
                label="Description (Optional)"
                wire:model="newPresetDescription"
                placeholder="Describe when to use this preset..."
                rows="3"
                hint="Help yourself remember when to use this preset"
            />
            
            {{-- Preview current settings --}}
            <div class="p-4 bg-gray-50 rounded-lg">
                <h4 class="font-medium text-gray-900 mb-3">Current Settings Preview</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><span class="text-gray-600">Storage:</span> <span class="font-medium">{{ ucfirst($storageDisk) }}</span></div>
                    <div><span class="text-gray-600">Process Images:</span> <span class="font-medium">{{ $processImages ? 'Yes' : 'No' }}</span></div>
                    <div><span class="text-gray-600">Media Types:</span> <span class="font-medium">{{ implode(', ', $selectedMediaTypes) }}</span></div>
                    <div><span class="text-gray-600">Duplicates:</span> <span class="font-medium">{{ ucfirst($duplicateStrategy) }}</span></div>
                    @if($maxItems)
                        <div><span class="text-gray-600">Max Items:</span> <span class="font-medium">{{ number_format($maxItems) }}</span></div>
                    @endif
                    @if($fromDate || $toDate)
                        <div><span class="text-gray-600">Date Range:</span> <span class="font-medium">{{ $fromDate ? date('M j, Y', strtotime($fromDate)) : 'Any' }} - {{ $toDate ? date('M j, Y', strtotime($toDate)) : 'Any' }}</span></div>
                    @endif
                </div>
            </div>
        </div>
        
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showPresetModal', false)" class="btn-ghost" />
            <x-mary-button label="Save Preset" wire:click="savePreset" class="btn-primary" :loading="$importing" />
        </x-slot:actions>
    </x-mary-modal>
</div>