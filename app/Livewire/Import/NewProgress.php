<?php

declare(strict_types=1);

namespace App\Livewire\Import;

use App\Models\Import;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NewProgress extends Component
{
    public ?Import $import = null;

    public function mount(Import $import): void
    {
        // Ensure the import belongs to the current user
        if ($import->user_id !== Auth::id()) {
            abort(403);
        }

        $this->import = $import;
    }

    public function render()
    {
        return view('livewire.import.new-progress')
            ->layout('layouts.app');
    }
}