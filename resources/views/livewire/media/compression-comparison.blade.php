<div class="bg-white rounded-lg shadow-lg p-6">
    <h3 class="text-lg font-semibold mb-4">Image Compression</h3>
    
    {{-- Current Status --}}
    <div class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-700 mb-2">Original Size</h4>
                <p class="text-xl font-bold text-gray-900">{{ $this->getOriginalSizeFormatted() }}</p>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-700 mb-2">Current Size</h4>
                <p class="text-xl font-bold text-gray-900">{{ $this->getCurrentCompressedSizeFormatted() }}</p>
            </div>
        </div>
    </div>

    {{-- Quality Selection --}}
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            Quality Level: {{ $selectedQuality }}%
        </label>
        <input 
            type="range" 
            min="50" 
            max="95" 
            step="5" 
            wire:model.live="selectedQuality"
            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
        >
        <div class="flex justify-between text-xs text-gray-500 mt-1">
            <span>50% (Smaller)</span>
            <span>95% (Larger)</span>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3 mb-6">
        <x-mary-button 
            label="Generate Preview" 
            icon="o-eye" 
            class="btn-primary"
            wire:click="generateCompressionLevels"
            wire:loading.attr="disabled"
            wire:loading.class="loading"
        />
        
        @if($showComparison)
            <x-mary-button 
                label="Apply Compression" 
                icon="o-check" 
                class="btn-success"
                wire:click="applyCompression"
                wire:loading.attr="disabled"
                wire:confirm="Are you sure you want to apply this compression level?"
            />
            
            <x-mary-button 
                label="Clear" 
                icon="o-x-mark" 
                class="btn-outline"
                wire:click="clearCompressionLevels"
            />
        @endif
    </div>

    {{-- Compression Preview --}}
    @if($showComparison && !empty($compressionLevels))
        <div class="border-t pt-6">
            <h4 class="font-medium mb-4">Compression Preview</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($compressionLevels as $level)
                    <div class="border rounded-lg p-4 {{ $level['quality'] == $selectedQuality ? 'ring-2 ring-blue-500' : '' }}">
                        <div class="aspect-square bg-gray-100 rounded mb-2 overflow-hidden">
                            <img 
                                src="{{ $level['url'] }}" 
                                alt="Quality {{ $level['quality'] }}%"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <div class="text-center">
                            <div class="font-medium">{{ $level['quality'] }}%</div>
                            <div class="text-sm text-gray-600">{{ $level['size_formatted'] }}</div>
                            <div class="text-xs text-gray-500">{{ $level['compression_ratio'] }}% smaller</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Loading State --}}
    <div wire:loading.flex wire:target="generateCompressionLevels" class="justify-center items-center py-8">
        <div class="text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
            <p class="text-gray-600">Generating compression levels...</p>
        </div>
    </div>
</div>