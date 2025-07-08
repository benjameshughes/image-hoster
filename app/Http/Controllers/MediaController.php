<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Models\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MediaController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('media.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Media::class);

        return view('media.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaRequest $request)
    {
        $this->authorize('create', Media::class);
        // Implementation handled by Livewire components
    }

    /**
     * Display the specified resource.
     */
    public function show(Media $media)
    {
        $this->authorize('view', $media);

        return view('media.show', compact('media'));
    }

    /**
     * Show detailed view of the specified media.
     */
    public function view(Media $media)
    {
        $this->authorize('view', $media);

        return view('media.view', compact('media'));
    }

    /**
     * Download the specified media file.
     */
    public function download(Media $media)
    {
        $this->authorize('download', $media);

        if (! $media->exists()) {
            abort(404, 'Media file not found');
        }

        return \Storage::disk($media->disk->value)->download(
            $media->path,
            $media->original_name ?? $media->name
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Media $media)
    {
        $this->authorize('update', $media);

        return view('media.edit', compact('media'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMediaRequest $request, Media $media)
    {
        $this->authorize('update', $media);
        // Implementation handled by Livewire components
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Media $media)
    {
        $this->authorize('delete', $media);

        $media->delete();

        return redirect()->route('media.index')
            ->with('success', 'Media deleted successfully.');
    }
}
