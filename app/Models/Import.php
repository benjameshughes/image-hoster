<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source',
        'name',
        'config',
        'total_items',
        'processed_items',
        'successful_items',
        'failed_items',
        'duplicate_items',
        'status',
        'started_at',
        'completed_at',
        'summary',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'summary' => 'array',
            'status' => ImportStatus::class,
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'successful_items' => 'integer',
            'failed_items' => 'integer',
            'duplicate_items' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this import
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the import items
     */
    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    /**
     * Get pending items
     */
    public function pendingItems(): HasMany
    {
        return $this->items()->where('status', 'pending');
    }

    /**
     * Get failed items
     */
    public function failedItems(): HasMany
    {
        return $this->items()->where('status', 'failed');
    }

    /**
     * Get successful items
     */
    public function successfulItems(): HasMany
    {
        return $this->items()->where('status', 'completed');
    }

    /**
     * Get progress percentage
     */
    public function progressPercentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->total_items === 0) {
                    return 0;
                }
                
                return round(($this->processed_items / $this->total_items) * 100, 2);
            }
        );
    }

    /**
     * Get success rate percentage
     */
    public function successRate(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->processed_items === 0) {
                    return 0;
                }
                
                return round(($this->successful_items / $this->processed_items) * 100, 2);
            }
        );
    }

    /**
     * Get duration in seconds
     */
    public function duration(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->started_at) {
                    return null;
                }
                
                $end = $this->completed_at ?? now();
                
                return $this->started_at->diffInSeconds($end);
            }
        );
    }

    /**
     * Get formatted duration
     */
    public function formattedDuration(): Attribute
    {
        return Attribute::make(
            get: function () {
                $duration = $this->duration;
                
                if (! $duration) {
                    return '-';
                }
                
                return match (true) {
                    $duration >= 3600 => round($duration / 3600, 1) . ' hours',
                    $duration >= 60 => round($duration / 60, 1) . ' minutes',
                    default => $duration . ' seconds',
                };
            }
        );
    }

    /**
     * Get estimated time remaining
     */
    public function estimatedTimeRemaining(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->processed_items === 0 || $this->status !== ImportStatus::RUNNING) {
                    return null;
                }
                
                $itemsRemaining = $this->total_items - $this->processed_items;
                $secondsPerItem = $this->duration / $this->processed_items;
                
                return round($itemsRemaining * $secondsPerItem);
            }
        );
    }

    /**
     * Mark import as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => ImportStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark import as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => ImportStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark import as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => ImportStatus::FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Pause the import
     */
    public function pause(): void
    {
        if ($this->status === ImportStatus::RUNNING) {
            $this->update(['status' => ImportStatus::PAUSED]);
        }
    }

    /**
     * Resume the import
     */
    public function resume(): void
    {
        if ($this->status === ImportStatus::PAUSED) {
            $this->update(['status' => ImportStatus::RUNNING]);
        }
    }

    /**
     * Cancel the import
     */
    public function cancel(): void
    {
        if ($this->status->isActive()) {
            $this->update([
                'status' => ImportStatus::CANCELLED,
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Increment processed items
     */
    public function incrementProcessed(bool $successful = true, bool $duplicate = false): void
    {
        $updates = ['processed_items' => $this->processed_items + 1];
        
        if ($successful) {
            $updates['successful_items'] = $this->successful_items + 1;
        } else {
            $updates['failed_items'] = $this->failed_items + 1;
        }
        
        if ($duplicate) {
            $updates['duplicate_items'] = $this->duplicate_items + 1;
        }
        
        $this->update($updates);
    }

    /**
     * Get the current item being processed
     */
    public function currentItem(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items()
                ->where('status', 'processing')
                ->latest()
                ->first()
        );
    }
}