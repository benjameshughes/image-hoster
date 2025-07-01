<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageRequest;
use App\Models\Image;

class ImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('images.index');
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
    public function store(StoreImageRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        //        $images = collect(Image::all());
        //        return view('image.show', compact('images'));
    }

    /**
     * Show detailed view of the specified image.
     */
    public function view(Image $image)
    {
        // Ensure user can only view their own images
        if ($image->user_id !== auth()->id()) {
            abort(403);
        }

        return view('images.view', compact('image'));
    }

    /**
     * Download the specified image.
     */
    public function download(Image $image)
    {
        // Ensure user can only download their own images
        if ($image->user_id !== auth()->id()) {
            abort(403);
        }

        if (!$image->exists()) {
            abort(404, 'Image file not found');
        }

        return \Storage::disk($image->disk->value)->download(
            $image->path,
            $image->original_name ?? $image->name
        );
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
    public function update(UpdateImageRequest $request, Image $image)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Image $image)
    {
        //
    }
}
