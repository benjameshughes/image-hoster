<?php

namespace App\Models;

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    /** @use HasFactory<\Database\Factories\ImageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'original_name',
        'mime_type',
        'size',
        'disk',
        'is_public',
        'directory',
        'user_id',
        'width',
        'height',
        'file_hash',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'disk' => StorageDisk::class,
            'is_public' => 'boolean',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this image
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the file URL
     */
    public function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_public
                ? Storage::disk($this->disk->value)->url($this->path)
                : Storage::disk($this->disk->value)->temporaryUrl($this->path, now()->addHour())
        );
    }

    /**
     * Get the download URL
     */
    public function downloadUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => route('images.download', $this)
        );
    }

    /**
     * Get formatted file size
     */
    public function formattedSize(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->size >= 1024 ** 3 => round($this->size / (1024 ** 3), 2).' GB',
                $this->size >= 1024 ** 2 => round($this->size / (1024 ** 2), 2).' MB',
                $this->size >= 1024 => round($this->size / 1024, 2).' KB',
                default => $this->size.' B',
            }
        );
    }

    /**
     * Get the image type enum
     */
    public function imageType(): Attribute
    {
        return Attribute::make(
            get: fn () => AllowedImageType::fromMimeType($this->mime_type)
        );
    }

    /**
     * Check if image has dimensions
     */
    public function hasDimensions(): bool
    {
        return $this->width && $this->height;
    }

    /**
     * Get aspect ratio
     */
    public function aspectRatio(): ?float
    {
        return $this->hasDimensions() ? $this->width / $this->height : null;
    }

    /**
     * Check if image exists on disk
     */
    public function exists(): bool
    {
        return Storage::disk($this->disk->value)->exists($this->path);
    }

    /**
     * Delete the image file from storage
     */
    public function deleteFile(): bool
    {
        if ($this->exists()) {
            return Storage::disk($this->disk->value)->delete($this->path);
        }

        return true;
    }

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::deleting(function (Image $image) {
            $image->deleteFile();
        });
    }
}
