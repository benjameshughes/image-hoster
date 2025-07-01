<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicImageController extends Controller
{
    /**
     * Display a publicly shared image
     */
    public function show(string $uniqueId)
    {
        $image = Image::where('unique_id', $uniqueId)
            ->where('is_shareable', true)
            ->firstOrFail();

        // Increment view count
        $image->incrementViews();

        return view('public.image', compact('image'));
    }

    /**
     * Serve the image file directly
     */
    public function serve(string $uniqueId, string $type = 'original')
    {
        $image = Image::where('unique_id', $uniqueId)
            ->where('is_shareable', true)
            ->firstOrFail();

        // Determine which version to serve
        $path = match ($type) {
            'thumbnail' => $image->thumbnail_path ?? $image->path,
            'compressed' => $image->compressed_path ?? $image->path,
            default => $image->path,
        };

        if (!Storage::disk($image->disk->value)->exists($path)) {
            abort(404);
        }

        // Increment view count only for original image views
        if ($type === 'original') {
            $image->incrementViews();
        }

        // Get file contents and headers
        $fileContents = Storage::disk($image->disk->value)->get($path);
        $mimeType = $image->mime_type;

        // Set cache headers for better performance
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($fileContents),
            'Cache-Control' => 'public, max-age=31536000', // 1 year
            'Expires' => now()->addYear()->toRfc7231String(),
        ];

        // Set content disposition for downloads
        if (request()->query('download') === '1') {
            $filename = $image->original_name ?? $image->name;
            $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        }

        return response($fileContents, Response::HTTP_OK, $headers);
    }

    /**
     * Generate embed code for the image
     */
    public function embed(string $uniqueId)
    {
        $image = Image::where('unique_id', $uniqueId)
            ->where('is_shareable', true)
            ->firstOrFail();

        $baseUrl = request()->getSchemeAndHttpHost();
        $imageUrl = $baseUrl . route('images.public.serve', [$uniqueId, 'compressed'], false);
        $viewUrl = $baseUrl . route('images.public', $uniqueId, false);

        $embedCodes = [
            'html' => sprintf(
                '<img src="%s" alt="%s" title="%s" />',
                $imageUrl,
                htmlspecialchars($image->alt_text ?? $image->original_name),
                htmlspecialchars($image->description ?? $image->original_name)
            ),
            'markdown' => sprintf(
                '![%s](%s)',
                $image->alt_text ?? $image->original_name,
                $imageUrl
            ),
            'bbcode' => sprintf('[IMG]%s[/IMG]', $imageUrl),
        ];

        return response()->json([
            'image' => $image,
            'urls' => [
                'view' => $viewUrl,
                'original' => $baseUrl . route('images.public.serve', [$uniqueId, 'original'], false),
                'compressed' => $imageUrl,
                'thumbnail' => $baseUrl . route('images.public.serve', [$uniqueId, 'thumbnail'], false),
            ],
            'embed_codes' => $embedCodes,
        ]);
    }

    /**
     * Get image metadata as JSON
     */
    public function metadata(string $uniqueId)
    {
        $image = Image::where('unique_id', $uniqueId)
            ->where('is_shareable', true)
            ->firstOrFail();

        return response()->json([
            'unique_id' => $image->unique_id,
            'name' => $image->original_name,
            'slug' => $image->slug,
            'dimensions' => [
                'width' => $image->width,
                'height' => $image->height,
            ],
            'size' => $image->size,
            'formatted_size' => $image->formatted_size,
            'mime_type' => $image->mime_type,
            'created_at' => $image->created_at,
            'view_count' => $image->view_count,
            'alt_text' => $image->alt_text,
            'description' => $image->description,
            'tags' => $image->tags,
            'has_thumbnail' => $image->hasThumbnail(),
            'has_compressed' => $image->hasCompressed(),
            'compression_ratio' => $image->compressionRatio(),
        ]);
    }
}