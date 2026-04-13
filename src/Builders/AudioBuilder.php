<?php

namespace Laraextend\MediaToolkit\Builders;

use Illuminate\Support\Facades\File;
use Laraextend\MediaToolkit\Cache\ManifestCache;
use Laraextend\MediaToolkit\Exceptions\MediaBuilderException;
use Laraextend\MediaToolkit\Rendering\AudioHtmlRenderer;

/**
 * Phase-3 builder — serve an audio file and render an <audio> HTML element.
 *
 * No transcoding is performed. The source file is copied unchanged to the
 * public output directory on first access (lazy copy with timestamp check).
 *
 * Usage:
 *   Media::audio('resources/audio/podcast.mp3')
 *       ->controls()
 *       ->preload('none')
 *       ->html(class: 'w-full');
 *
 *   $url = Media::audio('resources/audio/track.ogg')->url();
 */
class AudioBuilder extends BaseBuilder
{
    private const ALLOWED_EXTENSIONS = ['mp3', 'ogg', 'wav', 'aac', 'm4a', 'opus', 'flac'];

    private bool   $showControls = true;
    private bool   $autoplay     = false;
    private bool   $muted        = false;
    private bool   $loop         = false;
    private string $preload      = 'metadata';

    public function __construct(
        protected readonly string            $path,
        protected readonly ManifestCache     $cache,
        protected readonly AudioHtmlRenderer $renderer,
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
     */
    public function autoplay(): static
    {
        $this->autoplay = true;

        return $this;
    }

    /**
     * Mute the audio.
     */
    public function muted(): static
    {
        $this->muted = true;

        return $this;
    }

    /**
     * Loop the audio when it reaches the end.
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

    // ─────────────────────────────────────────────────────────────
    //  OUTPUT METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Copy the audio file to the public output directory and return its public URL path.
     *
     * @throws MediaBuilderException  if the source file is missing or the path is invalid
     */
    public function url(): string
    {
        $this->assertSafeMediaPath($this->path, self::ALLOWED_EXTENSIONS);

        $sourcePath = base_path($this->path);

        if (! File::exists($sourcePath)) {
            throw new MediaBuilderException("Audio file not found: {$this->path}");
        }

        return $this->cache->copyOriginal($sourcePath);
    }

    /**
     * Return an <audio> HTML element for the source file.
     *
     * @param  string       $class       CSS class(es) for the <audio> element
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

        return $this->renderer->buildAudioTag(
            url:        $url,
            class:      $class,
            preload:    $this->preload,
            controls:   $this->showControls,
            autoplay:   $this->autoplay,
            muted:      $this->muted,
            loop:       $this->loop,
            id:         $id,
            attributes: $attributes,
        );
    }
}
