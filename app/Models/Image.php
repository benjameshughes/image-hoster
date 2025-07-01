<?php

namespace App\Models;

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Image extends Model
{
    /** @use HasFactory<\Database\Factories\ImageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'original_name',
        'unique_id',
        'slug',
        'thumbnail_path',
        'compressed_path',
        'thumbnail_width',
        'thumbnail_height',
        'compressed_size',
        'mime_type',
        'image_type',
        'size',
        'disk',
        'is_public',
        'is_shareable',
        'shared_at',
        'view_count',
        'alt_text',
        'description',
        'tags',
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
            'image_type' => AllowedImageType::class,
            'is_public' => 'boolean',
            'is_shareable' => 'boolean',
            'shared_at' => 'datetime',
            'size' => 'integer',
            'compressed_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'thumbnail_width' => 'integer',
            'thumbnail_height' => 'integer',
            'view_count' => 'integer',
            'tags' => 'array',
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
     * Get the thumbnail URL
     */
    public function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumbnail_path 
                ? ($this->is_public
                    ? Storage::disk($this->disk->value)->url($this->thumbnail_path)
                    : Storage::disk($this->disk->value)->temporaryUrl($this->thumbnail_path, now()->addHour()))
                : $this->url
        );
    }

    /**
     * Get the compressed image URL
     */
    public function compressedUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->compressed_path 
                ? ($this->is_public
                    ? Storage::disk($this->disk->value)->url($this->compressed_path)
                    : Storage::disk($this->disk->value)->temporaryUrl($this->compressed_path, now()->addHour()))
                : $this->url
        );
    }

    /**
     * Get the public sharing URL using unique_id
     */
    public function shareableUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->unique_id && $this->is_shareable 
                ? route('images.public', $this->unique_id)
                : null
        );
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
        
        if (! $this->shared_at) {
            $this->update(['shared_at' => now()]);
        }
    }

    /**
     * Get thumbnail dimensions
     */
    public function thumbnailDimensions(): ?array
    {
        return $this->thumbnail_width && $this->thumbnail_height 
            ? ['width' => $this->thumbnail_width, 'height' => $this->thumbnail_height]
            : null;
    }

    /**
     * Check if image has thumbnail
     */
    public function hasThumbnail(): bool
    {
        return (bool) $this->thumbnail_path;
    }

    /**
     * Check if image has compressed version
     */
    public function hasCompressed(): bool
    {
        return (bool) $this->compressed_path;
    }

    /**
     * Get compression ratio as percentage
     */
    public function compressionRatio(): ?float
    {
        return $this->compressed_size && $this->size 
            ? round((1 - $this->compressed_size / $this->size) * 100, 1)
            : null;
    }

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::creating(function (Image $image) {
            if (! $image->unique_id) {
                $image->unique_id = \Illuminate\Support\Str::random(32);
            }
            
            if (! $image->slug && $image->original_name) {
                $image->slug = \Illuminate\Support\Str::slug(pathinfo($image->original_name, PATHINFO_FILENAME));
            }
        });
        
        static::deleting(function (Image $image) {
            $image->deleteFile();
            
            // Also delete thumbnail and compressed versions
            if ($image->thumbnail_path && Storage::disk($image->disk->value)->exists($image->thumbnail_path)) {
                Storage::disk($image->disk->value)->delete($image->thumbnail_path);
            }
            
            if ($image->compressed_path && Storage::disk($image->disk->value)->exists($image->compressed_path)) {
                Storage::disk($image->disk->value)->delete($image->compressed_path);
            }
        });
    }
}
