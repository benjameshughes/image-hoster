<?php

namespace App\Livewire\Import;

use App\Models\Import;
use Livewire\Attributes\On;
use Livewire\Component;

class ImportStatus extends Component
{
    public Import $import;

    public function mount(Import $import): void
    {
        $this->import = $import;
    }
    
    public function render()
    {
        return view('livewire.import.import-status');
    }
}