{{-- Smart Display Logic for Credentials --}}
<div>
@if($hasDefaultCredential && $selectedCredential)
    {{-- Show Current Default Credential with connection testing --}}
    <x-mary-card 
        title="WordPress Connection" 
        class="border-l-4 {{ $this->connectionStatus === 'success' ? 'border-l-green-500 dark:border-l-green-400' : ($this->connectionStatus === 'failed' ? 'border-l-red-500 dark:border-l-red-400' : ($testing ? 'border-l-blue-500 dark:border-l-blue-400' : 'border-l-green-500 dark:border-l-green-400')) }}"
    >
        <div class="space-y-4">
            {{-- Credential Info --}}
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $selectedCredential->name }}</h4>
                        <x-mary-badge value="Default" class="badge-success badge-sm" />
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $selectedCredential->wordpress_url }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($this->hasSavedCredentials)
                        <x-mary-button 
                            icon="o-arrows-right-left" 
                            wire:click="$set('showCredentialForm', true)"
                            class="btn-sm btn-outline"
                            tooltip="Switch Credentials"
                        />
                    @endif
                    <x-mary-button 
                        icon="o-plus" 
                        wire:click="$set('showCredentialForm', true)"
                        class="btn-sm btn-outline"
                        tooltip="Add New Credentials"
                    />
                </div>
            </div>

            {{-- Connection Test Status --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
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
                        @elseif($hasActiveImport)
                            <x-mary-icon name="o-pause" class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                            <span class="font-medium text-orange-800 dark:text-orange-200">Testing Disabled (Import Running)</span>
                        @else
                            <x-mary-icon name="o-wifi" class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                            <span class="font-medium text-gray-800 dark:text-gray-200">Ready to Test</span>
                        @endif
                    </div>
                    
                    <x-mary-button 
                        label="Test Connection" 
                        icon="o-arrow-path"
                        wire:click="testConnection"
                        class="btn-sm btn-outline"
                        :loading="$testing"
                        loading-text="Testing..."
                        :disabled="$hasActiveImport"
                    />
                </div>

                {{-- Connection Details --}}
                @if(!empty($connectionTest) && $this->connectionStatus === 'success')
                    <div class="mt-4 space-y-3">
                        {{-- Site Info --}}
                        @if(!empty($siteInfo))
                            <div>
                                <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Site Information</h5>
                                <div class="grid grid-cols-2 gap-2 text-xs text-gray-700 dark:text-gray-300">
                                    <div><span class="font-medium">Name:</span> {{ $siteInfo['name'] ?? 'Unknown' }}</div>
                                    <div><span class="font-medium">Version:</span> {{ $siteInfo['version'] ?? 'Unknown' }}</div>
                                </div>
                            </div>
                        @endif
                        
                        {{-- Media Stats --}}
                        @if(!empty($mediaStats))
                            <div>
                                <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Media Library</h5>
                                <div class="grid grid-cols-4 gap-2 text-xs">
                                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded">
                                        <div class="font-bold text-blue-600 dark:text-blue-400">{{ $mediaStats['total'] ?? 0 }}</div>
                                        <div class="text-gray-600 dark:text-gray-400">Total</div>
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
                @elseif(!empty($connectionTest) && $this->connectionStatus === 'failed')
                    <div class="mt-4 text-sm text-red-700 dark:text-red-300">
                        <p>Connection failed. Please check your WordPress credentials and try again.</p>
                    </div>
                @endif
            </div>
        </div>
    </x-mary-card>
    
    {{-- Expandable credential management --}}
    @if($showCredentialForm)
        <x-mary-card title="Manage WordPress Credentials" subtitle="Switch between or add new WordPress connections">
            <div class="space-y-4">
                {{-- Credential Selection --}}
                @if($this->hasSavedCredentials)
                    <x-mary-select 
                        label="Switch to Saved Credentials" 
                        wire:model.live="selectedCredential"
                        wire:change="loadCredential($event.target.value)"
                        :options="$savedCredentials->map(fn($cred) => [
                            'id' => $cred->id, 
                            'name' => $cred->name . ($cred->is_default ? ' (Default)' : '') . ' - ' . $cred->wordpress_url
                        ])->prepend(['id' => '', 'name' => 'Current credentials...'])->toArray()"
                        option-value="id"
                        option-label="name"
                    />
                @endif
                
                {{-- Add New Credentials Section --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Add New Credentials</h4>
                    @include('livewire.import.components.partials.credential-form')
                </div>
                
                <div class="flex justify-end">
                    <x-mary-button 
                        label="Cancel" 
                        wire:click="$set('showCredentialForm', false)"
                        class="btn-ghost"
                    />
                </div>
            </div>
        </x-mary-card>
    @endif
    
@elseif($this->hasSavedCredentials)
    {{-- Show saved credentials when no default is set --}}
    <x-mary-card title="WordPress Credentials" subtitle="Select from your saved WordPress connections">
        <div class="space-y-4">
            {{-- Credential Selection --}}
            <x-mary-select 
                label="Saved Credentials" 
                wire:model.live="selectedCredential"
                wire:change="loadCredential($event.target.value)"
                :options="$savedCredentials->map(fn($cred) => [
                    'id' => $cred->id, 
                    'name' => $cred->name . ($cred->is_default ? ' (Default)' : '') . ' - ' . $cred->wordpress_url
                ])->prepend(['id' => '', 'name' => 'Select credentials...'])->toArray()"
                option-value="id"
                option-label="name"
            />
            
            {{-- Credential List --}}
            <div class="space-y-2">
                @foreach($savedCredentials as $credential)
                    <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 {{ $selectedCredential && $selectedCredential->id == $credential->id ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700' : '' }}">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $credential->name }}</h4>
                                @if($credential->is_default)
                                    <x-mary-badge value="Default" class="badge-primary badge-sm" />
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $credential->wordpress_url }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-500">
                                Last used {{ $credential->last_used_at?->diffForHumans() ?? 'never' }}
                                • Created {{ $credential->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if(!$selectedCredential || $selectedCredential->id != $credential->id)
                                <x-mary-button 
                                    icon="o-arrow-down-tray" 
                                    wire:click="loadCredential({{ $credential->id }})"
                                    class="btn-sm btn-outline"
                                    tooltip="Load Credential"
                                />
                            @endif
                            @if(!$credential->is_default)
                                <x-mary-button 
                                    icon="o-star" 
                                    wire:click="setCredentialAsDefault({{ $credential->id }})"
                                    class="btn-sm btn-ghost"
                                    tooltip="Set as Default"
                                />
                            @endif
                            <x-mary-button 
                                icon="o-trash" 
                                wire:click="deleteCredential({{ $credential->id }})"
                                class="btn-sm btn-ghost text-red-600 dark:text-red-400"
                                tooltip="Delete Credential"
                                wire:confirm="Are you sure you want to delete this credential?"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <x-mary-button 
                    icon="o-plus" 
                    label="Add New Credentials"
                    wire:click="$set('showCredentialForm', true)"
                    class="btn-outline btn-sm"
                />
            </div>
        </div>
    </x-mary-card>
    
    {{-- Add New Credentials Form --}}
    @if($showCredentialForm)
        <x-mary-card title="Add New WordPress Credentials">
            @include('livewire.import.components.partials.credential-form')
        </x-mary-card>
    @endif
    
@else
    {{-- No saved credentials - show setup form --}}
    <x-mary-card title="WordPress Connection" subtitle="Connect to your WordPress site to begin importing media">
        @include('livewire.import.components.partials.credential-form')
    </x-mary-card>
@endif
</div>