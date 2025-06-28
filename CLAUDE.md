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

### Important Notes
- The application is designed for cloud storage; local storage is secondary
- Multi-file uploads are handled with individual progress tracking
- Error handling includes automatic cleanup of partial uploads
- No image processing features currently implemented
- API routes not implemented (web-only application)