<?php

namespace App\Enums;

enum StorageDisk: string
{
    case LOCAL = 'local';
    case PUBLIC = 'public';
    case SPACES = 'spaces';
    case S3 = 's3';
    case R2 = 'r2';

    public function label(): string
    {
        return match ($this) {
            self::LOCAL => 'Local Storage',
            self::PUBLIC => 'Public Storage',
            self::SPACES => 'DigitalOcean Spaces',
            self::S3 => 'Amazon S3',
            self::R2 => 'Cloudflare R2',
        };
    }

    public function isCloud(): bool
    {
        return match ($this) {
            self::SPACES, self::S3, self::R2 => true,
            self::LOCAL, self::PUBLIC => false,
        };
    }
}
