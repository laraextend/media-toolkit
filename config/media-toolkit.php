<?php

return [

    // ─────────────────────────────────────────────────────────────
    //  DRIVER
    //  'auto' detects the best available extension (Imagick › GD).
    //  Explicit values: 'imagick' | 'gd'
    // ─────────────────────────────────────────────────────────────

    'driver' => env('MEDIA_TOOLKIT_DRIVER', 'auto'),

    // ─────────────────────────────────────────────────────────────
    //  OUTPUT DIRECTORY
    //  Relative to public_path(). Processed files are stored here.
    // ─────────────────────────────────────────────────────────────

    'output_dir' => env('MEDIA_TOOLKIT_OUTPUT_DIR', 'media/optimized'),

    // ─────────────────────────────────────────────────────────────
    //  IMAGE
    // ─────────────────────────────────────────────────────────────

    'image' => [

        'quality' => [
            'webp'  => 80,
            'avif'  => 65,
            'jpg'   => 82,
            'jpeg'  => 82,
            'png'   => 85,
        ],

        'responsive' => [
            'size_factors' => [0.5, 0.75, 1.0, 1.5, 2.0],
            'min_width'    => 100,
        ],

        'defaults' => [
            'format'          => 'webp',
            'picture_formats' => ['avif', 'webp'],
            'fallback_format' => 'jpg',
            'loading'         => 'lazy',
            'fetchpriority'   => 'auto',
            'sizes'           => '100vw',
        ],

        // ─────────────────────────────────────────────────────────────
        //  ERROR HANDLING
        //
        //  on_not_found    — source file does not exist on disk
        //  on_error        — file exists but processing/encoding fails
        //  on_memory_limit — GD skips processing because it would exceed PHP memory_limit
        //
        //  Modes for on_not_found / on_error:
        //    'placeholder' → gray SVG <img> with a text label (default)
        //    'broken'      → <img> with the original (non-existing) src,
        //                    so the browser renders its native broken-image icon
        //    'exception'   → throw MediaBuilderException
        //
        //  Additional mode for on_memory_limit:
        //    'original'    → copy & serve the raw source file unchanged
        // ─────────────────────────────────────────────────────────────

        'errors' => [
            'on_not_found'    => env('MEDIA_ON_NOT_FOUND',    'placeholder'),
            'on_error'        => env('MEDIA_ON_ERROR',        'placeholder'),
            'on_memory_limit' => env('MEDIA_ON_MEMORY_LIMIT', 'placeholder'),

            'not_found_text'     => 'Media could not be found.',
            'error_text'         => 'Media could not be displayed!',
            'memory_limit_text'  => 'Media will be displayed shortly.',

            'not_found_color'    => '#f87171',   // red-400
            'error_color'        => '#f87171',   // red-400
            'memory_limit_color' => '#9ca3af',   // gray-400
        ],

        // ─────────────────────────────────────────────────────────────
        //  LOGGING
        //
        //  Errors (not_found, processing errors, memory bypasses) are
        //  written to the Laravel log so you can monitor them in
        //  production without needing to inspect the HTML output.
        //
        //  A machine-readable failure registry is also maintained at
        //  storage/media-toolkit/failures.json for offline retry via
        //  php artisan media:process-pending
        // ─────────────────────────────────────────────────────────────

        'logging' => [
            'enabled' => env('MEDIA_LOGGING_ENABLED', true),

            // null = Laravel's default log channel (LOG_CHANNEL in .env)
            // Set to 'single', 'daily', 'stack', etc. to use a dedicated channel
            'channel' => env('MEDIA_LOG_CHANNEL', null),

            'level' => [
                'not_found'    => 'warning',
                'error'        => 'error',
                'memory_limit' => 'notice',
            ],
        ],

    ],

    // ─────────────────────────────────────────────────────────────
    //  VIDEO  (Phase 2)
    //  No processing — source files are served directly from
    //  public/<output_dir>/originals/ without transcoding.
    // ─────────────────────────────────────────────────────────────

    'video' => [
        // Allowed source extensions for VideoBuilder
        'allowed_extensions' => ['mp4', 'webm', 'ogg', 'mov'],

        // Default <video> element settings
        'defaults' => [
            'controls' => true,
            'preload'  => 'metadata',
        ],
    ],

    // ─────────────────────────────────────────────────────────────
    //  AUDIO  (Phase 3)
    //  No processing — source files are served directly from
    //  public/<output_dir>/originals/ without transcoding.
    // ─────────────────────────────────────────────────────────────

    'audio' => [
        // Allowed source extensions for AudioBuilder
        'allowed_extensions' => ['mp3', 'ogg', 'wav', 'aac', 'm4a', 'opus', 'flac'],

        // Default <audio> element settings
        'defaults' => [
            'controls' => true,
            'preload'  => 'metadata',
        ],
    ],

    // ─────────────────────────────────────────────────────────────
    //  SVG  (Phase 4)
    //  Default mode: served as <img src="...">.
    //  Inline mode (->inline()): SVG content embedded in HTML,
    //  with <script> and on* attributes filtered by default.
    // ─────────────────────────────────────────────────────────────

    'svg' => [
        // Whether to sanitize inline SVG output by default.
        // Can be overridden per call: ->inline(sanitize: false)
        'sanitize_inline' => true,
    ],

];
