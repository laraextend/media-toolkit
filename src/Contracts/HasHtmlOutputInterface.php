<?php

namespace Laraextend\MediaToolkit\Contracts;

interface HasHtmlOutputInterface
{
    /**
     * Override the loading attribute.
     * Allowed: 'lazy', 'eager'
     */
    public function loading(string $loading): static;

    /**
     * Override the fetchpriority attribute.
     * Allowed: 'auto', 'high', 'low'
     * Setting 'high' automatically forces loading='eager'.
     */
    public function fetchpriority(string $priority): static;

    /**
     * Returns the HTML output for the media element.
     *
     * Each concrete builder defines its own html() signature appropriate
     * for the media type it handles. ImageBuilder uses
     * html(string $alt, string $class, ?string $id, array $attributes).
     * Video/audio builders omit $alt; SvgBuilder keeps it.
     */
}
