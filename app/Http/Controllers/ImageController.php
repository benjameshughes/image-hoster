<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Services\ImageUploader;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\ImageProcessor;

class ImageController extends Controller
{
    protected $imageProcessor;
    protected $uploader;

    public function __construct(ImageProcessor $imageProcessor = null, ImageUploader $uploader = null)
    {
        $this->imageProcessor = $imageProcessor ?? app(ImageProcessor::class);
        $this->uploader = $uploader ?? app(ImageUploader::class);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $images = Image::orderBy('created_at', 'desc')->get();
        // Show the index view
        return view('images.index', compact('images'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UploadedFile $image, callable $progressCallback = null)
    {
        try {
            $extension = $image->getClientOriginalExtension();
            $shortFilename = Str::random(10) . '.' . $extension;

            // Simulate progress for file upload
            if ($progressCallback) {
                $progressCallback(10);
            }

            $path = $image->storeAs('', $shortFilename, 'images');

            if ($progressCallback) {
                $progressCallback(50);
            }

            // Pass the short filename to load
            $processedPath = $this->imageProcessor
                ->load("images/{$shortFilename}", $shortFilename)
                ->resize(800) // Resize width to 800px, auto-height
                ->optimize()
                ->save("images/processed/{$shortFilename}");

            if ($progressCallback) {
                $progressCallback(80);
            }

            $uploadedImage = Image::create([
                'user_id' => auth()->id(),
                'filename' => $shortFilename,
                'original_filename' => $this->uploader->formatFilename($image->getClientOriginalName()),
                'mime_type' => $image->getMimeType(),
                'file_size' => $image->getSize(),
                'processed_path' => $processedPath,
            ]);

            if ($progressCallback) {
                $progressCallback(100);
            }

            return [
                'success' => true,
                'upload' => $uploadedImage,
                'message' => 'Image uploaded and processed successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error uploading and processing image: ' . $e->getMessage()
            ];
        }
    }

//    protected function storeSingle(UploadedFile $image)
//    {
//        $extension = $image->getClientOriginalExtension();
//        $shortFilename = Str::random(10) . '.' . $extension;
//
//        $path = $image->storeAs('', $shortFilename, 'images');
//
//        // Pass the short filename to load
//        $processedPath = $this->imageProcessor
//            ->load("images/{$shortFilename}", $shortFilename)
//            ->resize(800) // Resize width to 800px, auto-height
//            ->optimize()
//            ->save("images/processed/{$shortFilename}");
//
//        Image::create([
//            'user_id' => auth()->id(),
//            'filename' => $shortFilename,
//            'original_filename' => $this->uploader->formatFilename($image->getClientOriginalName()),
//            'mime_type' => $image->getMimeType(),
//            'file_size' => $image->getSize(),
//            'processed_path' => $processedPath,
//        ]);
//        isset($this->image) ? $result = ['success' => true, 'upload' => $image, 'message' => 'Image uploaded and processed successfully'] : $result = ['success' => false, 'message' => 'Error uploading and processing image'];
//    }
//
//    protected function storeMultiple(array $images)
//    {
//        $results = [];
//        foreach ($images as $image) {
//            if ($image instanceof UploadedFile) {
//                $results[] = $this->storeSingle($image);
//            }
//        }
//        return $results;
//    }

    /**
     * Display the specified resource.
     */
    public function show($filename)
    {
        // Check if the image exists
        if (!Storage::disk('images')->exists($filename)) {
            abort(404);
        }

        // Return the image
        return response()->streamDownload(function () use ($filename) {
            return Storage::disk('images')->download($filename);
        });
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Image $image)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Image $image)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Image $image)
    {
        {
            try {
                // Delete the file from storage
                if (Storage::disk('images')->exists($image->filename)) {
                    Storage::disk('images')->delete($image->filename);
                }

                // Delete the database record
                $image->delete();

                return [
                    'success' => true,
                    'message' => 'Image deleted successfully.'
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete image: ' . $e->getMessage()
                ];
            }
        }
    }

    public function download(Image $image)
    {
        // Check if the image exists
        if (!Storage::disk('images')->exists($image->filename)) {
            abort(404);
        }

        // Download the image
        return Storage::disk('images')->download($image->filename);
    }
}
