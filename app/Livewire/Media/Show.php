<?php

namespace App\Livewire\Media;

use App\Models\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests;

    public Media $media;

    public function mount(Media $media): void
    {
        $this->authorize('view', $media);
        $this->media = $media;
    }

    public function render()
    {
        return view('livewire.media.show')
            ->layout('layouts.app');
    }
}
