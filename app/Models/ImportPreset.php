<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportPreset extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'config',
        'is_default',
        'is_global',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
        'is_global' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get presets available to a user (their own + global presets)
     */
    public static function availableForUser(int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('is_global', true);
            })
            ->orderBy('is_global', 'desc')
            ->orderBy('is_default', 'desc')
            ->orderBy('name');
    }

    /**
     * Get default preset for a user
     */
    public static function getDefaultForUser(int $userId): ?static
    {
        return static::availableForUser($userId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Set as default preset for the user
     */
    public function setAsDefault(): void
    {
        // Remove default flag from other user presets
        static::where('user_id', $this->user_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get preset configuration with defaults
     */
    public function getConfigWithDefaults(): array
    {
        return array_merge([
            'storage_disk' => 'spaces',
            'process_images' => true,
            'generate_thumbnails' => true,
            'compress_images' => true,
            'skip_duplicates' => true,
            'duplicate_strategy' => 'skip',
            'media_types' => ['image', 'video', 'audio', 'document'],
            'from_date' => null,
            'to_date' => null,
            'max_items' => null,
            'import_path' => 'wordpress/{year}/{month}',
        ], $this->config);
    }

    /**
     * Create preset from import configuration
     */
    public static function createFromImport(Import $import, string $name, ?string $description = null): static
    {
        $config = $import->config;
        
        // Remove sensitive data from config
        unset($config['wordpress_url'], $config['username'], $config['password']);

        return static::create([
            'user_id' => $import->user_id,
            'name' => $name,
            'description' => $description,
            'config' => $config,
        ]);
    }
}