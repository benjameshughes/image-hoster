<?php

declare(strict_types=1);

namespace App\Livewire\Import\Actions;

use App\Enums\ImportStatus;
use App\Jobs\WordPress\AnalyzeWordPressMediaJob;
use App\Models\Import;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Mary\Traits\Toast;

class ImportActions extends Component
{
    use AuthorizesRequests, Toast;

    public ?Import $activeImport = null;

    public bool $importing = false;

    // Required data for starting import
    public string $importName = '';

    public string $wordpressUrl = '';

    public string $username = '';

    public string $password = '';

    public array $configuration = [];

    public array $mediaStats = [];

    public bool $connectionTested = false;

    public function mount(): void
    {
        $this->activeImport = Auth::user()
            ->imports()
            ->where('status', ImportStatus::RUNNING)
            ->latest()
            ->first();

        // Load default credentials if available
        $defaultCredential = Auth::user()->defaultWordPressCredential();
        if ($defaultCredential) {
            $this->wordpressUrl = $defaultCredential->wordpress_url;
            $this->username = $defaultCredential->username;
            $this->password = $defaultCredential->password;

            // Set default import name
            if (empty($this->importName)) {
                $this->importName = 'WordPress Import - '.now()->format('M j, Y g:i A');
            }
        }
    }

    protected function getListeners(): array
    {
        return [
            'connection-tested' => 'onConnectionTested',
            'credential-loaded' => 'onCredentialLoaded',
        ];
    }

    public function onConnectionTested(array $data): void
    {
        $this->connectionTested = $data['success'] ?? false;
        if ($data['success'] ?? false) {
            $this->mediaStats = $data['mediaStats'] ?? [];
        }
    }

    public function onCredentialLoaded(array $data): void
    {
        $this->wordpressUrl = $data['wordpressUrl'];
        $this->username = $data['username'];
        $this->password = $data['password'];
    }

    public function startImport(): void
    {
        // Rate limiting check
        $request = app(Request::class);
        $request->setUserResolver(fn () => Auth::user());

        $limiter = RateLimiter::for('wordpress-import-start', fn () => $request);
        if (! $limiter->attempt($request)) {
            $this->error(
                title: 'Import Limit Reached',
                description: 'You can start 3 imports per hour. Please wait before starting another import.',
                position: 'toast-top toast-end',
                timeout: 6000
            );

            return;
        }

        if (! $this->connectionTested) {
            $this->error(
                title: 'Test Connection First',
                description: 'Please test the connection before starting import.',
                position: 'toast-top toast-end'
            );

            return;
        }

        $this->validate([
            'importName' => 'required|string|min:3',
            'wordpressUrl' => 'required|url',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $this->importing = true;

        try {
            // Create the import record
            $this->activeImport = Auth::user()->imports()->create([
                'source' => 'wordpress',
                'name' => $this->importName,
                'config' => array_merge($this->configuration, [
                    'wordpress_url' => $this->wordpressUrl,
                    'username' => $this->username,
                    'password' => $this->password,
                ]),
                'total_items' => $this->mediaStats['total'] ?? 0,
                'status' => ImportStatus::PENDING,
            ]);

            // Dispatch the background job to analyze and import media
            AnalyzeWordPressMediaJob::dispatch($this->activeImport)
                ->onQueue('wordpress-import');

            $this->success(
                title: 'Import Started!',
                description: 'Your WordPress media import has been queued for processing.',
                position: 'toast-top toast-end',
                timeout: 4000
            );

            // Emit to parent components
            $this->dispatch('import-started', ['import_id' => $this->activeImport->id]);

        } catch (\Exception $e) {
            $this->error(
                title: 'Import Failed',
                description: 'Failed to start import: '.$e->getMessage(),
                position: 'toast-top toast-end',
                timeout: 6000
            );
        } finally {
            $this->importing = false;
        }
    }

    public function pauseImport(): void
    {
        if ($this->activeImport && $this->activeImport->status->canBePaused()) {
            $this->activeImport->pause();
            $this->warning(
                title: 'Import Paused',
                description: 'The import has been paused. You can resume it anytime.',
                position: 'toast-top toast-end'
            );
            $this->activeImport->refresh();
        }
    }

    public function resumeImport(): void
    {
        if ($this->activeImport && $this->activeImport->status->canBeResumed()) {
            $this->activeImport->resume();
            $this->info(
                title: 'Import Resumed',
                description: 'The import has been resumed and will continue processing.',
                position: 'toast-top toast-end'
            );
            $this->activeImport->refresh();
        }
    }

    public function cancelImport(): void
    {
        if ($this->activeImport && $this->activeImport->status->canBeCancelled()) {
            $this->activeImport->cancel();
            $this->activeImport = null;
            $this->info(
                title: 'Import Cancelled',
                description: 'The import has been cancelled.',
                position: 'toast-top toast-end'
            );
        }
    }

    public function deleteImport(Import $import): void
    {
        $this->authorize('delete', $import);

        try {
            // Cancel if running
            if ($import->status->isActive()) {
                $import->cancel();
            }

            // Delete associated items first
            $import->items()->delete();

            // Delete the import
            $import->delete();

            // Clear active import if it was the one deleted
            if ($this->activeImport?->id === $import->id) {
                $this->activeImport = null;
            }

            $this->success(
                title: 'Import Deleted',
                description: 'The import and all its data have been permanently deleted.',
                position: 'toast-top toast-end'
            );

        } catch (\Exception $e) {
            $this->error(
                title: 'Delete Failed',
                description: 'Failed to delete import: '.$e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }

    public function retryFailedItems(Import $import): void
    {
        $this->authorize('update', $import);

        if ($import->status->isCompleted() && $import->failed_items > 0) {
            try {
                // Reset failed items to pending
                $import->items()
                    ->where('status', 'failed')
                    ->update(['status' => 'pending', 'error_message' => null]);

                // Resume the import
                $import->resume();

                $this->info(
                    title: 'Retrying Failed Items',
                    description: "{$import->failed_items} failed items will be retried.",
                    position: 'toast-top toast-end'
                );

            } catch (\Exception $e) {
                $this->error(
                    title: 'Retry Failed',
                    description: 'Failed to retry items: '.$e->getMessage(),
                    position: 'toast-top toast-end'
                );
            }
        }
    }

    public function canStartImport(): bool
    {
        return $this->connectionTested &&
               ! empty($this->importName) &&
               ! empty($this->wordpressUrl) &&
               ! empty($this->username) &&
               ! empty($this->password) &&
               ! $this->importing &&
               (! $this->activeImport || ! $this->activeImport->status->isActive());
    }

    public function render()
    {
        return view('livewire.import.actions.import-actions');
    }
}
