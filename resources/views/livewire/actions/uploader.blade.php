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
                        ['id' => 's3', 'name' => 'Amazon S3'],
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
                            @elseif($disk->value === 's3')
                                {{ __('Amazon S3') }}
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

    <x-mary-form wire:submit="save">
        <div
                x-data="{
                uploading: false,
                progress: 0,
                uploadStarted: false,
                currentFile: '',
                totalFiles: 0,
                processedFiles: 0,
                storageProgress: {},
                selectedDisk: '{{ $disk->value }}'
            }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-cancel="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                {{-- Listen to our custom events from the Livewire component --}}
                x-on:upload-started="uploadStarted = true; totalFiles = $event.detail.total; processedFiles = 0; storageProgress = {}"
                x-on:upload-progress="processedFiles = $event.detail.current; currentFile = $event.detail.filename; progress = $event.detail.size || ''"
                x-on:upload-completed="uploadStarted = false; currentFile = ''; storageProgress = {}"
                x-on:storage-progress-update="storageProgress = $event.detail.progress"
        >
            <x-mary-file
                    wire:model="files"
                    label="{{ __('Select Images') }}"
                    multiple
                    accept="image/*"
                    hint="{{ __('Max') }} {{ $maxFileSizeMB }}{{ __('MB per file. Supported: JPEG, PNG, GIF, WebP, SVG, BMP, TIFF') }}"
                    class="border-2 border-dashed border-gray-300 hover:border-primary transition-colors"
            />

            {{-- File Upload Progress (Livewire's built-in) --}}
            <div x-show="uploading" class="mt-4">
                <x-mary-card>
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-cloud-arrow-up" class="w-5 h-5 text-primary animate-bounce" />
                            <span class="font-medium">{{ __('Uploading files...') }}</span>
                        </div>
                        <span class="text-sm font-mono" x-text="progress + '%'"></span>
                    </div>
                    <x-mary-progress :value="0" max="100" x-bind:value="progress" class="mb-2"/>
                </x-mary-card>
            </div>

            {{-- Processing Progress (Our custom progress) --}}
            <div x-show="uploadStarted" class="mt-4">
                <x-mary-card>
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-cog-6-tooth" class="w-5 h-5 text-primary animate-spin" />
                            <span class="font-medium">{{ __('Processing files...') }}</span>
                        </div>
                        <span class="text-sm font-mono" x-text="processedFiles + ' / ' + totalFiles"></span>
                    </div>
                    <x-mary-progress :value="0" max="100" x-bind:value="totalFiles > 0 ? (processedFiles / totalFiles) * 100 : 0" class="mb-2"/>
                    <div x-show="currentFile" class="flex items-center gap-2 text-xs text-gray-500">
                        <x-mary-icon name="o-document" class="w-4 h-4" />
                        <span>{{ __('Processing:') }}</span>
                        <span x-text="currentFile" class="font-medium"></span>
                        <span x-text="progress" class="text-gray-400"></span>
                    </div>
                </x-mary-card>
            </div>

            {{-- Storage Provider Progress (Detailed per-file progress) --}}
            <div x-show="uploadStarted && Object.keys(storageProgress).length > 0" class="mt-4">
                <x-mary-card>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-cloud-arrow-up" class="w-5 h-5 text-primary" />
                            <span class="font-medium">{{ __('Storage Progress') }}</span>
                        </div>
                        <div class="badge badge-outline badge-sm" x-text="
                            selectedDisk === 'spaces' ? 'DigitalOcean Spaces' :
                            selectedDisk === 'r2' ? 'Cloudflare R2' :
                            selectedDisk === 's3' ? 'Amazon S3' :
                            'Local Storage'
                        "></div>
                    </div>
                    
                    <div class="space-y-3">
                        <template x-for="(progress, fileId) in storageProgress" :key="fileId">
                            <div class="border border-base-300 dark:border-base-content/20 rounded-lg p-3">
                                <!-- File Header -->
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full" 
                                             x-bind:class="{
                                                 'bg-blue-500 animate-pulse': progress.stage === 'preparing',
                                                 'bg-yellow-500 animate-pulse': progress.stage === 'uploading',
                                                 'bg-orange-500 animate-pulse': progress.stage === 'storing',
                                                 'bg-purple-500 animate-pulse': progress.stage === 'processing',
                                                 'bg-green-500': progress.stage === 'complete',
                                                 'bg-red-500': progress.stage === 'error'
                                             }"></div>
                                        <span class="text-sm font-medium" x-text="progress.filename"></span>
                                        <span class="text-xs text-base-content/70" x-text="progress.size"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-mono" x-text="Math.round(progress.progress || 0) + '%'"></span>
                                        <div class="text-xs capitalize px-2 py-1 rounded" 
                                             x-bind:class="{
                                                 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': progress.stage === 'preparing',
                                                 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': progress.stage === 'uploading',
                                                 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': progress.stage === 'storing',
                                                 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200': progress.stage === 'processing',
                                                 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': progress.stage === 'complete',
                                                 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': progress.stage === 'error'
                                             }"
                                             x-text="
                                                 progress.stage === 'preparing' ? '{{ __('Preparing') }}' :
                                                 progress.stage === 'uploading' ? '{{ __('Uploading') }}' :
                                                 progress.stage === 'storing' ? '{{ __('Storing') }}' :
                                                 progress.stage === 'processing' ? '{{ __('Processing') }}' :
                                                 progress.stage === 'complete' ? '{{ __('Complete') }}' :
                                                 progress.stage === 'error' ? '{{ __('Error') }}' :
                                                 progress.stage
                                             "></div>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="w-full bg-base-200 dark:bg-base-300 rounded-full h-2 mb-2">
                                    <div class="h-2 rounded-full transition-all duration-300"
                                         x-bind:class="{
                                             'bg-blue-500': progress.stage === 'preparing',
                                             'bg-yellow-500': progress.stage === 'uploading',
                                             'bg-orange-500': progress.stage === 'storing',
                                             'bg-purple-500': progress.stage === 'processing',
                                             'bg-green-500': progress.stage === 'complete',
                                             'bg-red-500': progress.stage === 'error'
                                         }"
                                         x-bind:style="'width: ' + Math.min(100, Math.max(0, progress.progress || 0)) + '%'"></div>
                                </div>
                                
                                <!-- Storage Provider & Additional Info -->
                                <div class="flex items-center justify-between text-xs text-base-content/70">
                                    <div class="flex items-center gap-2">
                                        <x-mary-icon name="o-server" class="w-3 h-3" />
                                        <span x-text="progress.provider"></span>
                                    </div>
                                    <div x-show="progress.uploaded_bytes && progress.total_bytes" class="font-mono">
                                        <span x-text="Math.round((progress.uploaded_bytes / 1024 / 1024) * 100) / 100 + ' MB'"></span>
                                        <span class="mx-1">/</span>
                                        <span x-text="Math.round((progress.total_bytes / 1024 / 1024) * 100) / 100 + ' MB'"></span>
                                    </div>
                                </div>
                                
                                <!-- Error Message -->
                                <div x-show="progress.stage === 'error' && progress.error" class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-xs text-red-700 dark:text-red-300">
                                    <x-mary-icon name="o-exclamation-triangle" class="w-3 h-3 inline mr-1" />
                                    <span x-text="progress.error"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </x-mary-card>
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
        </div>

        {{-- Show upload button only when files are selected and not currently uploading --}}
        @if($files && count($files) > 0)
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        {{ count($files) }} {{ count($files) > 1 ? __('files') : __('file') }} {{ __('selected') }}
                    </div>
                    <div class="flex gap-2">
                        <x-mary-button
                                wire:click="$set('files', [])"
                                class="btn-ghost btn-sm"
                                icon="o-x-mark"
                                x-bind:disabled="uploading || uploadStarted"
                        >
                            {{ __('Clear') }}
                        </x-mary-button>
                        
                        <x-mary-button
                                type="submit"
                                class="btn-primary"
                                icon="o-cloud-arrow-up"
                                x-bind:disabled="uploading || uploadStarted"
                                spinner="save"
                        >
                            <span x-show="!uploading && !uploadStarted">
                                {{ __('Upload') }} {{ count($files) }} {{ count($files) > 1 ? __('files') : __('file') }}
                            </span>
                            <span x-show="uploading">{{ __('Uploading...') }}</span>
                            <span x-show="uploadStarted && !uploading">{{ __('Processing...') }}</span>
                        </x-mary-button>
                    </div>
                </div>
            </div>
        @endif
    </x-mary-form>

    {{-- Display uploaded files --}}
    @if($uploadedFiles && count($uploadedFiles) > 0)
        <x-mary-card title="{{ __('Uploaded Files') }}" subtitle="{{ count($uploadedFiles) }} {{ count($uploadedFiles) > 1 ? __('files') : __('file') }} {{ __('uploaded successfully') }}" class="mt-8">
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

            <!-- Upload Statistics -->
            @if($this->uploadStats)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="text-center">
                        <div class="text-lg font-bold text-blue-600">{{ $this->uploadStats['total_files'] }}</div>
                        <div class="text-xs text-gray-500">{{ __('Files') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-green-600">{{ $this->uploadStats['total_size_formatted'] }}</div>
                        <div class="text-xs text-gray-500">{{ __('Total Size') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-purple-600">{{ $this->formatFileSize($this->uploadStats['average_size']) }}</div>
                        <div class="text-xs text-gray-500">{{ __('Avg Size') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-orange-600">{{ count($this->uploadStats['file_types']) }}</div>
                        <div class="text-xs text-gray-500">{{ __('Types') }}</div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($uploadedFiles as $index => $file)
                    <x-mary-card class="group hover:shadow-lg transition-all duration-200">
                        {{-- File preview --}}
                        <div class="relative mb-3">
                            @if(str_starts_with($file['mime'], 'image/'))
                                <img
                                        src="{{ $file['url'] }}"
                                        alt="{{ $file['name'] }}"
                                        class="w-full h-32 object-cover rounded"
                                        loading="lazy"
                                />
                                
                                <!-- Image overlay with dimensions -->
                                @if(isset($file['width'], $file['height']))
                                    <div class="absolute bottom-1 right-1 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                        {{ $file['width'] }}×{{ $file['height'] }}
                                    </div>
                                @endif
                            @else
                                <div class="w-full h-32 bg-gray-100 rounded flex items-center justify-center">
                                    <x-mary-icon name="o-document" class="w-12 h-12 text-gray-400"/>
                                </div>
                            @endif
                            
                            <!-- File type badge -->
                            <div class="absolute top-1 right-1">
                                <x-mary-badge value="{{ strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) }}" class="badge-primary badge-sm" />
                            </div>
                        </div>

                        {{-- File info --}}
                        <div class="space-y-1 mb-3">
                            <p class="font-medium text-sm truncate" title="{{ $file['name'] }}">
                                {{ $file['name'] }}
                            </p>
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>{{ $file['formatted_size'] ?? $this->formatFileSize($file['size']) }}</span>
                                @if(isset($file['uploaded_at']))
                                    <span>{{ \Carbon\Carbon::parse($file['uploaded_at'])->format('M j, g:i A') }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-1">
                            @if(isset($file['url']))
                                <x-mary-button 
                                    icon="o-eye" 
                                    class="btn-xs btn-ghost flex-1"
                                    link="{{ $file['url'] }}"
                                    external
                                    tooltip="{{ __('View') }}" />
                                
                                <x-mary-button 
                                    icon="o-clipboard" 
                                    class="btn-xs btn-ghost"
                                    onclick="navigator.clipboard.writeText('{{ $file['url'] }}')"
                                    tooltip="{{ __('Copy URL') }}" />
                            @endif
                            
                            <x-mary-button 
                                icon="o-trash" 
                                class="btn-xs btn-error"
                                wire:click="removeFile({{ $index }})"
                                wire:confirm="{{ __('Remove this file?') }}"
                                tooltip="{{ __('Delete') }}" />
                        </div>
                    </x-mary-card>
                @endforeach
            </div>
        </x-mary-card>
    @endif

    {{-- Toast Messages --}}
    <x-mary-toast />
    
    {{-- Success/Error Messages --}}
    <div
            x-data="{ show: false, message: '', type: 'success', errors: [] }"
            x-on:upload-completed="
                if($event.detail.successful > 0) {
                    $wire.dispatch('success', { message: $event.detail.successful + ' file(s) uploaded successfully!' });
                }
                if($event.detail.failed > 0) {
                    show = true;
                    message = $event.detail.failed + ' file(s) failed to upload';
                    type = 'error';
                    errors = $event.detail.errors || [];
                    setTimeout(() => show = false, 10000)
                }
            "
            x-on:file-removed="
                if($event.detail.success) {
                    $wire.dispatch('success', { message: 'File removed successfully' });
                } else {
                    $wire.dispatch('error', { message: 'Failed to remove file' });
                }
            "
            x-on:files-cleared="
                $wire.dispatch('success', { message: $event.detail.deleted + ' of ' + $event.detail.total + ' files cleared' });
            "
    >
        <div x-show="show" x-transition class="mt-4">
            <x-mary-alert
                    x-bind:class="type === 'success' ? 'alert-success' : 'alert-error'"
                    icon="o-exclamation-triangle"
                    dismissible
            >
                <span x-text="message"></span>
                <template x-if="errors.length > 0">
                    <ul class="mt-2 list-disc list-inside text-sm">
                        <template x-for="error in errors" :key="error.filename">
                            <li>
                                <strong x-text="error.filename"></strong>: 
                                <span x-text="error.error"></span>
                            </li>
                        </template>
                    </ul>
                </template>
            </x-mary-alert>
        </div>
    </div>
</div>