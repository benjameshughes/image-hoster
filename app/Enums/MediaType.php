<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case ARCHIVE = 'archive';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::VIDEO => 'Video',
            self::AUDIO => 'Audio',
            self::DOCUMENT => 'Document',
            self::ARCHIVE => 'Archive',
            self::OTHER => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::IMAGE => 'o-photo',
            self::VIDEO => 'o-video-camera',
            self::AUDIO => 'o-musical-note',
            self::DOCUMENT => 'o-document-text',
            self::ARCHIVE => 'o-archive-box',
            self::OTHER => 'o-document',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IMAGE => 'primary',
            self::VIDEO => 'secondary',
            self::AUDIO => 'accent',
            self::DOCUMENT => 'info',
            self::ARCHIVE => 'warning',
            self::OTHER => 'neutral',
        };
    }

    public function extensions(): array
    {
        return match ($this) {
            self::IMAGE => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'tiff'],
            self::VIDEO => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', 'mpg', 'mpeg'],
            self::AUDIO => ['mp3', 'wav', 'ogg', 'aac', 'flac', 'wma', 'm4a', 'opus'],
            self::DOCUMENT => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'],
            self::ARCHIVE => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
            self::OTHER => [],
        };
    }

    public function mimeTypes(): array
    {
        return match ($this) {
            self::IMAGE => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'image/bmp', 'image/x-icon', 'image/tiff',
            ],
            self::VIDEO => [
                'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo',
                'video/x-flv', 'video/webm', 'video/x-matroska', 'video/x-m4v',
            ],
            self::AUDIO => [
                'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/aac', 'audio/flac',
                'audio/x-ms-wma', 'audio/mp4', 'audio/opus',
            ],
            self::DOCUMENT => [
                'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain', 'text/rtf', 'application/vnd.oasis.opendocument.text',
            ],
            self::ARCHIVE => [
                'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
                'application/x-tar', 'application/gzip', 'application/x-bzip2', 'application/x-xz',
            ],
            self::OTHER => [],
        };
    }

    public static function fromMimeType(string $mimeType): self
    {
        foreach (self::cases() as $case) {
            if (in_array($mimeType, $case->mimeTypes(), true)) {
                return $case;
            }
        }

        return self::OTHER;
    }

    public static function fromExtension(string $extension): self
    {
        $extension = strtolower(ltrim($extension, '.'));

        foreach (self::cases() as $case) {
            if (in_array($extension, $case->extensions(), true)) {
                return $case;
            }
        }

        return self::OTHER;
    }

    public function supportsPreview(): bool
    {
        return match ($this) {
            self::IMAGE, self::DOCUMENT => true,
            self::VIDEO, self::AUDIO, self::ARCHIVE, self::OTHER => false,
        };
    }

    public function supportsThumbnails(): bool
    {
        return match ($this) {
            self::IMAGE, self::VIDEO, self::DOCUMENT => true,
            self::AUDIO, self::ARCHIVE, self::OTHER => false,
        };
    }

    public function supportsMetadataExtraction(): bool
    {
        return match ($this) {
            self::IMAGE, self::VIDEO, self::AUDIO, self::DOCUMENT => true,
            self::ARCHIVE, self::OTHER => false,
        };
    }
}