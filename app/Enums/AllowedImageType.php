<?php

namespace App\Enums;

enum AllowedImageType: string
{
    case JPEG = 'jpeg';
    case JPG = 'jpg';
    case PNG = 'png';
    case GIF = 'gif';
    case WEBP = 'webp';
    case SVG = 'svg';
    case BMP = 'bmp';
    case TIFF = 'tiff';

    public function mimeType(): string
    {
        return match ($this) {
            self::JPEG, self::JPG => 'image/jpeg',
            self::PNG => 'image/png',
            self::GIF => 'image/gif',
            self::WEBP => 'image/webp',
            self::SVG => 'image/svg+xml',
            self::BMP => 'image/bmp',
            self::TIFF => 'image/tiff',
        };
    }

    public function isAnimated(): bool
    {
        return match ($this) {
            self::GIF, self::WEBP => true,
            default => false,
        };
    }

    public static function fromMimeType(string $mimeType): ?self
    {
        return match ($mimeType) {
            'image/jpeg' => self::JPEG,
            'image/png' => self::PNG,
            'image/gif' => self::GIF,
            'image/webp' => self::WEBP,
            'image/svg+xml' => self::SVG,
            'image/bmp' => self::BMP,
            'image/tiff' => self::TIFF,
            default => null,
        };
    }

    public static function getAllMimeTypes(): array
    {
        return array_map(fn ($case) => $case->mimeType(), self::cases());
    }
}
