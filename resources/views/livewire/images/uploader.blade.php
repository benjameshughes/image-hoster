<?php

use App\Services\ImageUploader;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

new class extends Component
{
    use WithFileUploads;

    public $images = [];
    public array $uploadQueue = [];
    public bool $isUploading = false;
    public bool $uploadComplete = false;

    public function updatedImages()
    {
        $uploader = app(ImageUploader::class);
        $this->uploadQueue = $uploader->prepareUploadQueue($this->images);
    }

    public function startUpload()
    {
        $this->isUploading = true;
        $this->uploadImages();
    }

    private function uploadImages()
    {
        $uploader = app(ImageUploader::class);
        foreach ($this->uploadQueue as $index => $item) {
            $this->uploadSingleImage($index, $uploader);
        }
        $this->finishUpload();
    }

    private function uploadSingleImage($index, ImageUploader $uploader)
    {
        $image = $this->images[$index];
        $this->uploadQueue[$index]['status'] = 'uploading';

        try {
            // Wrap the single image in an array to match the expected input
            $path = $uploader->upload([$image], function ($progress) use ($index) {
                $this->updateProgress($index, $progress);
            });

            $this->uploadQueue[$index]['status'] = 'complete';
            $this->uploadQueue[$index]['path'] = $path;
        } catch (\Exception $e) {
            $this->uploadQueue[$index]['status'] = 'failed';
            $this->uploadQueue[$index]['error'] = $e->getMessage();
        }
    }

    #[On('updateProgress')]
    public function updateProgress($index, $progress)
    {
        $this->uploadQueue[$index]['progress'] = $progress;
    }

    private function finishUpload()
    {
        $this->isUploading = false;
        $this->uploadComplete = true;
    }

    public function resetUploader()
    {
        $this->reset('images', 'uploadQueue', 'isUploading', 'uploadComplete');
    }
};
?>

<div>
    <form wire:submit.prevent="$parent.save" class="w-full">
        <div x-data="{ dragOver: false }"
             x-on:dragover.prevent="dragOver = true"
             x-on:dragleave.prevent="dragOver = false"
             x-on:drop.prevent="dragOver = false; $wire.images = $event.dataTransfer.files">

            <div x-show="!$wire.isUploading && !$wire.uploadComplete"
                 class="flex flex-col items-center justify-center w-full h-64 border-2 border-dashed rounded-lg cursor-pointer"
                 :class="{ 'border-gray-300 bg-gray-50 hover:bg-gray-100': !dragOver, 'border-blue-300 bg-blue-50': dragOver }">
                <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-full">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <svg class="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                        </svg>
                        <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                        <p class="text-xs text-gray-500">SVG, PNG, JPG or GIF (MAX. 10MB)</p>
                    </div>
                    <input id="dropzone-file" type="file" class="hidden" multiple wire:model="images"/>
                </label>
            </div>

            <div x-show="!$wire.isUploading && !$wire.uploadComplete && $wire.uploadQueue.length > 0" class="mt-4">
                <h2 class="text-lg font-semibold">Selected Files:</h2>
                <ul class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-2">
                    @foreach($uploadQueue as $item)
                        <li class="relative">
                            <img src="{{ $item['temporary_url'] }}" alt="{{ $item['filename'] }}" class="w-full h-32 object-cover rounded-lg">
                            <p class="text-sm mt-1 truncate">{{ $item['filename'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div x-show="$wire.isUploading" class="mt-4" wire:poll.500ms>
                <h1 class="leading-6 text-lg">Uploading Images</h1>
                @foreach($uploadQueue as $index => $item)
                    <div class="mt-2">
                        <p class="text-sm font-semibold">{{ $item['filename'] }}</p>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300 ease-in-out" style="width: {{ $item['progress'] }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500">Progress: {{ $item['progress'] }}% | Status: {{ $item['status'] }}</p>
                        @if($item['status'] === 'failed')
                            <p class="text-xs text-red-500">{{ $item['error'] ?? 'Upload failed' }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div x-show="$wire.uploadComplete" class="mt-4">
                <h1 class="leading-6 text-lg text-green-600">Upload Complete!</h1>
                <p class="mt-2">Successfully uploaded {{ count($uploadQueue) }} files.</p>
                <button wire:click="resetUploader" type="button"
                        class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Upload More Files
                </button>
            </div>
        </div>

        <div x-show="$wire.uploadQueue.length > 0 && !$wire.isUploading && !$wire.uploadComplete" class="mt-4">
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-md"
                    wire:click="startUpload">
                Start Upload
            </button>
            <button wire:click="resetUploader" type="button"
                    class="ml-2 px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Clear
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('updateProgress', (data) => {
            @this.updateProgress(data.index, data.progress);
        });
    });
</script>