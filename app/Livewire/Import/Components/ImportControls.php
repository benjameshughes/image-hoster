<?php

declare(strict_types=1);

namespace App\Livewire\Import\Components;

use App\Models\Import;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

class ImportControls extends Component
{
    use AuthorizesRequests, Toast;

    public Import $import;

    public function mount(Import $import): void
    {
        // Ensure the import belongs to the current user
        if ($import->user_id !== Auth::id()) {
            abort(403);
        }

        $this->import = $import;
    }

    public function pauseImport(): void
    {
        if ($this->import->status->canBePaused()) {
            $this->import->pause();
            $this->warning(
                title: 'Import Paused',
                description: 'The import has been paused. You can resume it anytime.',
                position: 'toast-top toast-end'
            );
            $this->import->refresh();
        }
    }

    public function resumeImport(): void
    {
        if ($this->import->status->canBeResumed()) {
            $this->import->resume();
            $this->info(
                title: 'Import Resumed',
                description: 'The import has been resumed and will continue processing.',
                position: 'toast-top toast-end'
            );
            $this->import->refresh();
        }
    }

    public function cancelImport(): void
    {
        if ($this->import->status->canBeCancelled()) {
            $this->import->cancel();
            $this->info(
                title: 'Import Cancelled',
                description: 'The import has been cancelled.',
                position: 'toast-top toast-end'
            );
            $this->import->refresh();
        }
    }

    public function deleteImport(): void
    {
        $this->authorize('delete', $this->import);
        
        try {
            // Cancel if running
            if ($this->import->status->isActive()) {
                $this->import->cancel();
            }
            
            // Delete associated items first
            $this->import->items()->delete();
            
            // Delete the import
            $this->import->delete();
            
            $this->success(
                title: 'Import Deleted',
                description: 'The import and all its data have been permanently deleted.',
                position: 'toast-top toast-end'
            );

            // Redirect to dashboard
            $this->redirect(route('import.dashboard'));
            
        } catch (\Exception $e) {
            $this->error(
                title: 'Delete Failed',
                description: 'Failed to delete import: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }

    public function retryFailedItems(): void
    {
        $this->authorize('update', $this->import);
        
        if ($this->import->status->isCompleted() && $this->import->failed_items > 0) {
            try {
                // Reset failed items to pending
                $this->import->items()
                    ->where('status', 'failed')
                    ->update(['status' => 'pending', 'error_message' => null]);
                
                // Resume the import
                $this->import->resume();
                
                $this->info(
                    title: 'Retrying Failed Items',
                    description: "{$this->import->failed_items} failed items will be retried.",
                    position: 'toast-top toast-end'
                );
                
            } catch (\Exception $e) {
                $this->error(
                    title: 'Retry Failed',
                    description: 'Failed to retry items: ' . $e->getMessage(),
                    position: 'toast-top toast-end'
                );
            }
        }
    }

    public function render()
    {
        return view('livewire.import.components.import-controls');
    }
}