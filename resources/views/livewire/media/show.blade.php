<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $media->original_name }}</h1>
                <p class="text-gray-600 mt-1">{{ $media->formattedSize }} • {{ $media->created_at->diffForHumans() }}</p>
            </div>
            
            <div class="flex items-center gap-2">
                <x-mary-button 
                    label="Back to Media" 
                    icon="o-arrow-left" 
                    link="{{ route('media.index') }}"
                    class="btn-outline"
                    wire:navigate
                />
            </div>
        </div>

        {{-- Media Display --}}
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            @if($media->media_type->value === 'image')
                <div class="aspect-auto bg-gray-100 flex items-center justify-center">
                    <img 
                        src="{{ $media->url }}" 
                        alt="{{ $media->alt_text ?? $media->original_name }}"
                        class="max-w-full h-auto max-h-96 object-contain"
                    />
                </div>
            @else
                <div class="aspect-video bg-gray-100 flex items-center justify-center">
                    <div class="text-center">
                        <x-mary-icon :name="$media->media_type->icon()" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-600">{{ $media->media_type->label() }}</p>
                    </div>
                </div>
            @endif

            {{-- Media Info --}}
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-3">File Details</h3>
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Original Name</dt>
                                <dd class="text-sm text-gray-900">{{ $media->original_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Size</dt>
                                <dd class="text-sm text-gray-900">{{ $media->formattedSize }}</dd>
                            </div>
                            @if($media->hasDimensions())
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Dimensions</dt>
                                    <dd class="text-sm text-gray-900">{{ $media->width }}×{{ $media->height }} pixels</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type</dt>
                                <dd class="text-sm text-gray-900">{{ $media->media_type->label() }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Uploaded</dt>
                                <dd class="text-sm text-gray-900">{{ $media->created_at->format('F j, Y g:i A') }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-3">Actions</h3>
                        <div class="space-y-2">
                            <x-mary-button 
                                label="View Original" 
                                icon="o-eye" 
                                link="{{ $media->url }}"
                                external
                                class="btn-primary w-full"
                            />
                            
                            <x-mary-button 
                                label="Download" 
                                icon="o-arrow-down-tray" 
                                link="{{ $media->downloadUrl }}"
                                class="btn-outline w-full"
                            />
                            
                            @if($media->isShareable)
                                <x-mary-button 
                                    label="Share" 
                                    icon="o-share" 
                                    link="{{ $media->shareableUrl }}"
                                    external
                                    class="btn-outline w-full"
                                />
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detail Actions Component --}}
        <div class="mt-6">
            <livewire:media.detail-actions :media="$media" />
        </div>
    </div>
</div>