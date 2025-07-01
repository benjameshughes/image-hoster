<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-base-content leading-tight">
                    {{ $image->alt_text ?? $image->original_name }}
                </h2>
                <p class="text-sm text-base-content/70 mt-1">
                    {{ __('Image Details & Actions') }}
                </p>
            </div>
            <div class="flex gap-2">
                <x-mary-button 
                    label="{{ __('Back to Images') }}" 
                    link="{{ route('images.index') }}" 
                    class="btn-ghost" 
                    icon="o-arrow-left" />
                
                <x-mary-button 
                    label="{{ __('Edit') }}" 
                    class="btn-primary" 
                    icon="o-pencil"
                    onclick="document.getElementById('edit-image-modal').showModal()" />
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Main Image Display -->
                <div class="lg:col-span-2">
                    <x-mary-card class="mb-6">
                        <!-- Image Container -->
                        <div class="text-center mb-4">
                            <img src="{{ $image->url }}" 
                                 alt="{{ $image->alt_text ?? $image->original_name }}"
                                 class="max-w-full h-auto rounded-lg shadow-lg mx-auto"
                                 style="max-height: 70vh;" />
                        </div>
                        
                        <!-- Image Versions Tabs -->
                        <div class="tabs tabs-bordered justify-center">
                            <a class="tab tab-active" onclick="showVersion('original')">{{ __('Original') }}</a>
                            @if($image->hasCompressed())
                                <a class="tab" onclick="showVersion('compressed')">{{ __('Compressed') }}</a>
                            @endif
                            @if($image->hasThumbnail())
                                <a class="tab" onclick="showVersion('thumbnail')">{{ __('Thumbnail') }}</a>
                            @endif
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-wrap gap-2 justify-center mt-6">
                            <x-mary-button 
                                label="{{ __('Download Original') }}" 
                                link="{{ route('images.download', $image) }}"
                                class="btn-primary" 
                                icon="o-arrow-down-tray"
                                external />
                            
                            @if($image->hasCompressed())
                                <x-mary-button 
                                    label="{{ __('Download Compressed') }}" 
                                    link="{{ $image->compressed_url }}?download=1"
                                    class="btn-secondary" 
                                    icon="o-archive-box"
                                    external />
                            @endif
                            
                            <x-mary-button 
                                label="{{ __('Copy URL') }}" 
                                class="btn-ghost" 
                                icon="o-clipboard"
                                onclick="copyToClipboard('{{ $image->url }}')" />
                            
                            @if($image->is_shareable && $image->unique_id)
                                <x-mary-button 
                                    label="{{ __('Copy Share Link') }}" 
                                    class="btn-ghost" 
                                    icon="o-link"
                                    onclick="copyToClipboard('{{ $image->shareable_url }}')" />
                                    
                                <x-mary-button 
                                    label="{{ __('View Public') }}" 
                                    link="{{ $image->shareable_url }}"
                                    class="btn-ghost" 
                                    icon="o-share"
                                    external />
                            @endif
                            
                            <x-mary-button 
                                label="{{ __('Embed Codes') }}" 
                                class="btn-ghost" 
                                icon="o-code-bracket"
                                onclick="document.getElementById('embed-modal').showModal()" />
                        </div>
                    </x-mary-card>
                </div>

                <!-- Sidebar with Details -->
                <div class="space-y-6">
                    
                    <!-- Basic Info -->
                    <x-mary-card title="{{ __('Image Information') }}">
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('Filename') }}</label>
                                <p class="text-sm">{{ $image->original_name }}</p>
                            </div>
                            
                            @if($image->alt_text)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">{{ __('Alt Text') }}</label>
                                    <p class="text-sm">{{ $image->alt_text }}</p>
                                </div>
                            @endif
                            
                            @if($image->description)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">{{ __('Description') }}</label>
                                    <p class="text-sm">{{ $image->description }}</p>
                                </div>
                            @endif
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('File Size') }}</label>
                                <p class="text-sm">{{ $image->formatted_size }}</p>
                            </div>
                            
                            @if($image->hasDimensions())
                                <div>
                                    <label class="text-sm font-medium text-gray-500">{{ __('Dimensions') }}</label>
                                    <p class="text-sm">{{ $image->width }} × {{ $image->height }} pixels</p>
                                </div>
                            @endif
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('Format') }}</label>
                                <p class="text-sm">{{ strtoupper($image->image_type?->value ?? 'Unknown') }}</p>
                            </div>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('Uploaded') }}</label>
                                <p class="text-sm">{{ $image->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                        </div>
                    </x-mary-card>

                    <!-- Sharing & Visibility -->
                    <x-mary-card title="{{ __('Sharing & Visibility') }}">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm">{{ __('Public Sharing') }}</span>
                                <x-mary-badge 
                                    value="{{ $image->is_shareable ? __('Enabled') : __('Disabled') }}" 
                                    class="{{ $image->is_shareable ? 'badge-success' : 'badge-error' }}" />
                            </div>
                            
                            @if($image->unique_id)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">{{ __('Unique ID') }}</label>
                                    <p class="text-xs font-mono">{{ $image->unique_id }}</p>
                                </div>
                            @endif
                            
                            @if($image->slug)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">{{ __('Slug') }}</label>
                                    <p class="text-sm">{{ $image->slug }}</p>
                                </div>
                            @endif
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('View Count') }}</label>
                                <p class="text-sm">{{ number_format($image->view_count) }} {{ __('views') }}</p>
                            </div>
                            
                            @if($image->shared_at)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">{{ __('First Shared') }}</label>
                                    <p class="text-sm">{{ $image->shared_at->diffForHumans() }}</p>
                                </div>
                            @endif
                        </div>
                    </x-mary-card>

                    <!-- Processing Info -->
                    @if($image->hasThumbnail() || $image->hasCompressed())
                        <x-mary-card title="{{ __('Processing Information') }}">
                            <div class="space-y-3">
                                @if($image->hasThumbnail())
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">{{ __('Thumbnail') }}</label>
                                        <p class="text-sm">{{ $image->thumbnail_width }} × {{ $image->thumbnail_height }}px</p>
                                    </div>
                                @endif
                                
                                @if($image->hasCompressed())
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">{{ __('Compressed Size') }}</label>
                                        <p class="text-sm">{{ number_format($image->compressed_size / 1024, 1) }} KB</p>
                                    </div>
                                    
                                    @if($image->compressionRatio())
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">{{ __('Space Saved') }}</label>
                                            <p class="text-sm">{{ $image->compressionRatio() }}%</p>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </x-mary-card>
                    @endif

                    <!-- Tags -->
                    @if($image->tags && count($image->tags) > 0)
                        <x-mary-card title="{{ __('Tags') }}">
                            <div class="flex flex-wrap gap-2">
                                @foreach($image->tags as $tag)
                                    <x-mary-badge value="{{ $tag }}" class="badge-outline" />
                                @endforeach
                            </div>
                        </x-mary-card>
                    @endif

                    <!-- Compression Tools -->
                    <x-mary-card title="{{ __('Compression Tools') }}">
                        <button 
                            class="btn btn-outline w-full" 
                            onclick="openCompressionModal()">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            {{ __('Optimize Compression') }}
                        </button>
                    </x-mary-card>

                    <!-- Storage Info -->
                    <x-mary-card title="{{ __('Storage Information') }}">
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('Storage Disk') }}</label>
                                <p class="text-sm">{{ ucfirst($image->disk->value) }}</p>
                            </div>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('Directory') }}</label>
                                <p class="text-xs font-mono">{{ $image->directory ?: '/' }}</p>
                            </div>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">{{ __('File Path') }}</label>
                                <p class="text-xs font-mono break-all">{{ $image->path }}</p>
                            </div>
                            
                            @if($image->file_hash)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">{{ __('File Hash') }}</label>
                                    <p class="text-xs font-mono break-all">{{ $image->file_hash }}</p>
                                </div>
                            @endif
                        </div>
                    </x-mary-card>

                    <!-- Quick Actions -->
                    <x-mary-card title="{{ __('Quick Actions') }}">
                        <livewire:image.detail-actions :image="$image" />
                    </x-mary-card>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Image Modal -->
    <dialog id="edit-image-modal" class="modal">
        <div class="modal-box max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">{{ __('Edit Image Details') }}</h3>
                <button class="btn btn-sm btn-circle btn-ghost" onclick="document.getElementById('edit-image-modal').close()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <livewire:image.edit :image="$image" />
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Embed Codes Modal -->
    <dialog id="embed-modal" class="modal">
        <div class="modal-box max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">{{ __('Embed Codes') }}</h3>
                <button class="btn btn-sm btn-circle btn-ghost" onclick="document.getElementById('embed-modal').close()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">{{ __('HTML') }}</span>
                    </label>
                    <textarea class="textarea textarea-bordered w-full" readonly id="htmlEmbed">
&lt;img src="{{ $image->hasCompressed() ? $image->compressed_url : $image->url }}" alt="{{ $image->alt_text ?? $image->original_name }}" /&gt;
                    </textarea>
                    <button class="btn btn-xs btn-ghost mt-1" onclick="copyToClipboard(document.getElementById('htmlEmbed').value)">{{ __('Copy') }}</button>
                </div>
                
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">{{ __('Markdown') }}</span>
                    </label>
                    <textarea class="textarea textarea-bordered w-full" readonly id="markdownEmbed">
![{{ $image->alt_text ?? $image->original_name }}]({{ $image->hasCompressed() ? $image->compressed_url : $image->url }})
                    </textarea>
                    <button class="btn btn-xs btn-ghost mt-1" onclick="copyToClipboard(document.getElementById('markdownEmbed').value)">{{ __('Copy') }}</button>
                </div>
                
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">{{ __('BBCode') }}</span>
                    </label>
                    <textarea class="textarea textarea-bordered w-full" readonly id="bbcodeEmbed">
[IMG]{{ $image->hasCompressed() ? $image->compressed_url : $image->url }}[/IMG]
                    </textarea>
                    <button class="btn btn-xs btn-ghost mt-1" onclick="copyToClipboard(document.getElementById('bbcodeEmbed').value)">{{ __('Copy') }}</button>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Compression Comparison Modal -->
    <dialog id="compression-modal" class="modal">
        <div class="modal-box w-11/12 max-w-7xl h-[90vh] max-h-[90vh] p-0">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-base-100 p-6 border-b border-base-300 flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold">{{ __('Compression Optimization') }}</h3>
                    <p class="text-sm opacity-70 mt-1">{{ __('Optimize your image with different quality levels') }}</p>
                </div>
                <button class="btn btn-sm btn-circle btn-ghost" onclick="document.getElementById('compression-modal').close()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6 overflow-y-auto" style="height: calc(90vh - 100px);">
                <livewire:image.compression-comparison :image="$image" />
            </div>
        </div>
        
        <!-- Modal Backdrop -->
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Toast notifications -->
    <x-mary-toast />

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('{{ __("Copied to clipboard!") }}', 'success');
            }).catch(() => {
                showToast('{{ __("Failed to copy") }}', 'error');
            });
        }

        function showVersion(version) {
            const img = document.querySelector('img[alt="{{ $image->alt_text ?? $image->original_name }}"]');
            const urls = {
                original: '{{ $image->url }}',
                @if($image->hasCompressed())
                compressed: '{{ $image->compressed_url }}',
                @endif
                @if($image->hasThumbnail())
                thumbnail: '{{ $image->thumbnail_url }}',
                @endif
            };
            
            if (urls[version]) {
                img.src = urls[version];
            }
            
            // Update active tab
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('tab-active'));
            event.target.classList.add('tab-active');
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-top toast-center`;
            toast.innerHTML = `<div class="alert alert-${type}"><span>${message}</span></div>`;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 3000);
        }

        // Function to open compression modal
        function openCompressionModal() {
            const modal = document.getElementById('compression-modal');
            if (modal) {
                modal.showModal();
            } else {
                console.error('Compression modal not found');
                showToast('{{ __("Modal not found") }}', 'error');
            }
        }

        // Listen for page refresh events from Livewire components
        document.addEventListener('livewire:init', () => {
            Livewire.on('refresh-page', () => {
                window.location.reload();
            });
            
            Livewire.on('close-edit-modal', () => {
                document.getElementById('edit-image-modal').close();
            });
        });
    </script>
</x-app-layout>