<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'images' => [
            'driver' => 'local',
            'root' => storage_path('app/images'),
            'url' => env('APP_URL').'/images/view',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'spaces' => [
            'driver' => 's3',
            'key' => env('DIGITALOCEAN_KEY', null),
            'secret' => env('DIGITALOCEAN_SECRET', null),
            'region' => env('DIGITALOCEAN_REGION', null),
            'bucket' => env('DIGITALOCEAN_BUCKET', null),
            'url' => env('DIGITALOCEAN_URL', null),
            'endpoint' => env('DIGITALOCEAN_ENDPOINT', null),
            'use_path_style_endpoint' => env('DIGITALOCEAN_USE_PATH_STYLE', false),
            'version' => env('DIGITALOCEAN_VERSION', 'latest'),
        ],

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID', null),
            'secret' => env('R2_SECRET_ACCESS_KEY', null),
            'region' => env('R2_REGION', null),
            'bucket' => env('R2_BUCKET', null),
            'url' => env('R2_URL', null),
            'endpoint' => env('R2_ENDPOINT', null),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE', false),
            'version' => env('R2_VERSION', 'latest'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
