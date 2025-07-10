<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::RUNNING => 'Running',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'neutral',
            self::RUNNING => 'info',
            self::PAUSED => 'warning',
            self::COMPLETED => 'success',
            self::FAILED => 'error',
            self::CANCELLED => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'o-clock',
            self::RUNNING => 'o-arrow-path',
            self::PAUSED => 'o-pause',
            self::COMPLETED => 'o-check-circle',
            self::FAILED => 'o-x-circle',
            self::CANCELLED => 'o-stop',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::PENDING, self::RUNNING => true,
            self::PAUSED, self::COMPLETED, self::FAILED, self::CANCELLED => false,
        };
    }

    public function canBePaused(): bool
    {
        return $this === self::RUNNING;
    }

    public function canBeResumed(): bool
    {
        return $this === self::PAUSED;
    }


    public function canBeCancelled(): bool
    {
        return match ($this) {
            self::PENDING, self::RUNNING, self::PAUSED => true,
            self::COMPLETED, self::FAILED, self::CANCELLED => false,
        };
    }

    public function canBeRetried(): bool
    {
        return match ($this) {
            self::FAILED, self::CANCELLED => true,
            default => false,
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isRunning(): bool
    {
        return $this === self::RUNNING;
    }

    public function isPaused(): bool
    {
        return $this === self::PAUSED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
}