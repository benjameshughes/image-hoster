<?php

declare(strict_types=1);

namespace App\Livewire\Import\Actions;

use App\Services\WordPress\WordPressConnectionTester;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Mary\Traits\Toast;

class TestConnectionAction extends Component
{
    use Toast;

    public string $wordpressUrl = '';

    public string $username = '';

    public string $password = '';

    public array $connectionTest = [];

    public array $siteInfo = [];

    public array $mediaStats = [];

    public bool $testing = false;

    public bool $hasDefaultCredentials = false;

    public function mount(): void
    {
        // Check if user has default credentials and load them
        $defaultCredential = auth()->user()->defaultWordPressCredential();
        if ($defaultCredential) {
            $this->hasDefaultCredentials = true;
            $this->wordpressUrl = $defaultCredential->wordpress_url;
            $this->username = $defaultCredential->username;
            $this->password = $defaultCredential->password;

            // Don't auto-test here - let parent component trigger it
        }
    }

    public function testConnection(): void
    {
        // Rate limiting check
        $request = app(Request::class);
        $request->setUserResolver(fn () => Auth::user());

        $limiter = RateLimiter::for('wordpress-connection-test', fn () => $request);
        if (! $limiter->attempt($request)) {
            $this->error(
                title: 'Connection Test Limit Reached',
                description: 'Too many connection attempts. Please wait before testing again.',
                position: 'toast-top toast-end',
                timeout: 5000
            );

            return;
        }

        $this->validate([
            'wordpressUrl' => 'required|url',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

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
                    'siteInfo' => $this->siteInfo,
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
        } catch (Exception $e) {
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

    public function getConnectionStatusProperty(): string
    {
        if (empty($this->connectionTest)) {
            return 'untested';
        }

        return $this->connectionTest['success'] ? 'success' : 'failed';
    }

    protected function getListeners(): array
    {
        return [
            'credential-loaded' => 'onCredentialLoaded',
            'test-connection-async' => 'testConnectionAsync',
        ];
    }

    public function onCredentialLoaded(array $data): void
    {
        $this->wordpressUrl = $data['wordpressUrl'];
        $this->username = $data['username'];
        $this->password = $data['password'];
        $this->hasDefaultCredentials = true;

        // Don't auto-test here - let the parent trigger it
    }

    public function testConnectionAsync(): void
    {
        // Only test if we have credentials
        if (! $this->hasDefaultCredentials || empty($this->wordpressUrl)) {
            return;
        }

        $this->testConnection();
    }

    public function render()
    {
        return view('livewire.import.actions.test-connection-action');
    }
}
