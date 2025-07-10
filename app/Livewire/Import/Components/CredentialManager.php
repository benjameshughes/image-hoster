<?php

declare(strict_types=1);

namespace App\Livewire\Import\Components;

use App\Models\WordPressCredential;
use App\Services\WordPress\WordPressConnectionTester;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

class CredentialManager extends Component
{
    use Toast;

    public ?WordPressCredential $selectedCredential = null;
    public bool $saveCredentials = false;
    public bool $showCredentialForm = false;
    
    #[Validate('required|string|max:255')]
    public string $credentialName = '';
    
    #[Validate('required|url')]
    public string $wordpressUrl = '';
    
    #[Validate('required|string')]
    public string $username = '';
    
    #[Validate('required|string')]
    public string $password = '';
    
    public bool $setAsDefault = false;
    
    // Connection testing properties
    public array $connectionTest = [];
    public array $siteInfo = [];
    public array $mediaStats = [];
    public bool $testing = false;
    public bool $hasActiveImport = false;
    
    // Cached properties to prevent DB queries on every render
    public $savedCredentials;
    public bool $hasDefaultCredential = false;

    public function mount(): void
    {
        // Load cached properties once to prevent DB queries on every render
        $this->savedCredentials = Auth::user()->wordPressCredentials()->latest()->get();
        
        // Check for active imports once on mount
        $this->hasActiveImport = Auth::user()
            ->imports()
            ->where('status', \App\Enums\ImportStatus::RUNNING)
            ->exists();
            
        // Load default credentials if available
        $defaultCredential = Auth::user()->defaultWordPressCredential();
        if ($defaultCredential) {
            $this->hasDefaultCredential = true;
            $this->selectedCredential = $defaultCredential;
            $this->loadCredentialData($defaultCredential);
            
            // Emit loaded credentials to other components
            $this->dispatch('credential-loaded', [
                'wordpressUrl' => $this->wordpressUrl,
                'username' => $this->username,
                'password' => $this->password,
            ]);
            
            // Skip auto-test to reduce HTTP requests - user can manually test if needed
        }
    }


    public function autoTestConnection(): void
    {
        // Don't test if there's an active import running
        $activeImport = Auth::user()
            ->imports()
            ->where('status', \App\Enums\ImportStatus::RUNNING)
            ->exists();
            
        if ($activeImport) {
            return;
        }
        
        // Test connection automatically when credentials are loaded
        if ($this->hasDefaultCredential && !empty($this->wordpressUrl) && empty($this->connectionTest)) {
            $this->testConnection();
        }
    }

    public function testConnectionAsync(): void
    {
        // Don't test if there's an active import running
        $activeImport = Auth::user()
            ->imports()
            ->where('status', \App\Enums\ImportStatus::RUNNING)
            ->exists();
            
        if ($activeImport) {
            return;
        }
        
        // Only test if we have default credentials and haven't tested yet
        if ($this->hasDefaultCredential && !empty($this->wordpressUrl) && empty($this->connectionTest)) {
            $this->testConnection();
        }
    }

    public function loadCredential(int $credentialId): void
    {
        $credential = Auth::user()->wordPressCredentials()->find($credentialId);
        
        if (!$credential) {
            $this->error(
                title: 'Credential Not Found',
                description: 'The selected credential could not be found.',
                position: 'toast-top toast-end'
            );
            return;
        }
        
        $this->selectedCredential = $credential;
        $this->loadCredentialData($credential);
        
        $credential->markAsUsed();
        
        $this->success(
            title: 'Credentials Loaded',
            description: "Loaded credentials for {$credential->name}",
            position: 'toast-top toast-end'
        );

        // Emit to parent components
        $this->dispatch('credential-loaded', [
            'wordpressUrl' => $this->wordpressUrl,
            'username' => $this->username,
            'password' => $this->password,
        ]);
    }

    private function loadCredentialData(WordPressCredential $credential): void
    {
        $this->wordpressUrl = $credential->wordpress_url;
        $this->username = $credential->username;
        $this->password = $credential->password;
    }

    public function saveCredential(): void
    {
        $this->validate([
            'credentialName' => 'required|string|max:255',
            'wordpressUrl' => 'required|url',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $credential = Auth::user()->wordPressCredentials()->create([
                'name' => $this->credentialName,
                'wordpress_url' => $this->wordpressUrl,
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($this->setAsDefault) {
                $credential->setAsDefault();
            }

            $this->selectedCredential = $credential;
            $this->showCredentialForm = false;
            $this->reset(['credentialName', 'setAsDefault']);
            
            // Refresh cached credentials
            $this->refreshCredentials();

            $this->success(
                title: 'Credentials Saved',
                description: 'WordPress credentials have been securely saved.',
                position: 'toast-top toast-end'
            );

        } catch (\Exception $e) {
            $this->error(
                title: 'Save Failed',
                description: 'Failed to save credentials: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }

    public function deleteCredential(WordPressCredential $credential): void
    {
        try {
            $credential->delete();
            
            if ($this->selectedCredential?->id === $credential->id) {
                $this->selectedCredential = null;
                $this->reset(['wordpressUrl', 'username', 'password']);
            }
            
            // Refresh cached credentials
            $this->refreshCredentials();

            $this->success(
                title: 'Credentials Deleted',
                description: 'WordPress credentials have been deleted.',
                position: 'toast-top toast-end'
            );

        } catch (\Exception $e) {
            $this->error(
                title: 'Delete Failed',
                description: 'Failed to delete credentials: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }

    public function setCredentialAsDefault(WordPressCredential $credential): void
    {
        try {
            $credential->setAsDefault();
            
            $this->success(
                title: 'Default Set',
                description: "'{$credential->name}' is now your default WordPress credential.",
                position: 'toast-top toast-end'
            );

        } catch (\Exception $e) {
            $this->error(
                title: 'Failed to Set Default',
                description: 'Failed to set default credential: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }

    public function getHasSavedCredentialsProperty(): bool
    {
        return $this->savedCredentials && $this->savedCredentials->count() > 0;
    }
    
    public function refreshCredentials(): void
    {
        // Refresh cached credentials when needed (after save/delete)
        $this->savedCredentials = Auth::user()->wordPressCredentials()->latest()->get();
    }


    public function getConnectionStatusProperty(): string
    {
        if (empty($this->connectionTest)) {
            return 'untested';
        }

        return $this->connectionTest['success'] ? 'success' : 'failed';
    }

    public function testConnection(): void
    {
        // Don't test if there's an active import running
        $activeImport = Auth::user()
            ->imports()
            ->where('status', \App\Enums\ImportStatus::RUNNING)
            ->exists();
            
        if ($activeImport) {
            $this->warning(
                title: 'Import in Progress',
                description: 'Cannot test connection while an import is running.',
                position: 'toast-top toast-end'
            );
            return;
        }
        
        if (empty($this->wordpressUrl) || empty($this->username) || empty($this->password)) {
            $this->error(
                title: 'Missing Credentials',
                description: 'Please ensure all connection details are provided.',
                position: 'toast-top toast-end'
            );
            return;
        }

        $this->testing = true;
        $this->connectionTest = [];
        $this->siteInfo = [];
        $this->mediaStats = [];

        try {
            $tester = WordPressConnectionTester::make(
                $this->wordpressUrl,
                $this->username,
                $this->password
            );

            $results = $tester->test();
            $this->connectionTest = $results;

            if ($results['success']) {
                $this->siteInfo = $results['site_info'] ?? [];
                $this->mediaStats = $results['media_stats'] ?? [];
                
                $this->success(
                    title: 'Connection Successful!',
                    description: "Found {$this->mediaStats['total']} media items ready for import.",
                    position: 'toast-top toast-end',
                    timeout: 4000
                );

                // Emit event to parent components
                $this->dispatch('connection-tested', [
                    'success' => true,
                    'mediaStats' => $this->mediaStats,
                    'siteInfo' => $this->siteInfo
                ]);
            } else {
                $this->error(
                    title: 'Connection Failed',
                    description: 'Please check your credentials and try again.',
                    position: 'toast-top toast-end',
                    timeout: 6000
                );

                $this->dispatch('connection-tested', ['success' => false]);
            }
        } catch (\Exception $e) {
            $this->error(
                title: 'Connection Error',
                description: $e->getMessage(),
                position: 'toast-top toast-end',
                timeout: 6000
            );

            $this->dispatch('connection-tested', ['success' => false]);
        } finally {
            $this->testing = false;
        }
    }

    public function render()
    {
        return view('livewire.import.components.credential-manager');
    }
}