<?php

return [

    // Path to the ffmpeg binary. On the server install ffmpeg and either put it on
    // PATH (leave this as 'ffmpeg') or set FFMPEG_PATH to the full path in .env.
    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),

    // Max size (KB) for a source video uploaded to the "Auto Shorts" tool.
    // Also raise php.ini `upload_max_filesize` and `post_max_size` to match.
    'source_max_kb' => (int) env('SHORTS_SOURCE_MAX_KB', 1048576),   // 1 GB

    // Output size for generated shorts (9:16).
    'short_width' => 1080,
    'short_height' => 1920,
];
