<?php

namespace Laraextend\MediaToolkit\Builders;

use Laraextend\MediaToolkit\Contracts\HasFormatInterface;
use Laraextend\MediaToolkit\Contracts\HasHtmlOutputInterface;
use Laraextend\MediaToolkit\Contracts\MediaBuilderInterface;
use Laraextend\MediaToolkit\Enums\ImageMode;
use Laraextend\MediaToolkit\Exceptions\MediaBuilderException;

abstract class BaseBuilder implements
    MediaBuilderInterface,
    HasFormatInterface,
    HasHtmlOutputInterface
{
    protected const ALLOWED_FORMATS      = ['webp', 'avif', 'jpg', 'jpeg', 'png'];
    protected const ALLOWED_LOADING      = ['lazy', 'eager'];
    protected const ALLOWED_FETCHPRIORITY = ['auto', 'high', 'low'];
    protected const DEFAULT_FORMAT       = 'webp';
    protected const DEFAULT_LOADING      = 'lazy';
    protected const DEFAULT_FETCHPRIORITY = 'auto';
    protected const DEFAULT_SIZES        = '100vw';

    protected ImageMode $mode         = ImageMode::Normal;
    protected bool      $disableCache = false;
    protected ?string   $format       = null;
    protected ?int      $quality      = null;
    protected ?string   $loading      = null;
    protected ?string   $fetchpriority = null;

    // ─────────────────────────────────────────────────────────────
    //  COMMON CHAIN METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Serve the original file without any processing or optimization.
     * Calling this disables all transformation methods.
     */
    public function original(): static
    {
        $this->mode = ImageMode::Original;

        return $this;
    }

    /**
     * Skip the manifest cache — always re-process and overwrite cached files.
     */
    public function noCache(): static
    {
        $this->disableCache = true;

        return $this;
    }

    /**
     * Override the output format.
     * Allowed: 'webp', 'avif', 'jpg', 'jpeg', 'png'
     */
    public function format(string $format): static
    {
        $this->assertTransformationsAllowed('format');
        $this->format = $this->normalizeFormat($format, self::DEFAULT_FORMAT);

        return $this;
    }

    /**
     * Override the output quality (1–100).
     */
    public function quality(int $quality): static
    {
        $this->assertTransformationsAllowed('quality');
        $this->quality = max(1, min(100, $quality));

        return $this;
    }

    /**
     * Override the loading attribute: 'lazy' | 'eager'
     */
    public function loading(string $loading): static
    {
        $this->loading = $this->normalizeLoading($loading);

        return $this;
    }

    /**
     * Override the fetchpriority attribute: 'auto' | 'high' | 'low'
     * Setting 'high' automatically forces loading='eager'.
     */
    public function fetchpriority(string $priority): static
    {
        $this->fetchpriority = $this->normalizeFetchpriority($priority);

        if ($this->fetchpriority === 'high') {
            $this->loading = 'eager';
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    //  ABSTRACT OUTPUT
    // ─────────────────────────────────────────────────────────────

    abstract public function url(): string;

    // ─────────────────────────────────────────────────────────────
    //  INTERNAL GUARDS
    // ─────────────────────────────────────────────────────────────

    /**
     * Throw a MediaBuilderException if original() mode is active.
     */
    protected function assertTransformationsAllowed(string $method): void
    {
        if ($this->mode === ImageMode::Original) {
            throw new MediaBuilderException(
                "Cannot call {$method}() after original() has been set."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  CONFIG RESOLVERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Returns the resolved format (explicit → config → hardcoded default).
     */
    protected function resolveFormat(): string
    {
        return $this->format
            ?? $this->normalizeFormat(
                config('media-toolkit.image.defaults.format', self::DEFAULT_FORMAT),
                self::DEFAULT_FORMAT,
            );
    }

    /**
     * Returns the resolved quality for the given format.
     */
    protected function resolveQuality(string $format): int
    {
        if ($this->quality !== null) {
            return $this->quality;
        }

        $fromConfig = config("media-toolkit.image.quality.{$format}");

        return is_numeric($fromConfig) ? (int) $fromConfig : 80;
    }

    /**
     * Returns the resolved loading attribute, applying fetchpriority='high' → 'eager' rule.
     */
    protected function resolveLoading(): string
    {
        $loading      = $this->loading      ?? $this->normalizeLoading(config('media-toolkit.image.defaults.loading', self::DEFAULT_LOADING));
        $fetchpriority = $this->fetchpriority ?? $this->normalizeFetchpriority(config('media-toolkit.image.defaults.fetchpriority', self::DEFAULT_FETCHPRIORITY));

        if ($fetchpriority === 'high' && $loading === 'lazy') {
            return 'eager';
        }

        return $loading;
    }

    /**
     * Returns the resolved fetchpriority attribute.
     */
    protected function resolveFetchpriority(): string
    {
        return $this->fetchpriority
            ?? $this->normalizeFetchpriority(
                config('media-toolkit.image.defaults.fetchpriority', self::DEFAULT_FETCHPRIORITY)
            );
    }

    /**
     * Returns the resolved sizes attribute.
     */
    protected function resolveSizes(?string $override): string
    {
        return $this->normalizeSizes(
            $override ?? config('media-toolkit.image.defaults.sizes', self::DEFAULT_SIZES)
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  NORMALIZATION HELPERS
    // ─────────────────────────────────────────────────────────────

    protected function normalizeFormat(mixed $format, string $fallback): string
    {
        $fallback = strtolower(trim($fallback));
        if (! in_array($fallback, self::ALLOWED_FORMATS, true)) {
            $fallback = self::DEFAULT_FORMAT;
        }

        if (! is_string($format)) {
            return $fallback;
        }

        $normalized = strtolower(trim($format));

        return in_array($normalized, self::ALLOWED_FORMATS, true) ? $normalized : $fallback;
    }

    protected function normalizeLoading(mixed $loading): string
    {
        if (! is_string($loading)) {
            return self::DEFAULT_LOADING;
        }

        $normalized = strtolower(trim($loading));

        return in_array($normalized, self::ALLOWED_LOADING, true) ? $normalized : self::DEFAULT_LOADING;
    }

    protected function normalizeFetchpriority(mixed $fetchpriority): string
    {
        if (! is_string($fetchpriority)) {
            return self::DEFAULT_FETCHPRIORITY;
        }

        $normalized = strtolower(trim($fetchpriority));

        return in_array($normalized, self::ALLOWED_FETCHPRIORITY, true) ? $normalized : self::DEFAULT_FETCHPRIORITY;
    }

    protected function normalizeSizes(mixed $sizes): string
    {
        if (! is_string($sizes)) {
            return self::DEFAULT_SIZES;
        }

        $normalized = trim($sizes);

        return $normalized !== '' ? $normalized : self::DEFAULT_SIZES;
    }

    protected function normalizeFormatsList(mixed $formats, array $fallback): array
    {
        if (! is_array($formats)) {
            return $fallback;
        }

        $normalized = [];
        foreach ($formats as $format) {
            if (! is_string($format)) {
                continue;
            }
            $f = strtolower(trim($format));
            if (in_array($f, self::ALLOWED_FORMATS, true)) {
                $normalized[] = $f;
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? $fallback : $normalized;
    }

    protected function normalizeDimension(?int $dimension): ?int
    {
        if ($dimension === null) {
            return null;
        }

        return $dimension > 0 ? $dimension : null;
    }

    // ─────────────────────────────────────────────────────────────
    //  SHARED PATH SECURITY GUARDS
    //  Used by ImageBuilder, VideoBuilder, AudioBuilder, SvgBuilder.
    // ─────────────────────────────────────────────────────────────

    /**
     * Reject any path that contains null bytes, CRLF, or directory-traversal sequences.
     * Used as a fast pre-resolution guard on raw path inputs (e.g. watermark URLs).
     *
     * @throws MediaBuilderException
     */
    protected function assertNoTraversal(string $path): void
    {
        if (preg_match('/[\x00\r\n]/', $path) || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $path)) {
            throw new MediaBuilderException('Invalid path: directory traversal is not allowed.');
        }
    }

    /**
     * Validate a media source path: checks control characters, directory traversal,
     * and enforces the provided file-extension whitelist.
     *
     * @param  string[]  $allowedExtensions
     * @throws MediaBuilderException
     */
    protected function assertSafeMediaPath(string $path, array $allowedExtensions): void
    {
        if (preg_match('/[\x00\r\n]/', $path)) {
            throw new MediaBuilderException('Invalid path: control characters are not allowed.');
        }

        if (preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $path)) {
            throw new MediaBuilderException('Invalid path: directory traversal is not allowed.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext !== '' && ! in_array($ext, $allowedExtensions, true)) {
            throw new MediaBuilderException(
                "Invalid file type: '.{$ext}' is not an allowed media format."
            );
        }
    }

    /**
     * Ensure a resolved absolute path is confined within an allowed root directory.
     * Uses realpath() when the file exists; falls back to a string prefix check otherwise.
     *
     * @throws MediaBuilderException
     */
    protected function assertSafeResolvedPath(string $resolved, string $allowedRoot): void
    {
        $real = realpath($resolved);
        $root = realpath($allowedRoot) ?: rtrim($allowedRoot, '/\\');

        if ($real !== false) {
            if (! str_starts_with($real, $root . DIRECTORY_SEPARATOR) && $real !== $root) {
                throw new MediaBuilderException('Invalid path: directory traversal is not allowed.');
            }
        } else {
            $normalised = str_replace('\\', '/', $resolved);
            $rootSlash  = rtrim(str_replace('\\', '/', $root), '/') . '/';

            if (! str_starts_with($normalised, $rootSlash)) {
                throw new MediaBuilderException('Invalid path: directory traversal is not allowed.');
            }
        }
    }
}
