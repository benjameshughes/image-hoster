<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Import;
use App\Models\ImportPreset;
use App\Models\User;
use Illuminate\Console\Command;

class ManageImportsCommand extends Command
{
    protected $signature = 'import:manage 
                           {action : Action to perform (list, pause, resume, cancel, cleanup, stats)}
                           {--user= : Filter by user ID}
                           {--status= : Filter by status}
                           {--days= : Filter by days old}
                           {--preset= : Preset name for bulk operations}';

    protected $description = 'Manage WordPress imports in bulk';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listImports(),
            'pause' => $this->pauseImports(),
            'resume' => $this->resumeImports(),
            'cancel' => $this->cancelImports(),
            'cleanup' => $this->cleanupImports(),
            'stats' => $this->showStats(),
            'presets' => $this->managePresets(),
            default => $this->showHelp(),
        };
    }

    private function listImports(): int
    {
        $query = Import::query()->with('user');
        
        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }
        
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }
        
        if ($days = $this->option('days')) {
            $query->where('created_at', '<=', now()->subDays((int) $days));
        }
        
        $imports = $query->orderBy('created_at', 'desc')->get();
        
        if ($imports->isEmpty()) {
            $this->info('No imports found matching criteria.');
            return 0;
        }
        
        $this->table([
            'ID', 'User', 'Name', 'Status', 'Progress', 'Created', 'Duration'
        ], $imports->map(function ($import) {
            $progress = $import->total_items > 0 
                ? round(($import->processed_items / $import->total_items) * 100, 1) . '%'
                : '0%';
                
            return [
                $import->id,
                $import->user->name,
                str_limit($import->name, 30),
                $import->status->value,
                "{$import->processed_items}/{$import->total_items} ({$progress})",
                $import->created_at->diffForHumans(),
                $import->formattedDuration ?? 'N/A'
            ];
        }));
        
        return 0;
    }

    private function pauseImports(): int
    {
        $imports = $this->getFilteredImports()
            ->where('status', 'running')
            ->get();
            
        if ($imports->isEmpty()) {
            $this->info('No running imports found to pause.');
            return 0;
        }
        
        $count = 0;
        foreach ($imports as $import) {
            if ($import->status->canBePaused()) {
                $import->pause();
                $count++;
                $this->info("Paused import: {$import->name}");
            }
        }
        
        $this->info("Paused {$count} imports.");
        return 0;
    }

    private function resumeImports(): int
    {
        $imports = $this->getFilteredImports()
            ->where('status', 'paused')
            ->get();
            
        if ($imports->isEmpty()) {
            $this->info('No paused imports found to resume.');
            return 0;
        }
        
        $count = 0;
        foreach ($imports as $import) {
            if ($import->status->canBeResumed()) {
                $import->resume();
                $count++;
                $this->info("Resumed import: {$import->name}");
            }
        }
        
        $this->info("Resumed {$count} imports.");
        return 0;
    }

    private function cancelImports(): int
    {
        $imports = $this->getFilteredImports()
            ->whereIn('status', ['running', 'paused', 'pending'])
            ->get();
            
        if ($imports->isEmpty()) {
            $this->info('No active imports found to cancel.');
            return 0;
        }
        
        if (!$this->confirm("Are you sure you want to cancel {$imports->count()} imports?")) {
            return 0;
        }
        
        $count = 0;
        foreach ($imports as $import) {
            if ($import->status->canBeCancelled()) {
                $import->cancel();
                $count++;
                $this->info("Cancelled import: {$import->name}");
            }
        }
        
        $this->info("Cancelled {$count} imports.");
        return 0;
    }

    private function cleanupImports(): int
    {
        $days = $this->option('days') ?? 30;
        
        $imports = Import::where('status', 'completed')
            ->where('created_at', '<=', now()->subDays((int) $days))
            ->get();
            
        if ($imports->isEmpty()) {
            $this->info("No completed imports older than {$days} days found.");
            return 0;
        }
        
        if (!$this->confirm("Delete {$imports->count()} completed imports older than {$days} days?")) {
            return 0;
        }
        
        $count = 0;
        foreach ($imports as $import) {
            // Clean up import items first
            $import->items()->delete();
            $import->delete();
            $count++;
        }
        
        $this->info("Cleaned up {$count} old imports.");
        return 0;
    }

    private function showStats(): int
    {
        $totalImports = Import::count();
        $activeImports = Import::whereIn('status', ['running', 'pending'])->count();
        $completedImports = Import::where('status', 'completed')->count();
        $failedImports = Import::where('status', 'failed')->count();
        
        $totalMediaImported = Import::where('status', 'completed')->sum('successful_items');
        $totalDataProcessed = Import::sum('processed_items');
        
        $avgItemsPerImport = $totalImports > 0 ? round($totalDataProcessed / $totalImports) : 0;
        
        $this->table(['Metric', 'Value'], [
            ['Total Imports', number_format($totalImports)],
            ['Active Imports', number_format($activeImports)],
            ['Completed Imports', number_format($completedImports)],
            ['Failed Imports', number_format($failedImports)],
            ['Total Media Imported', number_format($totalMediaImported)],
            ['Average Items Per Import', number_format($avgItemsPerImport)],
        ]);
        
        // Show top users by import activity
        $topUsers = User::withCount('imports')
            ->orderBy('imports_count', 'desc')
            ->take(5)
            ->get()
            ->filter(function ($user) {
                return $user->imports_count > 0;
            });
            
        if ($topUsers->isNotEmpty()) {
            $this->info("\nTop Users by Import Activity:");
            $this->table(['User', 'Total Imports'], $topUsers->map(function ($user) {
                return [$user->name, $user->imports_count];
            }));
        }
        
        return 0;
    }

    private function managePresets(): int
    {
        $this->info('Available Import Presets:');
        
        $presets = ImportPreset::with('user')
            ->orderBy('is_global', 'desc')
            ->orderBy('name')
            ->get();
            
        if ($presets->isEmpty()) {
            $this->info('No presets found.');
            return 0;
        }
        
        $this->table([
            'ID', 'Name', 'Owner', 'Type', 'Storage', 'Media Types', 'Process Images'
        ], $presets->map(function ($preset) {
            $config = $preset->getConfigWithDefaults();
            
            return [
                $preset->id,
                $preset->name,
                $preset->is_global ? 'Global' : $preset->user->name,
                $preset->is_global ? 'Global' : 'Personal',
                ucfirst($config['storage_disk']),
                implode(', ', $config['media_types']),
                $config['process_images'] ? 'Yes' : 'No',
            ];
        }));
        
        return 0;
    }

    private function getFilteredImports()
    {
        $query = Import::query();
        
        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }
        
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }
        
        if ($days = $this->option('days')) {
            $query->where('created_at', '<=', now()->subDays((int) $days));
        }
        
        return $query;
    }

    private function showHelp(): int
    {
        $this->info('WordPress Import Management Commands:');
        $this->line('');
        $this->line('Available actions:');
        $this->line('  list     - List imports with optional filters');
        $this->line('  pause    - Pause running imports');
        $this->line('  resume   - Resume paused imports');  
        $this->line('  cancel   - Cancel active imports');
        $this->line('  cleanup  - Delete old completed imports');
        $this->line('  stats    - Show import statistics');
        $this->line('  presets  - List available import presets');
        $this->line('');
        $this->line('Options:');
        $this->line('  --user=ID    Filter by user ID');
        $this->line('  --status=X   Filter by status (running, completed, failed, etc.)');
        $this->line('  --days=N     Filter by age in days');
        $this->line('');
        $this->line('Examples:');
        $this->line('  php artisan import:manage list --status=running');
        $this->line('  php artisan import:manage cleanup --days=30');
        $this->line('  php artisan import:manage pause --user=1');
        
        return 0;
    }
}