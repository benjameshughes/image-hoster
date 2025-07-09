<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Import;
use App\Enums\ImportStatus;
use Illuminate\Console\Command;

class FixStuckImports extends Command
{
    protected $signature = 'imports:fix-stuck {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix imports that are stuck at 100% but not marked as completed';

    public function handle(): int
    {
        $stuckImports = Import::where('status', ImportStatus::RUNNING)
            ->whereColumn('processed_items', '>=', 'total_items')
            ->where('total_items', '>', 0)
            ->get();

        if ($stuckImports->isEmpty()) {
            $this->info('No stuck imports found.');
            return 0;
        }

        $this->info("Found {$stuckImports->count()} stuck import(s):");

        foreach ($stuckImports as $import) {
            $this->line("- Import #{$import->id}: {$import->processed_items}/{$import->total_items} items ({$import->progress_percentage}%)");
            
            if (! $this->option('dry-run')) {
                $import->markAsCompleted();
                $this->info("  → Marked as completed");
            } else {
                $this->info("  → Would be marked as completed (dry-run)");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('This was a dry-run. Use --dry-run=false to actually fix the imports.');
        } else {
            $this->info('All stuck imports have been fixed!');
        }

        return 0;
    }
}