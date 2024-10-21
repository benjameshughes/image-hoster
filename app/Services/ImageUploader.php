<?php

namespace App\Services;

use App\Http\Controllers\ImageController;
use Illuminate\Support\Str;

class ImageUploader
{
    private $images = [];
    private $errors = [];
    private $uploadedImages = [];

    private string $formattedFilename;

//    public function upload($file)
//    {
//        $controller = new ImageController();
//        // Now call the store method
//        return $controller->store($file);
//    }

    public function prepareUploadQueue(array $files)
    {
        return array_map(function ($file) {
            return [
                'filename' => $file->getClientOriginalName(),
                'progress' => 0,
                'status' => 'pending',
                'temporary_url' => $file->temporaryUrl(),
            ];
        }, $files);
    }
    public function upload(array $images, callable $callback = null)
    {
        $controller = new ImageController();
        $uploadedImages = [];
        $errors = [];

        if (!is_array($images)) {
            $images = [$images];
        }

        foreach ($images as $index => $image) {
            if ($callback) {
                $callback('uploading', $index, null);
            }

            $result = $controller->store($image, function ($progress) use ($callback, $index) {
                if ($callback) {
                    $callback('progress', $index, $progress);
                }
            });

            if ($result['success']) {
                $uploadedImages[] = $result['upload'];
                if ($callback) {
                    $callback('success', $index, $result['upload']);
                }
            } else {
                $errors[] = $result['message'];
                if ($callback) {
                    $callback('error', $index, $result['message']);
                }
            }
        }

        if ($callback) {
            $callback('complete', null, ['uploaded' => $uploadedImages, 'errors' => $errors]);
        }

        return [
            'uploadedImages' => $uploadedImages,
            'errors' => $errors
        ];
    }

    /**
     * Get the disk instance and allow the user to set a property to change the default disk.
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function disk(){
        return $this->disk;
    }

    public function dispatch()
    {
        foreach ($this->uploadedImages as $upload) {
            $this->dispatch('imageUploaded', $upload);
        }

        return $this;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function isSuccessful()
    {
        return count($this->errors) === 0;
    }

    /**
     * Get the images from the input
     * @param $filename
     * @return $this
     */
    public function getImagesFromInput($file)
    {
        $this->uploadedImages = array_push($this->uploadedImages, $file);
    }

    /*
     * Make the file name safe
     */
    public function formatFilename($filename): self
    {
        $removeExtension = pathinfo($filename, PATHINFO_FILENAME);
        $sanitizeName = preg_replace('/[^A-Za-z0-9\s\-]/', '', $removeExtension);
        $normalizedSpaces = preg_replace('/\s+/', ' ', $sanitizeName);
        $this->formattedFilename = Str::title(trim($normalizedSpaces));
        return $this;
    }

    public function getFormattedFilename(): string
    {
        return $this->formattedFilename;
    }

    public function __toString()
    {
        return $this->formattedFilename;
    }
}