<div>
    <x-mary-form wire:submit="save">
        <div
                x-data="{
                uploading: false,
                progress: 0,
                uploadStarted: false,
                currentFile: '',
                totalFiles: 0,
                processedFiles: 0
            }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-cancel="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                {{-- Listen to our custom events from the Livewire component --}}
                x-on:upload-started="uploadStarted = true; totalFiles = $event.detail.total; processedFiles = 0"
                x-on:upload-progress="processedFiles = $event.detail.current; currentFile = $event.detail.filename"
                x-on:upload-completed="uploadStarted = false; currentFile = ''"
        >
            <x-mary-file
                    wire:model="files"
                    label="Media"
                    multiple
                    accept="image/*,application/pdf"
                    hint="Max 10MB per file. Supported: JPG, PNG, GIF, PDF"
            />

            {{-- File Upload Progress (Livewire's built-in) --}}
            <div x-show="uploading" class="mt-4">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Uploading files...</span>
                    <span x-text="progress + '%'"></span>
                </div>
                <x-mary-progress :value="0" max="100" x-bind:value="progress" class="mb-2"/>
            </div>

            {{-- Processing Progress (Our custom progress) --}}
            <div x-show="uploadStarted" class="mt-4">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Processing files...</span>
                    <span x-text="processedFiles + ' / ' + totalFiles"></span>
                </div>
                <x-mary-progress :value="0" max="100" x-bind:value="totalFiles > 0 ? (processedFiles / totalFiles) * 100 : 0"/>
                <p x-show="currentFile" class="text-xs text-gray-500 mt-1">
                    Currently processing: <span x-text="currentFile"></span>
                </p>
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
            <div class="mt-4">
                <x-mary-button
                        type="submit"
                        class="btn-primary"
                        x-bind:disabled="uploading || uploadStarted"
                        spinner="save"
                >
                    <span x-show="!uploading && !uploadStarted">
                        Upload {{ count($files) }} file{{ count($files) > 1 ? 's' : '' }}
                    </span>
                    <span x-show="uploading">Uploading...</span>
                    <span x-show="uploadStarted && !uploading">Processing...</span>
                </x-mary-button>
            </div>
        @endif
    </x-mary-form>

    {{-- Display uploaded files --}}
    @if($uploadedFiles && count($uploadedFiles) > 0)
        <div class="mt-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Uploaded Files ({{ count($uploadedFiles) }})</h2>
                <x-mary-button
                        type="button"
                        wire:click="clear"
                        class="btn-outline btn-sm"
                        icon="o-trash"
                >
                    Clear All
                </x-mary-button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($uploadedFiles as $index => $file)
                    <div class="border rounded-lg p-4 bg-white shadow-sm">
                        {{-- File preview --}}
                        <div class="mb-3">
                            @if(str_starts_with($file['mime'], 'image/'))
                                <img
                                        src="{{ $file['url'] }}"
                                        alt="{{ $file['name'] }}"
                                        class="w-full h-32 object-cover rounded"
                                        loading="lazy"
                                />
                            @else
                                <div class="w-full h-32 bg-gray-100 rounded flex items-center justify-center">
                                    <x-mary-icon name="o-document" class="w-12 h-12 text-gray-400"/>
                                </div>
                            @endif
                        </div>

                        {{-- File info --}}
                        <div class="space-y-1">
                            <p class="font-medium text-sm truncate" title="{{ $file['name'] }}">
                                {{ $file['name'] }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $this->formatFileSize($file['size']) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $file['mime'] }}
                            </p>
                            @if(isset($file['uploaded_at']))
                                <p class="text-xs text-gray-400">
                                    {{ \Carbon\Carbon::parse($file['uploaded_at'])->diffForHumans() }}
                                </p>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="mt-3 flex gap-2">
                            @if(isset($file['url']))
                                <flux:button type="button" href="{{ $file['url'] }}" _target="_blank">View Image</flux:button>
                            @endif
                            <flux:button type="button" wire:click="removeFile({{ $index }})" onclick="return confirm('Remove?')">
                                Delete
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Success/Error Messages --}}
    <div
            x-data="{ show: false, message: '', type: 'success' }"
            x-on:files-uploaded="
            show = true;
            message = $event.detail.count + ' file(s) uploaded successfully!';
            type = 'success';
            setTimeout(() => show = false, 5000)
        "
            x-on:upload-completed="
            if($event.detail.failed > 0) {
                show = true;
                message = $event.detail.successful + ' uploaded, ' + $event.detail.failed + ' failed';
                type = 'warning';
                setTimeout(() => show = false, 8000)
            }
        "
    >
        <div x-show="show" x-transition class="mt-4">
            <x-mary-alert
                    x-bind:class="type === 'success' ? 'alert-success' : 'alert-warning'"
                    x-text="message"
            />
        </div>
    </div>
</div>


