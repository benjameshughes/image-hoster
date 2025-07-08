<?php

declare(strict_types=1);

namespace App\Enums;

enum DuplicateStatus: string
{
    case UNIQUE = 'unique';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::UNIQUE => 'Unique',
            self::PENDING_REVIEW => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNIQUE => 'success',
            self::PENDING_REVIEW => 'warning',
            self::APPROVED => 'info',
            self::REJECTED => 'error',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::UNIQUE => 'o-check-circle',
            self::PENDING_REVIEW => 'o-clock',
            self::APPROVED => 'o-check',
            self::REJECTED => 'o-x-mark',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UNIQUE => 'This file is unique and has no duplicates',
            self::PENDING_REVIEW => 'This file has potential duplicates awaiting review',
            self::APPROVED => 'This file has been approved as a duplicate',
            self::REJECTED => 'This file has been rejected as not a duplicate',
        };
    }
}