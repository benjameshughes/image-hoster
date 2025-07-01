# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12.10.2 image management application that allows users to upload, store, and manage images across multiple cloud storage providers. The application uses Livewire for reactive UI components and supports multi-file uploads with real-time progress tracking.

## Common Development Commands

### Running the Development Environment
```bash
# Start all services concurrently (recommended)
composer dev

# Or run services individually:
php artisan serve        # Laravel server
npm run dev             # Vite dev server
php artisan queue:listen # Queue worker
php artisan pail        # Real-time log viewer
```

### Building and Testing
```bash
# Build frontend assets for production
npm run build

# Run tests (using Pest)
php artisan test
./vendor/bin/pest

# Format PHP code
./vendor/bin/pint

# Run database migrations
php artisan migrate
php artisan migrate:fresh --seed  # Fresh with seeders
```

### Useful Artisan Commands
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Debug and monitor
php artisan tinker      # Interactive REPL
php artisan pail        # Real-time logs

# Regenerate autoload (if adding helpers or new classes)
composer dump-autoload
```

## Architecture Overview

### Technology Stack
- **Backend**: Laravel 12.10.2 with PHP 8.4+
- **Frontend**: Livewire 3.6 with Volt, Mary UI components, Tailwind CSS
- **Storage**: Multiple cloud providers (DigitalOcean Spaces, AWS S3, Cloudflare R2)
- **Database**: SQLite (default), migrations in `/database/migrations/`
- **Testing**: Pest PHP framework

### Key Architectural Components

1. **UploaderService** (`/app/Services/UploaderService.php`)
   - Central service for handling file uploads with fluent interface
   - Registered as `Upload` facade
   - Supports multiple storage backends and validation rules
   - Handles filename sanitization and cloud storage operations

2. **Livewire Components**
   - `App\Livewire\Image\Index`: Main image management (list, view, download, delete)
   - `App\Livewire\Actions\Uploader`: Multi-file upload with progress tracking
   - Authentication components use Livewire Volt in `/resources/views/livewire/`

3. **Storage Configuration**
   - Primary storage: DigitalOcean Spaces (configured as 'spaces' disk)
   - Multiple cloud providers configured in `config/filesystems.php`
   - Uploads stored with user ID prefix in cloud storage

4. **Database Structure**
   - `images` table: stores file metadata, paths, and storage information
   - User-Image relationship: User hasMany Images
   - Migrations define nullable `user_id` for optional user association

### Data Flow
1. **Upload Process**: Livewire component → UploaderService → Cloud Storage → Database
2. **Retrieval**: Database query → Generate cloud URL → Display/Download
3. **Real-time Updates**: Livewire events provide progress feedback during uploads

### Authentication
- Laravel Breeze with Livewire implementation
- Standard Laravel authentication flow
- ImagePolicy exists but requires implementation for authorization rules

### Frontend Architecture
- Mary UI provides pre-built Blade/Livewire components
- Tailwind CSS with DaisyUI for styling
- Vite handles asset bundling and HMR
- Livewire Flux for enhanced UI components

### Recent Improvements (PHP 8.2+ Refactoring)

**Modern PHP Features Implemented:**
- **Enums**: StorageDisk, AllowedImageType, UploadStatus with helper methods
- **Match Expressions**: File size formatting, disk resolution, validation logic
- **Union Types**: StorageDisk|string|Closure for flexible disk configuration
- **Named Arguments**: Enhanced UploaderService fluent API
- **Typed Properties**: Strict typing throughout models and services
- **Custom Exceptions**: Specific upload exceptions with detailed error messages

**Enhanced Security & Authorization:**
- Complete ImagePolicy implementation with proper authorization
- User-scoped image queries to prevent data leakage
- File hash duplicate detection to prevent storage waste
- Proper input validation and sanitization
- Security-focused filename handling

**Performance & Reliability:**
- Database transactions for upload operations
- Optimized database indexes for common queries
- Automatic file cleanup on errors
- EXIF metadata extraction for images
- Computed properties for efficient data access

**New Features Added:**
- Search and filtering capabilities in image listing
- Bulk operations (select all, delete multiple)
- Image metadata extraction (dimensions, EXIF data)
- File type filtering and statistics
- Progress tracking for uploads
- User-specific upload directories by date

### Helper Functions
- **gravatar($email, $size = 80, $default = 'mp', $rating = 'g')**: Generates Gravatar URLs for user avatars
  - Located in `app/helpers.php` and auto-loaded via composer.json
  - Used in navigation components for user profile pictures

### Important Notes
- The application is designed for cloud storage; local storage is secondary
- Multi-file uploads are handled with individual progress tracking
- Error handling includes automatic cleanup of partial uploads and database rollback
- Images are automatically organized by user ID and upload date
- Authorization policies ensure users can only access their own images
- File duplicate detection prevents storage waste using SHA256 hashing