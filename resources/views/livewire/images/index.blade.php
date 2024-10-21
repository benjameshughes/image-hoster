<?php

use App\Http\Controllers\ImageController;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {

    public \Illuminate\Support\Collection $images;

    public $listeners = [
        'imageUploaded',
        'imageDeleted',
    ];

    public function delete(Image $image)
    {
        $controller = new ImageController();
        $result = $controller->destroy($image);

        if ($result['success']) {
            $this->images = $this->images->filter(function ($item) use ($image) {
                return $item->id !== $image->id;
            })->values();
            $this->dispatch('imageDeleted', $image->id);
            session()->flash('deleted', 'Image deleted successfully');
        } else {
            $this->addError('delete', $result['message']);
        }
    }

    public function download(Image $image)
    {
        $controller = new ImageController();
        return $controller->download($image);
    }

    public function mount($images)
    {
        $this->images = $images;
    }
}; ?>

<div class="space-y-4">
    @if (session('uploaded'))
        <div class="p-4 bg-green-100 text-green-700 rounded-md">
            {{ session('status') }}
        </div>
    @endif
    @if (session('deleted'))
        <div class="p-4 bg-red-100 text-red-700 rounded-md">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between pb-4">
                <h2 class="text-xl font-semibold">Image Gallery</h2>
                <!-- Add any action buttons or filters here -->
            </div>

                @forelse($images as $image)
                    <ul role="list" class="divide-y divide-gray-100">
                        <li class="flex justify-between items-center gap-x-6 py-6 border-b border-gray-100">
                            <div class="flex items-center min-w-0 gap-x-4">
                                <img class="w-20 h-auto rounded-full" src="{{ Storage::disk('images')->url($image->filename) }}" alt="{{ $image->original_filename ?? 'Uploaded image' }}">
                                <div class="min-w-0 flex-auto">
                                    <p class="text-sm font-semibold leading-6 text-gray-900">{{$image->original_filename ?? 'Uploaded image'}}</p>
                                    <p class="mt-1 truncate text-xs leading-5 text-gray-500">{{ Storage::disk('images')->url($image->filename) }}</p>
                                </div>
                            </div>
                            <div class="hidden shrink-0 sm:flex sm:flex-col sm:items-end">
                                <p class="text-sm leading-6 text-gray-900">Uploaded {{$image->created_at->diffForHumans()}}</p>
                            </div>
                            <div class="hidden shrink-0 sm:flex sm:flex-col sm:items-end">
                                <x-copy-to-clipboard textToCopy="{{Storage::disk('images')->url($image->filename)}}" />
                                <x-confirm
                                        title="Are you sure you want to delete this image?"
                                        wireAction="delete({{ $image['filename'] }})"
                                        class="px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                >
                                    Delete
                                </x-confirm>
                            </div>

                        </li>
                    </ul>
                @empty
                    <div class="p-4 text-center text-gray-500 col-span-3">
                        No images found.
                    </div>
                @endforelse
            </div>
    </div>
</div>
