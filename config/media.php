<?php

return [

    'roots' => [
        'storage' => storage_path('app/public'),
        'public' => public_path(),
    ],

    'exclude' => [
        'storage' => ['gallery/*/thumbs'],
        'public' => ['build', 'plugins', 'storage', 'vendor'],
    ],

    'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'],

    'raster' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],

    'max_size' => 10 * 1024 * 1024,

    'chunk_size' => 1024 * 1024,

    'max_dimension' => 2560,

    'quality' => 85,

    'keep_versions' => 5,

    'large_bytes' => 500 * 1024,

    'upload_folder' => 'media',

    'rescan_after_minutes' => 10,

    'per_page' => 48,

    'thumb_size' => 360,

];
