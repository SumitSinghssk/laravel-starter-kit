<?php

return [

    'disk' => 'public',

    'chunk_size' => 1024 * 1024,

    'max_size' => [
        'image' => 10 * 1024 * 1024,
        'video' => 10 * 1024 * 1024,
    ],

    'mimes' => [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        'video' => ['video/mp4' => 'mp4', 'video/x-m4v' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
    ],

    'image' => [
        'max_dimension' => 2000,
        'thumb_width' => 480,
        'quality' => 82,
        'max_pixels' => 50_000_000,
    ],

    'poster_width' => 640,

    'stale_upload_hours' => 24,

    'per_page' => 30,

];
