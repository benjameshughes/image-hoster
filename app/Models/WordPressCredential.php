<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class WordPressCredential extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'wordpress_url',
        'username',
        'password', // This will be automatically encrypted via the mutator
        'encrypted_password',
        'is_default',
        'last_used_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPasswordAttribute(): string
    {
        return Crypt::decryptString($this->encrypted_password);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['encrypted_password'] = Crypt::encryptString($value);
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function setAsDefault(): void
    {
        // Remove default from other credentials
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
