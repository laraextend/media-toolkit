<?php

namespace Laraextend\MediaToolkit\Builders;

use Illuminate\Support\Facades\File;
use Laraextend\MediaToolkit\Cache\ManifestCache;
use Laraextend\MediaToolkit\Exceptions\MediaBuilderException;
use Laraextend\MediaToolkit\Rendering\VideoHtmlRenderer;

/**
 * Phase-2 builder — serve a video file and render a <video> HTML element.
 *
 * No transcoding is performed. The source file is copied unchanged to the
 * public output directory on first access (lazy copy with timestamp check).
 *
 * Usage:
 *   Media::video('resources/videos/intro.mp4')
 *       ->controls()
 *       ->muted()
 *       ->width(1280)
 *       ->html(class: 'w-full rounded');
 *
 *   $url = Media::video('resources/videos/clip.webm')->url();
 */
class VideoBuilder extends BaseBuilder
{
    private const ALLOWED_EXTENSIONS = ['mp4', 'webm', 'ogg', 'mov'];

    private bool    $showControls = true;
    private bool    $autoplay     = false;
    private bool    $muted        = false;
    private bool    $loop         = false;
    private bool    $playsinline  = false;
    private string  $preload      = 'metadata';
    private ?string $poster       = null;
    private ?int    $width        = null;
    private ?int    $height       = null;

    /**
     * Explicit <source> elements for multi-format / multi-codec output.
     * Each entry: ['path' => string, 'type' => string|null]
     * When non-empty, the <video> element uses <source> children instead of a src attribute.
     *
     * @var array<int, array{path: string, type: string|null}>
     */
    private array $sources = [];

    public function __construct(
        protected readonly string            $path,
        protected readonly ManifestCache     $cache,
        protected readonly VideoHtmlRenderer $renderer,
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  CHAIN METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Show or hide the native browser controls bar. Default: true.
     */
    public function controls(bool $show = true): static
    {
        $this->showControls = $show;

        return $this;
    }

    /**
     * Start playback automatically.
     * Most browsers require ->muted() alongside autoplay.
     */
    public function autoplay(): static
    {
        $this->autoplay = true;

        return $this;
    }

    /**
     * Mute the audio track.
     */
    public function muted(): static
    {
        $this->muted = true;

        return $this;
    }

    /**
     * Loop the video when it reaches the end.
     */
    public function loop(): static
    {
        $this->loop = true;

        return $this;
    }

    /**
     * Set the preload hint for the browser.
     * Allowed: 'none' | 'metadata' | 'auto'. Defaults to 'metadata'.
     */
    public function preload(string $strategy): static
    {
        $this->preload = in_array($strategy, ['none', 'metadata', 'auto'], true)
            ? $strategy
            : 'metadata';

        return $this;
    }

    /**
     * Set a poster image displayed before playback starts.
     * Accepts a path relative to base_path() — same format as the video source.
     */
    public function poster(string $path): static
    {
        $this->poster = $path;

        return $this;
    }

    /**
     * Set the display width in pixels.
     */
    public function width(int $width): static
    {
        $this->width = $width > 0 ? $width : null;

        return $this;
    }

    /**
     * Set the display height in pixels.
     */
    public function height(int $height): static
    {
        $this->height = $height > 0 ? $height : null;

        return $this;
    }

    /**
     * Prevent the video from going fullscreen on iOS Safari during autoplay.
     * Required for inline autoplay on mobile.
     */
    public function playsinline(): static
    {
        $this->playsinline = true;

        return $this;
    }

    /**
     * Add an explicit <source> element for multi-format / multi-codec output.
     *
     * When at least one source is added the <video> element renders <source>
     * children and omits the src attribute. The browser picks the first entry
     * it can decode, so order matters — put the preferred format first.
     *
     * The $type string accepts full MIME + codec strings, e.g.:
     *   'video/mp4; codecs="hvc1"'   (HEVC / H.265 for Safari 17+)
     *   'video/webm'                  (VP9 / AV1 for Chrome / Firefox)
     *
     * @param  string       $path  Path to the video file, relative to base_path()
     * @param  string|null  $type  Optional MIME type / codec string
     */
    public function source(string $path, ?string $type = null): static
    {
        $this->sources[] = ['path' => $path, 'type' => $type];

        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    //  OUTPUT METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Copy the video to the public output directory and return its public URL path.
     *
     * @throws MediaBuilderException  if the source file is missing or the path is invalid
     */
    public function url(): string
    {
        $this->assertSafeMediaPath($this->path, self::ALLOWED_EXTENSIONS);

        $sourcePath = base_path($this->path);

        if (! File::exists($sourcePath)) {
            throw new MediaBuilderException("Video file not found: {$this->path}");
        }

        return $this->cache->copyOriginal($sourcePath);
    }

    /**
     * Return a <video> HTML element for the source file.
     *
     * @param  string       $class       CSS class(es) for the <video> element
     * @param  string|null  $id          HTML id attribute
     * @param  array        $attributes  Additional HTML attributes as key-value pairs
     *
     * @throws MediaBuilderException  if the source file is missing or the path is invalid
     */
    public function html(
        string  $class      = '',
        ?string $id         = null,
        array   $attributes = [],
    ): string {
        $url = $this->url();

        $posterUrl = null;
        if ($this->poster !== null) {
            $this->assertSafeMediaPath($this->poster, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif']);
            $posterSource = base_path($this->poster);
            if (File::exists($posterSource)) {
                $posterUrl = asset($this->cache->copyOriginal($posterSource));
            }
        }

        // Resolve each source() path to its public URL.
        $resolvedSources = [];
        foreach ($this->sources as $entry) {
            $this->assertSafeMediaPath($entry['path'], self::ALLOWED_EXTENSIONS);
            $sourcePath = base_path($entry['path']);
            if (File::exists($sourcePath)) {
                $resolvedSources[] = [
                    'url'  => asset($this->cache->copyOriginal($sourcePath)),
                    'type' => $entry['type'],
                ];
            }
        }

        return $this->renderer->buildVideoTag(
            url:         $url,
            class:       $class,
            preload:     $this->preload,
            controls:    $this->showControls,
            autoplay:    $this->autoplay,
            muted:       $this->muted,
            loop:        $this->loop,
            playsinline: $this->playsinline,
            posterUrl:   $posterUrl,
            width:       $this->width,
            height:      $this->height,
            id:          $id,
            attributes:  $attributes,
            sources:     $resolvedSources,
        );
    }
}
