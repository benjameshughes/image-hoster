<div class="space-y-6">
    <!-- Current Status -->
    <x-mary-card title="{{ __('Compression Status') }}">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $this->getOriginalSizeFormatted() }}</div>
                <div class="text-sm text-gray-500">{{ __('Original Size') }}</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $image->hasCompressed() ? 'text-green-600' : 'text-gray-400' }}">
                    {{ $this->getCurrentCompressedSizeFormatted() }}
                </div>
                <div class="text-sm text-gray-500">{{ __('Compressed Size') }}</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $image->compressionRatio() ? 'text-purple-600' : 'text-gray-400' }}">
                    {{ $image->compressionRatio() ?? 0 }}%
                </div>
                <div class="text-sm text-gray-500">{{ __('Space Saved') }}</div>
            </div>
        </div>
    </x-mary-card>

    <!-- Compression Quality Selector -->
    <x-mary-card title="{{ __('Compression Settings') }}">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">{{ __('Quality Level') }}</span>
                    </label>
                    
                    <!-- Quality Slider -->
                    <div class="space-y-2">
                        <input 
                            type="range" 
                            min="50" 
                            max="95" 
                            step="5"
                            value="{{ $selectedQuality }}"
                            wire:model.live="selectedQuality" 
                            class="range range-primary" />
                        <div class="w-full flex justify-between text-xs px-2">
                            <span>50%</span>
                            <span>65%</span>
                            <span>75%</span>
                            <span>85%</span>
                            <span>95%</span>
                        </div>
                    </div>
                    
                    <!-- Current Quality Info -->
                    <div class="mt-2 p-3 bg-base-200 dark:bg-base-300 rounded-lg">
                        @php
                            $presets = $this->getCompressionPresets();
                            $currentPreset = $presets[$selectedQuality] ?? ['name' => 'Custom', 'description' => 'Custom quality level'];
                        @endphp
                        <div class="font-semibold text-base-content">{{ $currentPreset['name'] }} ({{ $selectedQuality }}%)</div>
                        <div class="text-sm text-base-content/70">{{ $currentPreset['description'] }}</div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <x-mary-button 
                        label="{{ __('Apply Compression') }}" 
                        class="btn-primary w-full" 
                        icon="o-arrow-down-tray"
                        wire:click="applyCompression"
                        wire:confirm="{{ __('This will replace the current compressed version. Continue?') }}" />
                    
                    <x-mary-button 
                        label="{{ __('Compare Quality Levels') }}" 
                        class="btn-outline w-full" 
                        icon="o-eye"
                        wire:click="generateCompressionLevels"
                        :disabled="$isGenerating"
                        spinner="generateCompressionLevels" />
                </div>
            </div>
        </div>
    </x-mary-card>

    <!-- Compression Comparison -->
    @if($showComparison && count($compressionLevels) > 0)
        <x-mary-card title="{{ __('Quality Comparison') }}">
            <x-slot:menu>
                <x-mary-button 
                    icon="o-x-mark" 
                    class="btn-sm btn-ghost"
                    wire:click="clearCompressionLevels"
                    tooltip="{{ __('Close comparison') }}" />
            </x-slot:menu>
            
            <div class="space-y-6">
                <!-- Original Image Reference -->
                <div class="text-center border-b pb-4">
                    <h3 class="font-semibold mb-2">{{ __('Original') }}</h3>
                    <img src="{{ $image->url }}" 
                         alt="{{ __('Original image') }}"
                         class="max-w-full h-auto rounded-lg shadow-sm mx-auto"
                         style="max-height: 200px;" />
                    <div class="text-sm text-gray-600 mt-2">
                        <strong>{{ $this->getOriginalSizeFormatted() }}</strong> - 100% Quality
                    </div>
                </div>
                
                <!-- Compression Level Comparisons -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($compressionLevels as $quality => $level)
                        <div class="border border-base-300 dark:border-base-content/20 rounded-lg p-3 {{ $quality == $selectedQuality ? 'ring-2 ring-primary bg-primary/5' : 'bg-base-100 dark:bg-base-200' }}">
                            <div class="text-center">
                                <h4 class="font-semibold mb-2">
                                    {{ $quality }}% Quality
                                    @if($quality == $selectedQuality)
                                        <span class="badge badge-primary badge-sm ml-1">{{ __('Selected') }}</span>
                                    @endif
                                </h4>
                                
                                <img src="{{ $level['url'] }}" 
                                     alt="{{ __('Compressed at') }} {{ $quality }}%"
                                     class="w-full h-auto rounded shadow-sm"
                                     style="max-height: 150px; object-fit: contain;" />
                                
                                <div class="mt-2 space-y-1 text-xs text-base-content">
                                    <div><strong>{{ $this->formatBytes($level['size']) }}</strong></div>
                                    <div class="text-success">{{ $level['compression_ratio'] }}% {{ __('saved') }}</div>
                                    <button 
                                        wire:click="$set('selectedQuality', {{ $quality }})"
                                        class="btn btn-xs btn-outline w-full mt-1">
                                        {{ __('Select') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <!-- Comparison Stats -->
                <div class="bg-base-200 dark:bg-base-300 rounded-lg p-4">
                    <h4 class="font-semibold mb-3">{{ __('Comparison Summary') }}</h4>
                    <div class="overflow-x-auto">
                        <table class="table table-sm w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Quality') }}</th>
                                    <th>{{ __('File Size') }}</th>
                                    <th>{{ __('Compression') }}</th>
                                    <th>{{ __('Savings') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="bg-primary/10">
                                    <td><strong>{{ __('Original') }}</strong></td>
                                    <td>{{ $this->getOriginalSizeFormatted() }}</td>
                                    <td>0%</td>
                                    <td>-</td>
                                </tr>
                                @foreach($compressionLevels as $quality => $level)
                                    <tr class="{{ $quality == $selectedQuality ? 'bg-primary/20' : 'hover:bg-base-100' }}">
                                        <td>
                                            {{ $quality }}%
                                            @if($quality == $selectedQuality)
                                                <span class="badge badge-primary badge-xs">{{ __('Selected') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $this->formatBytes($level['size']) }}</td>
                                        <td>{{ $level['compression_ratio'] }}%</td>
                                        <td class="text-success">
                                            {{ $this->formatBytes($image->size - $level['size']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-mary-card>
    @endif

    <!-- Visual Comparison (Side by Side) -->
    @if($showComparison && count($compressionLevels) > 0)
        <x-mary-card title="{{ __('Side-by-Side Comparison') }}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Original -->
                <div class="text-center">
                    <h3 class="font-semibold mb-2 text-primary">{{ __('Original') }}</h3>
                    <div class="border border-base-300 dark:border-base-content/20 rounded-lg p-2 bg-base-100 dark:bg-base-200">
                        <img src="{{ $image->url }}" 
                             alt="{{ __('Original image') }}"
                             class="w-full h-auto rounded" 
                             style="max-height: 300px; object-fit: contain;" />
                        <div class="mt-2 text-sm text-base-content">
                            <div class="font-semibold">{{ $this->getOriginalSizeFormatted() }}</div>
                            <div class="text-base-content/70">100% Quality</div>
                        </div>
                    </div>
                </div>
                
                <!-- Selected Compression -->
                @if(isset($compressionLevels[$selectedQuality]))
                    <div class="text-center">
                        <h3 class="font-semibold mb-2 text-success">{{ __('Compressed') }} ({{ $selectedQuality }}%)</h3>
                        <div class="border border-base-300 dark:border-base-content/20 rounded-lg p-2 bg-base-100 dark:bg-base-200">
                            <img src="{{ $compressionLevels[$selectedQuality]['url'] }}" 
                                 alt="{{ __('Compressed image') }}"
                                 class="w-full h-auto rounded" 
                                 style="max-height: 300px; object-fit: contain;" />
                            <div class="mt-2 text-sm text-base-content">
                                <div class="font-semibold">{{ $this->formatBytes($compressionLevels[$selectedQuality]['size']) }}</div>
                                <div class="text-success">{{ $compressionLevels[$selectedQuality]['compression_ratio'] }}% {{ __('smaller') }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            
            <!-- Apply Button -->
            <div class="text-center mt-4">
                <x-mary-button 
                    label="{{ __('Apply Selected Compression') }} ({{ $selectedQuality }}%)" 
                    class="btn-success" 
                    icon="o-check"
                    wire:click="applyCompression"
                    wire:confirm="{{ __('This will replace the current compressed version with') }} {{ $selectedQuality }}% {{ __('quality. Continue?') }}" />
            </div>
        </x-mary-card>
    @endif

    <!-- Loading State -->
    @if($isGenerating)
        <x-mary-card>
            <div class="text-center py-8">
                <div class="loading loading-spinner loading-lg text-primary"></div>
                <div class="mt-4 text-base-content/70">{{ __('Generating compression levels...') }}</div>
            </div>
        </x-mary-card>
    @endif
</div>