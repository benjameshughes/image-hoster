<div class="space-y-4">
    @if($hasDefaultCredentials)
        {{-- Show connection status when default credentials exist --}}
        <x-mary-card 
            title="WordPress Connection Test" 
            class="border-l-4 {{ $this->connectionStatus === 'success' ? 'border-l-green-500 dark:border-l-green-400' : ($this->connectionStatus === 'failed' ? 'border-l-red-500 dark:border-l-red-400' : ($testing ? 'border-l-blue-500 dark:border-l-blue-400' : 'border-l-gray-300 dark:border-l-gray-600')) }}"
        >
            <div class="space-y-4">
                {{-- Connection Status Display --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        @if($this->connectionStatus === 'success')
                            <x-mary-icon name="o-check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                            <span class="font-medium text-green-800 dark:text-green-200">Connection Successful</span>
                        @elseif($this->connectionStatus === 'failed')
                            <x-mary-icon name="o-x-circle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                            <span class="font-medium text-red-800 dark:text-red-200">Connection Failed</span>
                        @elseif($testing)
                            <x-mary-icon name="o-arrow-path" class="w-5 h-5 text-blue-600 dark:text-blue-400 animate-spin" />
                            <span class="font-medium text-blue-800 dark:text-blue-200">Testing Connection...</span>
                        @elseif(empty($connectionTest))
                            <x-mary-icon name="o-wifi" class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                            <span class="font-medium text-gray-800 dark:text-gray-200">Preparing to Test...</span>
                        @else
                            <x-mary-icon name="o-wifi" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            <span class="font-medium text-blue-800 dark:text-blue-200">Ready to Test</span>
                        @endif
                    </div>
                    
                    <x-mary-button 
                        label="Test Again" 
                        icon="o-arrow-path"
                        wire:click="testConnection"
                        class="btn-sm btn-outline"
                        :loading="$testing"
                        loading-text="Testing..."
                    />
                </div>
                
                {{-- Connection Details --}}
                @if(!empty($connectionTest))
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        @if($this->connectionStatus === 'success')
                            {{-- Site Info --}}
                            @if(!empty($siteInfo))
                                <div class="mb-4">
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Site Information</h4>
                                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <div><span class="font-medium">Name:</span> {{ $siteInfo['name'] ?? 'Unknown' }}</div>
                                        <div><span class="font-medium">URL:</span> {{ $siteInfo['url'] ?? 'Unknown' }}</div>
                                        <div><span class="font-medium">Version:</span> {{ $siteInfo['version'] ?? 'Unknown' }}</div>
                                        <div><span class="font-medium">Language:</span> {{ $siteInfo['language'] ?? 'Unknown' }}</div>
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Media Stats --}}
                            @if(!empty($mediaStats))
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Media Library Statistics</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                                        <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded">
                                            <div class="font-bold text-blue-600 dark:text-blue-400">{{ $mediaStats['total'] ?? 0 }}</div>
                                            <div class="text-gray-600 dark:text-gray-400">Total Items</div>
                                        </div>
                                        <div class="text-center p-2 bg-green-50 dark:bg-green-900/20 rounded">
                                            <div class="font-bold text-green-600 dark:text-green-400">{{ $mediaStats['images'] ?? 0 }}</div>
                                            <div class="text-gray-600 dark:text-gray-400">Images</div>
                                        </div>
                                        <div class="text-center p-2 bg-purple-50 dark:bg-purple-900/20 rounded">
                                            <div class="font-bold text-purple-600 dark:text-purple-400">{{ $mediaStats['videos'] ?? 0 }}</div>
                                            <div class="text-gray-600 dark:text-gray-400">Videos</div>
                                        </div>
                                        <div class="text-center p-2 bg-orange-50 dark:bg-orange-900/20 rounded">
                                            <div class="font-bold text-orange-600 dark:text-orange-400">{{ $mediaStats['others'] ?? 0 }}</div>
                                            <div class="text-gray-600 dark:text-gray-400">Others</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            {{-- Error Details --}}
                            <div class="text-red-700 dark:text-red-300">
                                <p class="font-medium">Connection failed. Please check:</p>
                                <ul class="list-disc list-inside mt-2 text-sm space-y-1">
                                    <li>WordPress URL is correct and accessible</li>
                                    <li>Username and password are valid</li>
                                    <li>WordPress REST API is enabled</li>
                                    <li>Application passwords are configured</li>
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </x-mary-card>
    @else
        {{-- Show manual connection form when no default credentials --}}
        <x-mary-card title="WordPress Connection Test" subtitle="Test your WordPress site connection">
            <div class="space-y-4">
                {{-- Manual Input Fields --}}
                <x-mary-input 
                    label="WordPress URL" 
                    wire:model="wordpressUrl"
                    placeholder="https://your-wordpress-site.com"
                    icon="o-globe-alt"
                    hint="Enter your WordPress site URL"
                />
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-mary-input 
                        label="Username" 
                        wire:model="username"
                        icon="o-user"
                        hint="WordPress admin username"
                    />
                    
                    <x-mary-input 
                        label="Application Password" 
                        type="password" 
                        wire:model="password"
                        icon="o-key"
                        hint="Generate in WordPress: Users → Profile → Application Passwords"
                    />
                </div>
                
                {{-- Test Button --}}
                <div class="flex justify-end">
                    <x-mary-button 
                        label="Test Connection" 
                        icon="o-wifi"
                        wire:click="testConnection"
                        class="btn-primary"
                        :loading="$testing"
                        loading-text="Testing..."
                    />
                </div>
            </div>
        </x-mary-card>

        {{-- Connection Results --}}
        @if(!empty($connectionTest))
            <x-mary-card 
                title="Connection Results" 
                class="border-l-4 {{ $this->connectionStatus === 'success' ? 'border-l-green-500 bg-green-50 dark:bg-green-900/20 dark:border-l-green-400' : 'border-l-red-500 bg-red-50 dark:bg-red-900/20 dark:border-l-red-400' }}"
            >
                @if($this->connectionStatus === 'success')
                    <div class="space-y-4">
                        {{-- Site Info --}}
                        @if(!empty($siteInfo))
                            <div>
                                <h4 class="font-medium text-green-800 dark:text-green-200">Site Information</h4>
                                <div class="grid grid-cols-2 gap-2 mt-2 text-sm text-gray-700 dark:text-gray-300">
                                    <div><span class="font-medium">Name:</span> {{ $siteInfo['name'] ?? 'Unknown' }}</div>
                                    <div><span class="font-medium">URL:</span> {{ $siteInfo['url'] ?? 'Unknown' }}</div>
                                    <div><span class="font-medium">Version:</span> {{ $siteInfo['version'] ?? 'Unknown' }}</div>
                                    <div><span class="font-medium">Language:</span> {{ $siteInfo['language'] ?? 'Unknown' }}</div>
                                </div>
                            </div>
                        @endif
                        
                        {{-- Media Stats --}}
                        @if(!empty($mediaStats))
                            <div>
                                <h4 class="font-medium text-green-800 dark:text-green-200">Media Library Statistics</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded">
                                        <div class="font-bold text-blue-600 dark:text-blue-400">{{ $mediaStats['total'] ?? 0 }}</div>
                                        <div class="text-gray-600 dark:text-gray-400">Total Items</div>
                                    </div>
                                    <div class="text-center p-2 bg-green-50 dark:bg-green-900/20 rounded">
                                        <div class="font-bold text-green-600 dark:text-green-400">{{ $mediaStats['images'] ?? 0 }}</div>
                                        <div class="text-gray-600 dark:text-gray-400">Images</div>
                                    </div>
                                    <div class="text-center p-2 bg-purple-50 dark:bg-purple-900/20 rounded">
                                        <div class="font-bold text-purple-600 dark:text-purple-400">{{ $mediaStats['videos'] ?? 0 }}</div>
                                        <div class="text-gray-600 dark:text-gray-400">Videos</div>
                                    </div>
                                    <div class="text-center p-2 bg-orange-50 dark:bg-orange-900/20 rounded">
                                        <div class="font-bold text-orange-600 dark:text-orange-400">{{ $mediaStats['others'] ?? 0 }}</div>
                                        <div class="text-gray-600 dark:text-gray-400">Others</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-red-800 dark:text-red-200">
                        <x-mary-icon name="o-x-circle" class="w-5 h-5 inline mr-2" />
                        Connection failed. Please check your credentials and try again.
                    </div>
                @endif
            </x-mary-card>
        @endif
    @endif
</div>