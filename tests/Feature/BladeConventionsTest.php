<?php

use Illuminate\Support\Facades\File;

test('no @js() inside component tag attributes', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = $file->getContents();

        preg_match_all('/<x-[a-z0-9.\-:]+\b(?:[^>"]|"[^"]*")*>/s', $source, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as [$tag, $offset]) {
            if (str_contains($tag, '@js(')) {
                $offenders[] = $file->getRelativePathname().':'.(substr_count(substr($source, 0, $offset), "\n") + 1);
            }
        }
    }

    expect($offenders)->toBe([], 'Use {{ \Illuminate\Support\Js::from(...) }} instead of @js() in: '.implode(', ', $offenders));
});
