<?php

namespace App\Livewire\Image;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public function deleteAll()
    {
        foreach (Image::all() as $image) {
            Storage::disk('spaces')->delete($image->path);
            $image->delete();
        }
    }

    public function view(Image $image)
    {
        // Redirect to the temporary URL
        return redirect()->away(Storage::disk('spaces')->url($image->path));
    }

    public function download(Image $image)
    {
        Storage::exists($image->path);
        return Storage::download($image->path);
    }


    public function delete(Image $image)
    {
        Storage::disk('spaces')->delete($image->path);
        $image->delete();
        $this->dispatch('ImageDeleted', message: "Image deleted");
    }

    public function mount()
    {
        $this->rows = Image::all();
        $this->headers = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'path', 'label' => 'Path'],
        ];
    }

    public function render()
    {
        return view('livewire.image.index', [
            'rows' => Image::all(),
            'headers' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'path', 'label' => 'Path'],
            ],
        ]);
    }
}
