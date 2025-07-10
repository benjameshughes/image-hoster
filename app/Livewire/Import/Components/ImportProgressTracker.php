<?php

declare(strict_types=1);

namespace App\Livewire\Import\Components;

use App\Models\Import;
use Livewire\Component;

class ImportProgressTracker extends Component
{
    public Import $import;
    public bool $showEstimatedTime = true;
    public bool $showRecentItems = true;
    public int $recentItemsLimit = 10;

    public function mount(Import $import): void
    {
        $this->import = $import;
    }

    public function getListeners()
    {
        return [
            // Removed Echo listeners to prevent event storm - ImportStatus handles Echo events
        ];
    }

    // Removed event handlers - ImportStatus handles all Echo events to prevent event storm

    public function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'Unknown';
        }

        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2) . ' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }

    public function getProgressPercentageProperty(): float
    {
        if ($this->import->total_items === 0) {
            return 0;
        }

        return ($this->import->processed_items / $this->import->total_items) * 100;
    }

    public function getRecentItemsProperty()
    {
        if (!$this->showRecentItems) {
            return collect();
        }
        
        return $this->import->items()
            ->whereNotNull('processed_at')
            ->latest('processed_at')
            ->limit($this->recentItemsLimit)
            ->get();
    }

    public function getEstimatedTimeRemainingProperty(): ?string
    {
        if (!$this->showEstimatedTime || !$this->import->status->isActive()) {
            return null;
        }

        $estimatedSeconds = $this->import->estimated_time_remaining;
        if (!$estimatedSeconds) {
            return null;
        }

        $minutes = round($estimatedSeconds / 60);
        if ($minutes < 1) {
            return '< 1 minute';
        } elseif ($minutes < 60) {
            return "{$minutes} minute" . ($minutes > 1 ? 's' : '');
        } else {
            $hours = round($minutes / 60, 1);
            return "{$hours} hour" . ($hours > 1 ? 's' : '');
        }
    }

    public function render()
    {
        return view('livewire.import.components.import-progress-tracker');
    }
}