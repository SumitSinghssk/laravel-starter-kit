<?php

return [

    'files_root' => storage_path('app/public'),

    'disk' => 'local',
    'directory' => 'backups',

    'step_seconds' => ['web' => 8, 'cli' => 25],

    'rows_per_query' => 1000,

    'part_max_bytes' => 200 * 1024 * 1024,
    'part_max_files' => 2000,

    'structure_only' => ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'],

    'stall_minutes' => 5,

    'defaults' => [
        'enabled' => false,
        'frequency' => 'daily',
        'time' => '02:00',
        'weekday' => 1,
        'monthday' => 1,
        'include_database' => true,
        'include_files' => true,
        'keep' => 7,
    ],

];
