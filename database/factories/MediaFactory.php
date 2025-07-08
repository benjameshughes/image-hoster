<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Enums\StorageDisk;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->slug(2).'.jpg';
        
        return [
            'user_id' => User::factory(),
            'name' => $filename,
            'original_name' => $this->faker->words(2, true).'.jpg',
            'path' => 'uploads/'.date('Y/m/d').'/'.$filename,
            'directory' => 'uploads/'.date('Y/m/d'),
            'disk' => $this->faker->randomElement(StorageDisk::cases()),
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(1024, 5242880), // 1KB to 5MB
            'media_type' => MediaType::IMAGE,
            'width' => $this->faker->numberBetween(100, 2000),
            'height' => $this->faker->numberBetween(100, 2000),
            'alt_text' => $this->faker->optional()->sentence(),
            'description' => $this->faker->optional()->paragraph(),
            'tags' => $this->faker->optional()->randomElements(
                ['nature', 'landscape', 'portrait', 'abstract', 'urban', 'travel'],
                $this->faker->numberBetween(0, 3)
            ),
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
            'is_shareable' => $this->faker->boolean(70), // 70% chance of being shareable
            'unique_id' => Str::random(32),
            'slug' => Str::slug($this->faker->words(2, true)),
            'file_hash' => hash('sha256', $filename.$this->faker->unixTime()),
            'source' => $this->faker->optional()->randomElement(['upload', 'wordpress', 'api']),
        ];
    }

    /**
     * Create an image media item
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => MediaType::IMAGE,
            'mime_type' => $this->faker->randomElement(['image/jpeg', 'image/png', 'image/gif', 'image/webp']),
            'width' => $this->faker->numberBetween(100, 2000),
            'height' => $this->faker->numberBetween(100, 2000),
        ]);
    }

    /**
     * Create a video media item
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => MediaType::VIDEO,
            'mime_type' => $this->faker->randomElement(['video/mp4', 'video/avi', 'video/mov']),
            'width' => $this->faker->numberBetween(480, 1920),
            'height' => $this->faker->numberBetween(360, 1080),
            'name' => $this->faker->slug(2).'.mp4',
            'original_name' => $this->faker->words(2, true).'.mp4',
        ]);
    }

    /**
     * Create a document media item
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => MediaType::DOCUMENT,
            'mime_type' => $this->faker->randomElement(['application/pdf', 'application/msword', 'text/plain']),
            'width' => null,
            'height' => null,
            'name' => $this->faker->slug(2).'.pdf',
            'original_name' => $this->faker->words(2, true).'.pdf',
        ]);
    }

    /**
     * Create a large file
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => $this->faker->numberBetween(10485760, 104857600), // 10MB to 100MB
        ]);
    }

    /**
     * Create a shareable media item
     */
    public function shareable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shareable' => true,
        ]);
    }

    /**
     * Create a non-shareable media item
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shareable' => false,
        ]);
    }

    /**
     * Create media with specific disk
     */
    public function onDisk(StorageDisk $disk): static
    {
        return $this->state(fn (array $attributes) => [
            'disk' => $disk,
        ]);
    }

    /**
     * Create media with tags
     */
    public function withTags(array $tags): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => $tags,
        ]);
    }

    /**
     * Create media from WordPress import
     */
    public function fromWordPress(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'wordpress',
        ]);
    }
}