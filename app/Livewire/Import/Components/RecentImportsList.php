<?php

declare(strict_types=1);

namespace App\Livewire\Import\Components;

use App\Models\Import;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

class RecentImportsList extends Component
{
    use Toast, AuthorizesRequests;

    public int $limit = 10;
    public $recentImports;

    public function mount(): void
    {
        $this->loadRecentImports();
    }

    public function loadRecentImports(): void
    {
        $this->recentImports = Auth::user()
            ->imports()
            ->latest()
            ->take($this->limit)
            ->get();
    }


    public function duplicateImport(Import $import): void
    {
        $this->authorize('view', $import);
        
        try {
            $config = $import->config;
            
            // Emit to parent with import settings (without password for security)
            $this->dispatch('import-duplicated', [
                'wordpressUrl' => $config['wordpress_url'] ?? '',
                'username' => $config['username'] ?? '',
                'importName' => $import->name . ' (Copy)',
                'configuration' => array_except($config, ['wordpress_url', 'username', 'password']),
            ]);
            
            $this->info(
                title: 'Import Settings Copied',
                description: 'Settings have been copied from the previous import. Please enter your password.',
                position: 'toast-top toast-end'
            );
            
        } catch (\Exception $e) {
            $this->error(
                title: 'Copy Failed',
                description: 'Failed to copy import settings: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }


    public function render()
    {
        return view('livewire.import.components.recent-imports-list');
    }
}