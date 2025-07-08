<?php

namespace App\Models;

use App\Enums\AllowedImageType;
use App\Enums\DuplicateStatus;
use App\Enums\MediaType;
use App\Enums\StorageDisk;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $table = 'media';

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
        'media_type',
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
        'duration',
        'bitrate',
        'pages',
        'file_hash',
        'perceptual_hash',
        'duplicate_status',
        'duplicate_of_id',
        'similarity_score',
        'source',
        'source_id',
        'source_metadata',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'disk' => StorageDisk::class,
            'media_type' => MediaType::class,
            'image_type' => AllowedImageType::class,
            'duplicate_status' => DuplicateStatus::class,
            'is_public' => 'boolean',
            'is_shareable' => 'boolean',
            'shared_at' => 'datetime',
            'size' => 'integer',
            'compressed_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'thumbnail_width' => 'integer',
            'thumbnail_height' => 'integer',
            'bitrate' => 'integer',
            'pages' => 'integer',
            'view_count' => 'integer',
            'similarity_score' => 'float',
            'tags' => 'array',
            'metadata' => 'array',
            'source_metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this media
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the original media if this is a duplicate
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'duplicate_of_id');
    }

    /**
     * Get all duplicates of this media
     */
    public function duplicates()
    {
        return $this->hasMany(Media::class, 'duplicate_of_id');
    }

    /**
     * Get duplicate reviews for this media
     */
    public function duplicateReviews()
    {
        return $this->hasMany(DuplicateReview::class, 'media_id');
    }

    /**
     * Get import item if this media was imported
     */
    public function importItem()
    {
        return $this->hasOne(ImportItem::class, 'media_id');
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
            get: fn () => route('media.download', $this)
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
     * Check if media has dimensions (for images/videos)
     */
    public function hasDimensions(): bool
    {
        return $this->width && $this->height;
    }

    /**
     * Check if this is an image
     */
    public function isImage(): bool
    {
        return $this->media_type === MediaType::IMAGE;
    }

    /**
     * Check if this is a video
     */
    public function isVideo(): bool
    {
        return $this->media_type === MediaType::VIDEO;
    }

    /**
     * Check if this is audio
     */
    public function isAudio(): bool
    {
        return $this->media_type === MediaType::AUDIO;
    }

    /**
     * Check if this is a document
     */
    public function isDocument(): bool
    {
        return $this->media_type === MediaType::DOCUMENT;
    }

    /**
     * Check if media has a duplicate
     */
    public function hasDuplicate(): bool
    {
        return $this->duplicate_status !== DuplicateStatus::UNIQUE;
    }

    /**
     * Check if media is pending duplicate review
     */
    public function isPendingDuplicateReview(): bool
    {
        return $this->duplicate_status === DuplicateStatus::PENDING_REVIEW;
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
                ? route('media.public', $this->unique_id)
                : null
        );
    }

    /**
     * Check if media is shareable
     */
    public function isShareable(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['is_shareable'] ?? false
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
        static::creating(function (Media $media) {
            if (! $media->unique_id) {
                $media->unique_id = \Illuminate\Support\Str::random(32);
            }
            
            if (! $media->slug && $media->original_name) {
                $media->slug = \Illuminate\Support\Str::slug(pathinfo($media->original_name, PATHINFO_FILENAME));
            }
            
            // Set media type from mime type if not set
            if (! $media->media_type && $media->mime_type) {
                $media->media_type = MediaType::fromMimeType($media->mime_type);
            }
        });
        
        static::deleting(function (Media $media) {
            $media->deleteFile();
            
            // Also delete thumbnail and compressed versions
            if ($media->thumbnail_path && Storage::disk($media->disk->value)->exists($media->thumbnail_path)) {
                Storage::disk($media->disk->value)->delete($media->thumbnail_path);
            }
            
            if ($media->compressed_path && Storage::disk($media->disk->value)->exists($media->compressed_path)) {
                Storage::disk($media->disk->value)->delete($media->compressed_path);
            }
        });
    }
}
