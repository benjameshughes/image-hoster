<?php

declare(strict_types=1);

namespace App\Livewire\Import;

use App\Enums\ImportStatus;
use App\Models\Import;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NewDashboard extends Component
{
    // Connection data
    public string $wordpressUrl = '';
    public string $username = '';
    public string $password = '';
    
    // Configuration data
    public string $importName = '';
    public array $configuration = [];
    
    // Status tracking
    public array $mediaStats = [];
    public bool $connectionTested = false;
    public ?Import $activeImport = null;


    public function mount(): void
    {
        $this->activeImport = Auth::user()
            ->imports()
            ->where('status', ImportStatus::RUNNING)
            ->latest()
            ->first();

        if (! $this->importName) {
            $this->importName = 'WordPress Import ' . now()->format('M j, Y g:i A');
        }
    }

    public function triggerAsyncConnectionTest(): void
    {
        // Dispatch to TestConnectionAction to start async connection test
        $this->dispatch('test-connection-async');
    }

    #[On('credential-loaded')]
    public function onCredentialLoaded(array $data): void
    {
        $this->wordpressUrl = $data['wordpressUrl'];
        $this->username = $data['username'];
        $this->password = $data['password'];
        
        // Reset connection test when credentials change
        $this->connectionTested = false;
        $this->mediaStats = [];
    }

    #[On('connection-tested')]
    public function onConnectionTested(array $data): void
    {
        if (! $this->connectionTested) {
            $this->mediaStats = [];
        }

        $this->mediaStats = $data['mediaStats'];
        $this->connectionTested = true;

    }

    #[On('configuration-changed')]
    public function onConfigurationChanged(array $config): void
    {
        $this->configuration = $config;
    }

    #[On('import-started')]
    public function onImportStarted(array $data): void
    {
        $this->activeImport = Import::find($data['import_id']);
    }

    #[On('import-duplicated')]
    public function onImportDuplicated(array $data): void
    {
        $this->wordpressUrl = $data['wordpressUrl'];
        $this->username = $data['username'];
        $this->importName = $data['importName'];
        $this->configuration = $data['configuration'];
        
        // Reset connection test when settings change
        $this->connectionTested = false;
        $this->mediaStats = [];
        $this->password = ''; // Security: don't copy password
    }

    #[On('preset-loaded')]
    public function onPresetLoaded(array $data): void
    {
        $this->configuration = $data['configuration'];
        
        // Reset connection test when settings change
        $this->connectionTested = false;
        $this->mediaStats = [];
    }


    public function render()
    {
        return view('livewire.import.new-dashboard')
            ->layout('layouts.app');
    }
}