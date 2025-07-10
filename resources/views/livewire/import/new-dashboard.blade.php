<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">WordPress Import</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Import media files from your WordPress site to cloud storage.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            @if($activeImport && $activeImport->status->isActive())
                {{-- Show only import progress when active --}}
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Import in Progress</h2>
                    
                    <livewire:import.actions.import-actions 
                        :importName="$importName"
                        :wordpressUrl="$wordpressUrl"
                        :username="$username"
                        :password="$password"
                        :configuration="$configuration"
                        :mediaStats="$mediaStats"
                        :connectionTested="$connectionTested"
                        :activeImport="$activeImport"
                    />
                </div>
            @else
                {{-- Show configuration steps when no active import --}}
                {{-- Step 1: Credentials & Connection --}}
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">1. WordPress Connection</h2>
                    
                    {{-- Credential Manager --}}
                    <livewire:import.components.credential-manager 
                        wire:model.live="wordpressUrl"
                    />
                    
                    {{-- Connection Tester (only show if no saved credentials exist) --}}
                    @if(!auth()->user()->wordPressCredentials()->exists())
                        <livewire:import.actions.test-connection-action 
                            wire:model.live="wordpressUrl"
                            wire:model.live="username"
                            wire:model.live="password"
                        />
                    @endif
                </div>

                {{-- Step 2: Import Configuration --}}
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">2. Import Configuration</h2>
                    
                    {{-- Preset Manager --}}
                    <livewire:import.components.preset-manager />
                    
                    {{-- Import Configuration --}}
                    <livewire:import.components.import-configuration 
                        wire:model.live="importName"
                    />
                </div>

                {{-- Step 3: Start Import --}}
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">3. Start Import</h2>
                    
                    <livewire:import.actions.import-actions 
                        :importName="$importName"
                        :wordpressUrl="$wordpressUrl"
                        :username="$username"
                        :password="$password"
                        :configuration="$configuration"
                        :mediaStats="$mediaStats"
                        :connectionTested="$connectionTested"
                        :activeImport="$activeImport"
                    />
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            @if(!$activeImport || !$activeImport->status->isActive())
                {{-- Show media library stats only when not importing (no duplicate with ImportStatus) --}}
                @if($connectionTested && !empty($mediaStats))
                    <x-mary-card title="Media Library Stats">
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $mediaStats['total'] ?? 0 }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Total Items</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $mediaStats['images'] ?? 0 }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Images</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $mediaStats['videos'] ?? 0 }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Videos</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $mediaStats['others'] ?? 0 }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Others</div>
                            </div>
                        </div>
                    </x-mary-card>
                @endif

                {{-- Help Section --}}
                <x-mary-card title="Need Help?">
                    <div class="space-y-3 text-sm">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-gray-100">WordPress Requirements</h4>
                            <p class="text-gray-600 dark:text-gray-400">Your WordPress site needs REST API enabled and admin credentials.</p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-gray-100">Storage Options</h4>
                            <p class="text-gray-600 dark:text-gray-400">Choose from DigitalOcean Spaces, Cloudflare R2, or local storage.</p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-gray-100">Processing</h4>
                            <p class="text-gray-600 dark:text-gray-400">Enable image processing for optimization and thumbnail generation.</p>
                        </div>
                    </div>
                </x-mary-card>
            @endif

            {{-- Recent Imports (always show) --}}
            <livewire:import.components.recent-imports-list />
        </div>
    </div>
</div>