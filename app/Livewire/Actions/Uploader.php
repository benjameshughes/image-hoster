<?php

namespace App\Livewire\Actions;

use App\Models\Image;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Facades\Upload;

class Uploader extends Component
{
    use WithFileUploads;

    #[Rule(['files.*' => 'required|image|max:102400'])]
    public array $files = [];
    public array $uploadedFiles = [];

    public function updatedFiles()
    {
        $this->validate();
        $this->save();
    }

    /**
     * Process and save uploaded files with progress tracking
     */
    public function save(): void
    {
        $uploadedFiles = collect($this->uploadedFiles ?? []);
        $totalFiles = count($this->files);
        $processedFiles = 0;
        $errors = [];

        // Dispatch start event
        $this->dispatch('upload-started', ['total' => $totalFiles]);

        foreach ($this->files as $index => $file) {
            try {
                // Update progress
                $this->dispatch('upload-progress', [
                    'current' => $processedFiles + 1,
                    'total' => $totalFiles,
                    'filename' => $file->getClientOriginalName()
                ]);

                $result = Upload::make()
                    ->file($file)
                    ->disk('spaces')
                    ->directory('uploads/images')
                    ->randomFilename()
                    ->public()
                    ->process();

                $uploadedFiles->push([
                    'id' => $result['record']->id ?? null,
                    'name' => $file->getClientOriginalName(),
                    'filename' => $result['filename'],
                    'size' => $result['size'],
                    'mime' => $result['mime_type'],
                    'url' => $result['url'],
                    'path' => $result['path'],
                    'uploaded_at' => now()->toISOString(),
                ]);

                $file->delete();
                $processedFiles++;

            } catch (\Exception $e) {
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];

                try {
                    $file->delete();
                } catch (\Exception $cleanupError) {
                    logger()->warning('Cleanup failed', [
                        'file' => $file->getClientOriginalName(),
                        'error' => $cleanupError->getMessage()
                    ]);
                }
            }
        }

        $this->uploadedFiles = $uploadedFiles->toArray();
        $this->files = [];

        // Dispatch completion event
        $this->dispatch('upload-completed', [
            'successful' => $processedFiles,
            'failed' => count($errors),
            'errors' => $errors
        ]);
    }

    /**
     * Format file size for display
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Remove a specific uploaded file
     */
    public function removeFile(int $index): void
    {
        if (isset($this->uploadedFiles[$index])) {
            $file = $this->uploadedFiles[$index];

            // Optionally delete from storage
            try {
                if (isset($file['path'])) {
                    Storage::disk('r2')->delete($file['path']);
                }

                // Remove from database if stored
                if (isset($file['id'])) {
                    Image::find($file['id'])?->delete();
                }
            } catch (\Exception $e) {
                logger()->warning('Failed to delete file', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
            }

            // Remove from array
            unset($this->uploadedFiles[$index]);
            $this->uploadedFiles = array_values($this->uploadedFiles); // Re-index array

            $this->dispatch('file-removed', ['filename' => $file['name']]);
            $this->reset('uploadedFiles', 'files');
        }
    }

    /**
     * Clear all uploaded files
     */
    public function clear(): void
    {
        // Optionally delete files from storage
        foreach ($this->uploadedFiles as $file) {
            try {
                if (isset($file['path'])) {
                    Storage::disk('r2')->delete($file['path']);
                }
                if (isset($file['id'])) {
                    Image::find($file['id'])?->delete();
                }
            } catch (\Exception $e) {
                logger()->warning('Failed to delete file during clear', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->uploadedFiles = [];
        $this->files = [];

        $this->dispatch('files-cleared');
    }

}
