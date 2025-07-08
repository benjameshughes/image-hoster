# WordPress Media Importer Implementation Plan

## Project Overview

Build a professional WordPress media importer for the Laravel image management application with:
- Support for ALL media types (images, videos, audio, documents, archives)
- Duplicate detection with manual review system
- Queue-based processing with real-time UI updates
- WordPress REST API integration
- Beautiful Livewire reactive interface

## Requirements Summary

### Core Features
- [x] WordPress REST API integration with application password authentication
- [x] Import ALL media types from WordPress (not just images)
- [x] Queue one job per media file for reliability
- [x] Real-time progress updates via Livewire polling
- [x] Preserve all WordPress metadata (title, caption, description, alt text, upload date)
- [x] Use original files only (no compressed versions from WP)
- [x] Use existing Laravel file structure (not WordPress paths)

### Duplicate Detection System
- [x] Three-tier detection: Exact hash, Perceptual similarity, Filename similarity
- [x] Manual review interface with side-by-side comparison
- [x] Actions: Keep Both, Keep Original, Keep New, Merge Metadata
- [x] Beautiful UI for reviewing duplicates

### Technical Requirements
- [x] PHP 8.2+ with modern features (enums, match expressions, union types)
- [x] Laravel best practices (actions, services, fluent interfaces)
- [x] OOP patterns with readable, maintainable code
- [x] Comprehensive error handling and retry mechanisms
- [x] Database transactions for data integrity

## Architecture Overview

### Database Schema Changes

#### 1. Rename `images` table to `media`
```sql
-- Add new columns for media support
ALTER TABLE media ADD COLUMN media_type VARCHAR(50) DEFAULT 'image';
ALTER TABLE media ADD COLUMN duration VARCHAR(20) NULL; -- For video/audio
ALTER TABLE media ADD COLUMN bitrate INT NULL; -- For video/audio  
ALTER TABLE media ADD COLUMN pages INT NULL; -- For documents

-- Add duplicate detection columns
ALTER TABLE media ADD COLUMN perceptual_hash VARCHAR(64) NULL;
ALTER TABLE media ADD COLUMN duplicate_status VARCHAR(50) DEFAULT 'unique';
ALTER TABLE media ADD COLUMN duplicate_of_id BIGINT UNSIGNED NULL;
ALTER TABLE media ADD COLUMN similarity_score DECIMAL(5,2) NULL;

-- Add WordPress import tracking
ALTER TABLE media ADD COLUMN source VARCHAR(50) NULL;
ALTER TABLE media ADD COLUMN source_id VARCHAR(50) NULL;
ALTER TABLE media ADD COLUMN source_metadata JSON NULL;
```

#### 2. New Tables
```sql
-- Import tracking
CREATE TABLE imports (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    source VARCHAR(50) DEFAULT 'wordpress',
    name VARCHAR(255) NOT NULL,
    config JSON NOT NULL,
    total_items INT DEFAULT 0,
    processed_items INT DEFAULT 0,
    successful_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    duplicate_items INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    summary JSON NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Individual import items
CREATE TABLE import_items (
    id BIGINT PRIMARY KEY,
    import_id BIGINT NOT NULL,
    source_id VARCHAR(255) NOT NULL,
    source_url VARCHAR(1000) NOT NULL,
    title VARCHAR(255) NULL,
    source_metadata JSON NOT NULL,
    media_id BIGINT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    error_message TEXT NULL,
    retry_count INT DEFAULT 0,
    file_size INT NULL,
    mime_type VARCHAR(255) NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Duplicate review system
CREATE TABLE duplicate_reviews (
    id BIGINT PRIMARY KEY,
    media_id BIGINT NOT NULL,
    duplicate_of_id BIGINT NOT NULL,
    similarity_score DECIMAL(5,2) NOT NULL,
    detection_type VARCHAR(50) NOT NULL, -- hash, perceptual, filename
    action VARCHAR(50) DEFAULT 'pending', -- pending, keep_both, keep_original, keep_new, merge
    comparison_data JSON NULL,
    reviewed_by BIGINT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Enums

#### MediaType
```php
enum MediaType: string {
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case ARCHIVE = 'archive';
    case OTHER = 'other';
}
```

#### ImportStatus
```php  
enum ImportStatus: string {
    case PENDING = 'pending';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
```

#### DuplicateStatus
```php
enum DuplicateStatus: string {
    case UNIQUE = 'unique';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
```

## Implementation Phases

### Phase 1: Foundation ✅
- [x] Create enums (MediaType, ImportStatus, DuplicateStatus)
- [x] Create database migrations
- [x] Update Media model (rename from Image)
- [x] Create Import, ImportItem, DuplicateReview models

### Phase 2: WordPress Integration
- [ ] WordPress API Service (`App\Services\WordPress\WordPressApiService`)
- [ ] WordPress Authentication handling
- [ ] Media fetching with pagination
- [ ] Metadata extraction and mapping

### Phase 3: Import System
- [ ] Import Action (`App\Actions\WordPress\CreateImportAction`)
- [ ] Queue Jobs:
  - [ ] `AnalyzeWordPressMediaJob` - Discover all media
  - [ ] `ImportMediaItemJob` - Process individual files
  - [ ] `DetectDuplicatesJob` - Check for duplicates
- [ ] Extended UploaderService for WordPress imports

### Phase 4: Duplicate Detection
- [ ] Duplicate Detection Service
- [ ] Hash comparison (exact matches)
- [ ] Perceptual hashing for images (using ImageHash library)
- [ ] Filename similarity comparison
- [ ] Automatic duplicate review creation

### Phase 5: User Interface
- [ ] Import Dashboard (Livewire component)
- [ ] Real-time progress tracking
- [ ] Duplicate Review interface
- [ ] Side-by-side comparison UI
- [ ] Import history and management

### Phase 6: Error Handling & Polish
- [ ] Comprehensive error handling
- [ ] Retry mechanisms
- [ ] Import pause/resume functionality
- [ ] Cleanup and optimization
- [ ] Testing and validation

## Key Services Architecture

### WordPress API Service
```php
class WordPressApiService {
    public function authenticate(string $url, string $username, string $password): bool
    public function fetchMediaLibrary(int $page = 1, int $perPage = 100): array
    public function downloadMediaFile(string $url): UploadedFile
    public function extractMediaMetadata(array $wpMedia): array
}
```

### Import Action (Fluent Interface)
```php
class CreateImportAction {
    public static function make(): self
    public function forUser(User $user): self
    public function fromWordPress(string $url, string $username, string $password): self
    public function withName(string $name): self
    public function execute(): Import
}
```

### Duplicate Detection Service
```php
class DuplicateDetectionService {
    public function detectDuplicates(Media $media): Collection
    public function compareFiles(Media $media1, Media $media2): float
    public function createReview(Media $media, Media $duplicate, float $score): DuplicateReview
}
```

## Queue Jobs Structure

### AnalyzeWordPressMediaJob
- Connects to WordPress API
- Fetches all media with pagination
- Creates ImportItem records
- Updates Import totals
- Dispatches ImportMediaItemJob for each item

### ImportMediaItemJob
- Downloads individual file from WordPress
- Uses UploaderService to store file
- Maps WordPress metadata to Laravel structure
- Dispatches DetectDuplicatesJob
- Updates progress counters

### DetectDuplicatesJob
- Runs duplicate detection algorithms
- Creates DuplicateReview records if matches found
- Updates Media duplicate_status
- Triggers UI notifications

## User Interface Components

### Import Dashboard (`app/Livewire/Import/Dashboard.php`)
- Connection form (WP URL, credentials)
- Import progress display
- Real-time updates via polling
- Pause/Resume/Cancel controls
- Import history table

### Duplicate Review (`app/Livewire/Import/DuplicateReview.php`)
- Side-by-side media comparison
- Metadata differences highlighting
- Action buttons (Keep Both, Keep Original, etc.)
- Bulk review capabilities
- Filter by similarity score

### Progress Tracking (`app/Livewire/Import/ProgressTracker.php`)
- Overall import progress
- Individual file status
- Error reporting
- Estimated time remaining
- Success/failure statistics

## Error Handling Strategy

### Retry Logic
- Network failures: Retry up to 3 times with exponential backoff
- File download errors: Retry with different approach
- Storage failures: Clean up partial uploads

### Graceful Degradation
- Continue import even if some files fail
- Comprehensive error logging
- User-friendly error messages
- Ability to retry failed items

### Data Integrity
- Database transactions for critical operations
- Cleanup of orphaned files
- Rollback capabilities for failed imports

## Performance Considerations

### Queue Management
- Use separate queue for import jobs
- Process items in batches
- Monitor memory usage
- Implement queue worker scaling

### Storage Optimization
- Stream large file downloads
- Temporary file cleanup
- Progress caching
- Database query optimization

## Security Considerations

### WordPress Authentication
- Secure credential storage
- Application passwords (not user passwords)
- Connection validation
- Rate limiting API calls

### File Handling
- Validate file types and sizes
- Scan for malicious content
- Secure temporary file handling
- Proper file permissions

## Next Steps

1. **Phase 2**: Start with WordPress API Service implementation
2. **Test WordPress connection** and media fetching
3. **Build queue jobs** for reliable processing
4. **Create basic UI** for import management
5. **Implement duplicate detection** algorithms
6. **Polish UI** and add real-time updates

## Questions for Clarification

1. **WordPress Setup**: Do you have application passwords enabled on your WordPress site?
2. **File Storage**: Any specific cloud storage preferences for imported media?
3. **Duplicate Threshold**: What similarity percentage should trigger manual review?
4. **Import Limits**: Any restrictions on import size or file count?
5. **Metadata Priority**: Which WordPress metadata fields are most important to preserve?

---

**Status**: Foundation complete, ready for Phase 2 implementation
**Next**: WordPress API Service development