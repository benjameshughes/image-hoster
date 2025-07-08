<?php

declare(strict_types=1);

namespace App\Jobs\WordPress;

use App\Enums\DuplicateStatus;
use App\Enums\MediaType;
use App\Models\DuplicateReview;
use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectDuplicatesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 120; // 2 minutes
    public int $tries = 2;

    public function __construct(
        public readonly Media $media
    ) {}

    public function handle(): void
    {
        Log::info('Starting duplicate detection', [
            'media_id' => $this->media->id,
            'media_name' => $this->media->name,
            'file_hash' => $this->media->file_hash,
        ]);

        try {
            $duplicatesFound = 0;

            // 1. Check for exact hash matches (already handled in import, but double-check)
            $exactDuplicates = $this->findExactDuplicates();
            if ($exactDuplicates->isNotEmpty()) {
                foreach ($exactDuplicates as $duplicate) {
                    $this->createDuplicateReview($duplicate, 100.0, 'hash');
                    $duplicatesFound++;
                }
            }

            // 2. Check for perceptual duplicates (images only)
            if ($this->media->media_type === MediaType::IMAGE && $duplicatesFound < 5) {
                $perceptualDuplicates = $this->findPerceptualDuplicates();
                foreach ($perceptualDuplicates as $duplicate => $similarity) {
                    if ($similarity >= 85.0) { // High similarity threshold
                        $this->createDuplicateReview(
                            Media::find($duplicate),
                            $similarity,
                            'perceptual'
                        );
                        $duplicatesFound++;
                    }
                }
            }

            // 3. Check for filename similarity
            if ($duplicatesFound < 3) {
                $filenameDuplicates = $this->findFilenameDuplicates();
                foreach ($filenameDuplicates as $duplicate => $similarity) {
                    if ($similarity >= 90.0) { // Very high similarity for filenames
                        $this->createDuplicateReview(
                            Media::find($duplicate),
                            $similarity,
                            'filename'
                        );
                        $duplicatesFound++;
                    }
                }
            }

            // Update media duplicate status
            if ($duplicatesFound > 0) {
                $this->media->update(['duplicate_status' => DuplicateStatus::PENDING_REVIEW]);
                
                Log::info('Duplicates detected and marked for review', [
                    'media_id' => $this->media->id,
                    'duplicates_found' => $duplicatesFound,
                ]);
            } else {
                $this->media->update(['duplicate_status' => DuplicateStatus::UNIQUE]);
                
                Log::info('No duplicates found, marked as unique', [
                    'media_id' => $this->media->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error during duplicate detection', [
                'media_id' => $this->media->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't fail the whole process for duplicate detection errors
            $this->media->update(['duplicate_status' => DuplicateStatus::UNIQUE]);
        }
    }

    /**
     * Find exact hash duplicates
     */
    private function findExactDuplicates()
    {
        if (! $this->media->file_hash) {
            return collect();
        }

        return Media::where('file_hash', $this->media->file_hash)
            ->where('id', '!=', $this->media->id)
            ->where('user_id', $this->media->user_id) // Only check user's own files
            ->get();
    }

    /**
     * Find perceptual duplicates for images
     */
    private function findPerceptualDuplicates(): array
    {
        // For now, implement a simple similarity check
        // In production, you'd use a library like jenssegers/imagehash
        
        $similarities = [];
        
        // Get other images from the same user
        $otherImages = Media::where('media_type', MediaType::IMAGE)
            ->where('id', '!=', $this->media->id)
            ->where('user_id', $this->media->user_id)
            ->where('width', '>', 0) // Only images with dimensions
            ->where('height', '>', 0)
            ->get(['id', 'width', 'height', 'size', 'name']);

        foreach ($otherImages as $otherImage) {
            $similarity = $this->calculateImageSimilarity($this->media, $otherImage);
            
            if ($similarity >= 70.0) { // Minimum threshold for consideration
                $similarities[$otherImage->id] = $similarity;
            }
        }

        // Sort by similarity descending
        arsort($similarities);
        
        return array_slice($similarities, 0, 5, true); // Max 5 duplicates
    }

    /**
     * Calculate image similarity based on dimensions and size
     */
    private function calculateImageSimilarity(Media $media1, Media $media2): float
    {
        $similarity = 0.0;
        
        // Dimension similarity (40% weight)
        if ($media1->width && $media1->height && $media2->width && $media2->height) {
            $aspectRatio1 = $media1->width / $media1->height;
            $aspectRatio2 = $media2->width / $media2->height;
            
            $aspectSimilarity = 1 - abs($aspectRatio1 - $aspectRatio2) / max($aspectRatio1, $aspectRatio2);
            $similarity += $aspectSimilarity * 40;
            
            // Exact dimension match bonus
            if ($media1->width === $media2->width && $media1->height === $media2->height) {
                $similarity += 20;
            }
        }
        
        // File size similarity (30% weight)
        if ($media1->size && $media2->size) {
            $sizeDiff = abs($media1->size - $media2->size) / max($media1->size, $media2->size);
            $sizeSimilarity = 1 - $sizeDiff;
            $similarity += $sizeSimilarity * 30;
        }
        
        // Filename similarity (30% weight)
        $filenameSimilarity = $this->calculateStringSimilarity(
            pathinfo($media1->name, PATHINFO_FILENAME),
            pathinfo($media2->name, PATHINFO_FILENAME)
        );
        $similarity += $filenameSimilarity * 30;
        
        return min(100.0, max(0.0, $similarity));
    }

    /**
     * Find filename duplicates
     */
    private function findFilenameDuplicates(): array
    {
        $similarities = [];
        $baseFilename = pathinfo($this->media->name, PATHINFO_FILENAME);
        
        // Get other media with similar names
        $otherMedia = Media::where('id', '!=', $this->media->id)
            ->where('user_id', $this->media->user_id)
            ->get(['id', 'name']);

        foreach ($otherMedia as $otherFile) {
            $otherBasename = pathinfo($otherFile->name, PATHINFO_FILENAME);
            $similarity = $this->calculateStringSimilarity($baseFilename, $otherBasename);
            
            if ($similarity >= 80.0) { // High threshold for filename similarity
                $similarities[$otherFile->id] = $similarity;
            }
        }

        // Sort by similarity descending
        arsort($similarities);
        
        return array_slice($similarities, 0, 3, true); // Max 3 filename duplicates
    }

    /**
     * Calculate string similarity percentage
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) {
            return 100.0;
        }
        
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        // Use Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        $distance = levenshtein($str1, $str2);
        
        if ($distance === -1) { // Strings too long for levenshtein
            // Fallback to simple character similarity
            $similarity = similar_text($str1, $str2, $percent);
            return $percent;
        }
        
        return (1 - ($distance / $maxLen)) * 100;
    }

    /**
     * Create duplicate review record
     */
    private function createDuplicateReview(Media $duplicateOf, float $similarity, string $detectionType): void
    {
        // Check if review already exists
        $existingReview = DuplicateReview::where('media_id', $this->media->id)
            ->where('duplicate_of_id', $duplicateOf->id)
            ->first();

        if ($existingReview) {
            // Update with higher similarity if found
            if ($similarity > $existingReview->similarity_score) {
                $existingReview->update([
                    'similarity_score' => $similarity,
                    'detection_type' => $detectionType,
                ]);
            }
            return;
        }

        // Create comparison data
        $comparisonData = $this->buildComparisonData($this->media, $duplicateOf);

        DuplicateReview::create([
            'media_id' => $this->media->id,
            'duplicate_of_id' => $duplicateOf->id,
            'similarity_score' => $similarity,
            'detection_type' => $detectionType,
            'comparison_data' => $comparisonData,
        ]);

        Log::info('Duplicate review created', [
            'media_id' => $this->media->id,
            'duplicate_of_id' => $duplicateOf->id,
            'similarity_score' => $similarity,
            'detection_type' => $detectionType,
        ]);
    }

    /**
     * Build comparison data for review
     */
    private function buildComparisonData(Media $media1, Media $media2): array
    {
        return [
            'file_sizes' => [
                'new' => $media1->size,
                'original' => $media2->size,
                'difference' => $media1->size - $media2->size,
            ],
            'dimensions' => [
                'new' => [
                    'width' => $media1->width,
                    'height' => $media1->height,
                ],
                'original' => [
                    'width' => $media2->width,
                    'height' => $media2->height,
                ],
            ],
            'dates' => [
                'new_uploaded' => $media1->created_at->toISOString(),
                'original_uploaded' => $media2->created_at->toISOString(),
            ],
            'sources' => [
                'new_source' => $media1->source ?? 'direct-upload',
                'original_source' => $media2->source ?? 'direct-upload',
            ],
            'filenames' => [
                'new' => $media1->name,
                'original' => $media2->name,
            ],
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Duplicate detection job failed', [
            'media_id' => $this->media->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mark as unique if detection fails
        $this->media->update(['duplicate_status' => DuplicateStatus::UNIQUE]);
    }

    /**
     * Get unique job ID
     */
    public function uniqueId(): string
    {
        return "detect-duplicates-{$this->media->id}";
    }

    /**
     * Retry backoff
     */
    public function backoff(): array
    {
        return [30, 60]; // 30 sec, 1 min
    }
}