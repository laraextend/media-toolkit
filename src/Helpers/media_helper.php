<?php

use Laraextend\MediaToolkit\Facades\Media;

// ─────────────────────────────────────────────────────────────
//  img() - Single optimized image, WITHOUT srcset
// ─────────────────────────────────────────────────────────────

if (! function_exists('img')) {
    /**
     * @deprecated  Use Media::image($src)->resize(width: $width)->html(alt: $alt) instead.
     *
     * Optimized single image (resize + compressed, no srcset).
     *
     *   {!! img(
     *       src: 'resources/views/pages/home/logo.jpg',
     *       alt: 'Logo',
     *       width: 200,
     *       format: 'webp',
     *   ) !!}
     *
     *   {!! img(
     *       src: 'resources/views/pages/home/logo.png',
     *       alt: 'Logo',
     *       original: true,   // ← Original file, no processing
     *   ) !!}
     */
    function img(
        string  $src,
        string  $alt           = '',
        ?int    $width         = null,
        ?int    $height        = null,
        string  $class         = '',
        ?string $format        = null,
        ?string $loading       = null,
        ?string $fetchpriority = null,
        ?string $id            = null,
        bool    $original      = false,
        array   $attributes    = [],
    ): string {
        $builder = Media::image($src);

        if ($original) {
            $builder->original();
        } else {
            if ($width !== null || $height !== null) {
                $builder->resize(width: $width, height: $height);
            }
            if ($format !== null) {
                $builder->format($format);
            }
        }

        if ($loading !== null)      { $builder->loading($loading); }
        if ($fetchpriority !== null) { $builder->fetchpriority($fetchpriority); }

        return $builder->html(
            alt:        $alt,
            class:      $class,
            id:         $id,
            attributes: $attributes,
        );
    }
}

// ─────────────────────────────────────────────────────────────
//  responsive_img() - <img> with srcset (responsive)
// ─────────────────────────────────────────────────────────────

if (! function_exists('responsive_img')) {
    /**
     * @deprecated  Use Media::image($src)->resize(width: $width)->responsive($sizes)->html(alt: $alt) instead.
     *
     * Responsive <img> with srcset + sizes.
     *
     *   {!! responsive_img(
     *       src: 'resources/views/pages/home/hero.jpg',
     *       alt: 'Hero',
     *       width: 800,
     *       format: 'webp',
     *       fetchpriority: 'high',
     *       sizes: '(max-width: 768px) 100vw, 800px',
     *   ) !!}
     */
    function responsive_img(
        string  $src,
        string  $alt           = '',
        ?int    $width         = null,
        ?int    $height        = null,
        string  $class         = '',
        ?string $format        = null,
        ?string $loading       = null,
        ?string $fetchpriority = null,
        ?string $sizes         = null,
        ?string $id            = null,
        bool    $original      = false,
        array   $attributes    = [],
    ): string {
        $builder = Media::image($src);

        if ($original) {
            $builder->original();
        } else {
            if ($width !== null || $height !== null) {
                $builder->resize(width: $width, height: $height);
            }
            if ($format !== null) {
                $builder->format($format);
            }
        }

        if ($loading !== null)      { $builder->loading($loading); }
        if ($fetchpriority !== null) { $builder->fetchpriority($fetchpriority); }

        $builder->responsive($sizes);

        return $builder->html(
            alt:        $alt,
            class:      $class,
            id:         $id,
            attributes: $attributes,
        );
    }
}

// ─────────────────────────────────────────────────────────────
//  picture() - <picture> with multiple formats
// ─────────────────────────────────────────────────────────────

if (! function_exists('picture')) {
    /**
     * @deprecated  Use Media::image($src)->resize(width: $width)->picture()->html(alt: $alt) instead.
     *
     * <picture> with <source> per format + fallback <img>.
     *
     *   {!! picture(
     *       src: 'resources/views/pages/home/hero.jpg',
     *       alt: 'Hero',
     *       width: 800,
     *       formats: ['avif', 'webp'],
     *       fallbackFormat: 'jpg',
     *       fetchpriority: 'high',
     *       sizes: '(max-width: 768px) 100vw, 800px',
     *   ) !!}
     */
    function picture(
        string  $src,
        string  $alt            = '',
        ?int    $width          = null,
        ?int    $height         = null,
        string  $class          = '',
        string  $imgClass       = '',
        string  $sourceClass    = '',
        ?array  $formats        = null,
        ?string $fallbackFormat = null,
        ?string $loading        = null,
        ?string $fetchpriority  = null,
        ?string $sizes          = null,
        ?string $id             = null,
        bool    $original       = false,
        array   $attributes     = [],
    ): string {
        $builder = Media::image($src);

        if ($original) {
            $builder->original();
        } else {
            if ($width !== null || $height !== null) {
                $builder->resize(width: $width, height: $height);
            }

            $builder->picture(
                formats:     $formats,
                fallback:    $fallbackFormat,
                imgClass:    $imgClass,
                sourceClass: $sourceClass,
            );
        }

        if ($loading !== null)      { $builder->loading($loading); }
        if ($fetchpriority !== null) { $builder->fetchpriority($fetchpriority); }

        return $builder->html(
            alt:        $alt,
            class:      $class,
            id:         $id,
            attributes: $attributes,
        );
    }
}

// ─────────────────────────────────────────────────────────────
//  img_url() - Return only the URL
// ─────────────────────────────────────────────────────────────

if (! function_exists('img_url')) {
    /**
     * @deprecated  Use Media::image($src)->resize(width: $width)->url() instead.
     *
     * Returns only the URL of the optimized image.
     *
     *   <div style="background-image: url('{{ img_url(src: '...', width: 800) }}')">
     *   <meta property="og:image" content="{{ img_url(src: '...', width: 1200, format: 'jpg') }}">
     *   {{ img_url(src: '...', original: true) }}  ← Original file URL
     */
    function img_url(
        string  $src,
        ?int    $width    = null,
        ?string $format   = null,
        bool    $original = false,
        ?int    $quality  = null,
    ): string {
        $builder = Media::image($src);

        if ($original) {
            $builder->original();
        } else {
            if ($width !== null) {
                $builder->resize(width: $width);
            }
            if ($format !== null) {
                $builder->format($format);
            }
            if ($quality !== null) {
                $builder->quality($quality);
            }
        }

        return $builder->url();
    }
}
