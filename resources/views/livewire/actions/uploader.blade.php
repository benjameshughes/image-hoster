<div>
    <!-- Upload Settings Card -->
    <x-mary-card title="{{ __('Upload Settings') }}" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-mary-select 
                    label="{{ __('Storage') }}" 
                    wire:model.live="disk" 
                    :options="[
                        ['id' => 'spaces', 'name' => 'DigitalOcean Spaces'],
                        ['id' => 'r2', 'name' => 'Cloudflare R2']
                    ]" 
                    option-value="id"
                    option-label="name" />
                
                <!-- Storage Provider Status -->
                <div class="mt-2 p-2 bg-base-200 dark:bg-base-300 rounded-lg">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-base-content/70">{{ __('Provider:') }}</span>
                        <span class="font-medium">
                            @if($disk->value === 'spaces')
                                {{ __('DigitalOcean Spaces') }}
                            @elseif($disk->value === 'r2')
                                {{ __('Cloudflare R2') }}
                            @else
                                {{ __('Local Storage') }}
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs mt-1">
                        <x-mary-icon name="o-server" class="w-3 h-3 text-base-content/70" />
                        <span class="text-base-content/70">{{ __('Ready for uploads') }}</span>
                    </div>
                </div>
            </div>
            
            <div class="space-y-2">
                <x-mary-checkbox 
                    label="{{ __('Check for duplicates') }}" 
                    wire:model.live="checkDuplicates" 
                    hint="{{ __('Prevent uploading identical files') }}" />
                
                <x-mary-checkbox 
                    label="{{ __('Extract metadata') }}" 
                    wire:model.live="extractMetadata" 
                    hint="{{ __('Extract EXIF data from images') }}" />
            </div>
            
            <x-mary-input 
                label="{{ __('Max file size (MB)') }}" 
                wire:model.live="maxFileSizeMB" 
                type="number" 
                min="1" 
                max="100" 
                hint="{{ __('Maximum size per file') }}" />
        </div>
    </x-mary-card>

    <!-- File Upload Area -->
    <x-mary-form wire:submit="save">
        <div x-data="{ 
            uploading: false,
            files: [],
            showStatus: false 
        }"
        x-on:livewire-upload-start="uploading = true; showStatus = true"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-cancel="uploading = false; showStatus = false"
        x-on:livewire-upload-error="uploading = false; showStatus = false"
        x-on:upload-complete.window="showStatus = false; files = []">
        
            <x-mary-file
                wire:model="files"
                label="{{ __('Select Images') }}"
                multiple
                accept="image/*"
                hint="{{ __('Max') }} {{ $maxFileSizeMB }}{{ __('MB per file. Supported: JPEG, PNG, GIF, WebP, SVG, BMP, TIFF') }}"
                class="border-gray-300 hover:border-primary transition-colors"
                x-on:change="
                    if ($event.target.files.length > 0) {
                        showStatus = true;
                        files = Array.from($event.target.files).map(file => ({
                            name: file.name,
                            size: (file.size / 1024 / 1024).toFixed(2) + ' MB',
                            status: 'uploading'
                        }));
                    }
                "
            />

            <!-- Immediate Upload Status (JavaScript-driven) -->
            <div x-show="showStatus && !$wire.isUploading" x-transition class="mt-6">
                <x-mary-card>
                    <div class="text-center py-8">
                        <div class="flex justify-center mb-4">
                            <div class="relative">
                                <div class="w-16 h-16 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <x-mary-icon name="o-cloud-arrow-up" class="w-6 h-6 text-primary" />
                                </div>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-base-content mb-2">{{ __('Files Uploading') }}</h3>
                        <p class="text-base-content/70">{{ __('Uploading your images to') }} 
                            <span class="font-medium">
                                @if($disk->value === 'spaces')
                                    {{ __('DigitalOcean Spaces') }}
                                @elseif($disk->value === 'r2')
                                    {{ __('Cloudflare R2') }}
                                @elseif($disk->value === 's3')
                                    {{ __('Amazon S3') }}
                                @else
                                    {{ __('Local Storage') }}
                                @endif
                            </span>
                        </p>
                        
                        <!-- JavaScript-driven file list -->
                        <div class="mt-6 max-w-md mx-auto">
                            <template x-for="file in files" :key="file.name">
                                <div class="flex items-center justify-between py-2 text-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-4 h-4 border-2 border-blue-500/30 border-t-blue-500 rounded-full animate-spin"></div>
                                        <span class="text-blue-600">{{ __('Uploading') }}</span>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium" x-text="file.name"></div>
                                        <div class="text-xs text-base-content/70" x-text="file.size"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </x-mary-card>
            </div>
        </div>

        {{-- Display validation errors --}}
        @error('files')
        <x-mary-alert icon="o-exclamation-triangle" class="alert-error mt-4">
            {{ $message }}
        </x-mary-alert>
        @enderror

        {{-- Display any additional errors from our custom error handling --}}
        @if($errors->has('files.*'))
            <div class="mt-4">
                @foreach($errors->get('files.*') as $error)
                    <x-mary-alert icon="o-exclamation-triangle" class="alert-error mb-2">
                        {{ $error[0] }}
                    </x-mary-alert>
                @endforeach
            </div>
        @endif
    </x-mary-form>

    {{-- Beautiful Upload Status --}}
    @if($isUploading)
        <x-mary-card class="mt-6">
            <div class="text-center py-8">
                <div class="flex justify-center mb-4">
                    <div class="relative">
                        <div class="w-16 h-16 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <x-mary-icon name="o-cloud-arrow-up" class="w-6 h-6 text-primary" />
                        </div>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-base-content mb-2">{{ __('Files Uploading') }}</h3>
                <p class="text-base-content/70">{{ __('Uploading your images to') }} 
                    <span class="font-medium">
                        @if($disk->value === 'spaces')
                            {{ __('DigitalOcean Spaces') }}
                        @elseif($disk->value === 'r2')
                            {{ __('Cloudflare R2') }}
                        @elseif($disk->value === 's3')
                            {{ __('Amazon S3') }}
                        @else
                            {{ __('Local Storage') }}
                        @endif
                    </span>
                </p>
                
                {{-- Beautiful Processing List --}}
                @if(count($processingFiles) > 0)
                    <div class="mt-6 max-w-md mx-auto">
                        @foreach($processingFiles as $file)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <div class="flex items-center gap-3">
                                    @if($file['status'] === 'uploading')
                                        <div class="w-4 h-4 border-2 border-blue-500/30 border-t-blue-500 rounded-full animate-spin"></div>
                                        <span class="text-blue-600">{{ __('Uploading') }}</span>
                                    @elseif($file['status'] === 'processing')
                                        <div class="w-4 h-4 border-2 border-purple-500/30 border-t-purple-500 rounded-full animate-spin"></div>
                                        <span class="text-purple-600">{{ __('Processing') }}</span>
                                    @elseif($file['status'] === 'complete')
                                        <x-mary-icon name="o-check-circle" class="w-4 h-4 text-green-500" />
                                        <span class="text-green-600">{{ __('Complete') }}</span>
                                    @else
                                        <x-mary-icon name="o-x-circle" class="w-4 h-4 text-red-500" />
                                        <span class="text-red-600">{{ __('Error') }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="font-medium">{{ $file['name'] }}</div>
                                    <div class="text-xs text-base-content/70">{{ $file['size'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-mary-card>
    @endif

    {{-- Clean Uploaded Files Display --}}
    @if($uploadedFiles && count($uploadedFiles) > 0)
        <x-mary-card class="mt-8">
            <x-slot:title>
                <div class="flex items-center gap-2">
                    <x-mary-icon name="o-check-circle" class="w-5 h-5 text-success" />
                    {{ __('Upload Complete') }}
                </div>
            </x-slot:title>
            
            <x-slot:subtitle>
                {{ count($uploadedFiles) }} {{ count($uploadedFiles) > 1 ? __('files') : __('file') }} {{ __('uploaded successfully') }}
            </x-slot:subtitle>

            <x-slot:menu>
                <x-mary-button
                    wire:click="clear"
                    class="btn-outline btn-sm"
                    icon="o-trash"
                    wire:confirm="{{ __('Are you sure you want to clear all uploaded files?') }}"
                >
                    {{ __('Clear All') }}
                </x-mary-button>
            </x-slot:menu>

            <!-- Compact Statistics -->
            @if($this->uploadStats)
                <x-mary-stat
                    title="{{ $this->uploadStats['total_files'] }}"
                    description="{{ __('Files') }}"
                    value="{{ $this->uploadStats['total_size_formatted'] }}"
                    icon="o-cloud-arrow-up"
                    color="text-success"
                    class="mb-6"
                />
            @endif

            <!-- Clean File List -->
            <div class="space-y-3">
                @foreach($uploadedFiles as $index => $file)
                    <div class="flex items-center gap-4 p-4 bg-base-100 dark:bg-base-200 rounded-lg border border-base-300 dark:border-base-content/20">
                        <!-- File Preview -->
                        <div class="flex-shrink-0">
                            @if(str_starts_with($file['mime'], 'image/'))
                                <div class="relative w-16 h-16 rounded-lg overflow-hidden bg-base-200">
                                    <img
                                        src="{{ $file['url'] }}"
                                        alt="{{ $file['name'] }}"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                    />
                                    <div class="absolute inset-0 bg-green-500/10 flex items-center justify-center">
                                        <x-mary-icon name="o-check" class="w-4 h-4 text-green-600" />
                                    </div>
                                </div>
                            @else
                                <div class="w-16 h-16 rounded-lg bg-base-200 flex items-center justify-center">
                                    <x-mary-icon name="o-document" class="w-8 h-8 text-base-content/40"/>
                                </div>
                            @endif
                        </div>

                        <!-- File Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-medium text-base-content truncate" title="{{ $file['name'] }}">
                                    {{ $file['name'] }}
                                </h3>
                                <x-mary-badge 
                                    value="{{ strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) }}" 
                                    class="badge-primary badge-sm"
                                />
                            </div>
                            <div class="flex items-center gap-4 text-sm text-base-content/70">
                                <span>{{ $file['formatted_size'] ?? $this->formatFileSize($file['size']) }}</span>
                                @if(isset($file['width'], $file['height']))
                                    <span>{{ $file['width'] }}×{{ $file['height'] }}</span>
                                @endif
                                @if(isset($file['uploaded_at']))
                                    <span>{{ \Carbon\Carbon::parse($file['uploaded_at'])->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            @if(isset($file['url']))
                                <x-mary-button 
                                    icon="o-eye" 
                                    class="btn-sm btn-ghost"
                                    link="{{ $file['url'] }}"
                                    external
                                    tooltip="{{ __('View') }}" 
                                />
                                
                                <x-mary-button 
                                    icon="o-clipboard" 
                                    class="btn-sm btn-ghost"
                                    onclick="navigator.clipboard.writeText('{{ $file['url'] }}'); $dispatch('mary-toast', {description: '{{ __('URL copied to clipboard') }}', type: 'success'})"
                                    tooltip="{{ __('Copy URL') }}" 
                                />
                            @endif
                            
                            <x-mary-button 
                                icon="o-trash" 
                                class="btn-sm btn-error btn-outline"
                                wire:click="removeFile({{ $index }})"
                                wire:confirm="{{ __('Remove this file?') }}"
                                tooltip="{{ __('Delete') }}" 
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </x-mary-card>
    @endif

    {{-- Toast Messages --}}
    <x-mary-toast />
</div>