<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'source_id',
        'source_url',
        'title',
        'source_metadata',
        'media_id',
        'status',
        'error_message',
        'retry_count',
        'file_size',
        'mime_type',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_metadata' => 'array',
            'file_size' => 'integer',
            'retry_count' => 'integer',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the import this item belongs to
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * Get the created media file
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(int $mediaId): void
    {
        $this->update([
            'status' => 'completed',
            'media_id' => $mediaId,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark as duplicate review needed
     */
    public function markAsDuplicateReview(int $mediaId): void
    {
        $this->update([
            'status' => 'duplicate_review',
            'media_id' => $mediaId,
            'processed_at' => now(),
        ]);
    }

    /**
     * Increment retry count
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    /**
     * Check if can be retried
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->status === 'failed' && $this->retry_count < $maxRetries;
    }

    /**
     * Reset for retry
     */
    public function resetForRetry(): void
    {
        $this->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    /**
     * Get WordPress metadata
     */
    public function getWordPressMetadata(): array
    {
        return $this->source_metadata ?? [];
    }

    /**
     * Get WordPress title
     */
    public function getWordPressTitle(): ?string
    {
        return $this->source_metadata['title']['rendered'] ?? 
               $this->source_metadata['title'] ?? 
               $this->title;
    }

    /**
     * Get WordPress caption
     */
    public function getWordPressCaption(): ?string
    {
        return $this->source_metadata['caption']['rendered'] ?? 
               $this->source_metadata['caption'] ?? 
               null;
    }

    /**
     * Get WordPress alt text
     */
    public function getWordPressAltText(): ?string
    {
        return $this->source_metadata['alt_text'] ?? null;
    }

    /**
     * Get WordPress description
     */
    public function getWordPressDescription(): ?string
    {
        return $this->source_metadata['description']['rendered'] ?? 
               $this->source_metadata['description'] ?? 
               null;
    }

    /**
     * Get WordPress upload date
     */
    public function getWordPressUploadDate(): ?string
    {
        return $this->source_metadata['date'] ?? 
               $this->source_metadata['date_gmt'] ?? 
               null;
    }
}