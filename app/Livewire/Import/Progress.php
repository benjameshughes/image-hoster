<?php

declare(strict_types=1);

namespace App\Livewire\Import;

use App\Models\Import;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

class Progress extends Component
{
    use AuthorizesRequests, Toast;

    public ?Import $import = null;

    public function mount(Import $import): void
    {
        // Ensure the import belongs to the current user
        if ($import->user_id !== Auth::id()) {
            abort(403);
        }

        $this->import = $import;
    }

    /**
     * Pause the import
     */
    public function pauseImport(): void
    {
        if ($this->import && $this->import->status->canBePaused()) {
            $this->import->pause();
            $this->warning(
                title: 'Import Paused',
                description: 'The import has been paused. You can resume it anytime.',
                position: 'toast-top toast-end'
            );
            $this->import->refresh();
        }
    }

    /**
     * Resume the import
     */
    public function resumeImport(): void
    {
        if ($this->import && $this->import->status->canBeResumed()) {
            $this->import->resume();
            $this->info(
                title: 'Import Resumed',
                description: 'The import has been resumed and will continue processing.',
                position: 'toast-top toast-end'
            );
            $this->import->refresh();
        }
    }

    /**
     * Cancel the import
     */
    public function cancelImport(): void
    {
        if ($this->import && $this->import->status->canBeCancelled()) {
            $this->import->cancel();
            $this->info(
                title: 'Import Cancelled',
                description: 'The import has been cancelled.',
                position: 'toast-top toast-end'
            );
            $this->import->refresh();
        }
    }

    /**
     * Format bytes for display
     */
    public function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2) . ' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageProperty(): float
    {
        if (!$this->import || $this->import->total_items === 0) {
            return 0;
        }

        return ($this->import->processed_items / $this->import->total_items) * 100;
    }

    public function render()
    {
        return view('livewire.import.progress')
            ->layout('layouts.app');
    }
}