<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'media_id',
        'duplicate_of_id',
        'similarity_score',
        'detection_type',
        'action',
        'comparison_data',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'similarity_score' => 'float',
            'comparison_data' => 'array',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'duplicate_of_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}