<?php

namespace Laraextend\MediaToolkit\Cache;

use Illuminate\Support\Facades\File;
use Laraextend\MediaToolkit\Processing\ImageProcessor;
use Throwable;

class ManifestCache
{
    public function __construct(
        private readonly string         $publicPath,
        private readonly string         $outputDir,
        private readonly array          $sizeFactors,
        private readonly int            $minWidth,
        private readonly ImageProcessor $processor,
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  VARIANT GENERATION & CACHE LOOKUP
    // ─────────────────────────────────────────────────────────────

    /**
     * Returns cached variants if up to date, otherwise generates and caches them.
     *
     * @param  array  $operations  ImageOperationInterface[] — applied to each variant
     * @return array  Variant metadata array: [['url', 'width', 'height', 'size'], ...]
     */
    public function getOrCreate(
        string $sourcePath,
        int    $sourceModified,
        ?int   $displayWidth,
        string $format,
        bool   $singleOnly,
        array  $operations,
        string $operationsFingerprint,
        int    $quality,
        bool   $noCache = false,
    ): array {
        $hash     = $this->buildCacheHash($sourcePath, $displayWidth, $format, $singleOnly, $operationsFingerprint, $quality);
        $cacheDir = $this->publicPath . '/' . $this->outputDir . '/' . $hash;

        $manifestPath = $cacheDir . '/manifest.json';

        if (! $noCache && File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);

            // Guard against partial reads during a concurrent write (race condition).
            // With atomic writes below this should never be null, but we check defensively.
            if (is_array($manifest) && ($manifest['source_modified'] ?? 0) === $sourceModified) {
                return $manifest['variants'] ?? [];
            }

            if (is_array($manifest)) {
                // Valid manifest but source has changed — delete and regenerate.
                File::deleteDirectory($cacheDir);
            }
            // If $manifest is null the file was likely being written concurrently;
            // fall through to createVariants() which will overwrite it atomically.
        }

        return $this->createVariants(
            $sourcePath,
            $sourceModified,
            $displayWidth,
            $format,
            $cacheDir,
            $singleOnly,
            $operations,
            $operationsFingerprint,
            $quality,
        );
    }

    /**
     * Generate and persist all variants, then write the manifest.json.
     */
    protected function createVariants(
        string $sourcePath,
        int    $sourceModified,
        ?int   $displayWidth,
        string $format,
        string $cacheDir,
        bool   $singleOnly,
        array  $operations,
        string $operationsFingerprint,
        int    $quality,
    ): array {
        File::ensureDirectoryExists($cacheDir, 0755, true);

        [$originalWidth, $originalHeight] = $this->processor->readImageDimensions($sourcePath)
            ?? [$displayWidth ?? 0, 0];

        $baseWidth = $displayWidth ?? $originalWidth;

        $widths = $singleOnly
            ? [min($baseWidth, $originalWidth ?: $baseWidth)]
            : $this->calculateWidths($baseWidth, $originalWidth ?: $baseWidth);

        $aspectRatio = ($originalHeight > 0 && $originalWidth > 0)
            ? $originalHeight / $originalWidth
            : 1.0;

        $variants  = [];
        $baseName  = pathinfo($sourcePath, PATHINFO_FILENAME);

        foreach ($widths as $w) {
            $h        = (int) round($w * $aspectRatio);
            $fileName = "{$baseName}-{$w}w.{$format}";
            $filePath = $cacheDir . '/' . $fileName;
            $urlPath  = '/' . $this->outputDir . '/' . basename($cacheDir) . '/' . $fileName;

            $encoded = $this->processor->processVariant(
                sourcePath:   $sourcePath,
                targetWidth:  $w,
                operations:   $operations,
                format:       $format,
                quality:      $quality,
            );

            $encoded->save($filePath);

            $variants[] = [
                'url'    => $urlPath,
                'width'  => $w,
                'height' => $h,
                'size'   => filesize($filePath),
            ];
        }

        $manifest = [
            'source'                  => $sourcePath,
            'source_modified'         => $sourceModified,
            'format'                  => $format,
            'quality'                 => $quality,
            'display_width'           => $displayWidth,
            'single_only'             => $singleOnly,
            'original_width'          => $originalWidth,
            'original_height'         => $originalHeight,
            'operations_fingerprint'  => $operationsFingerprint,
            'created_at'              => now()->toIso8601String(),
            'variants'                => $variants,
        ];

        // Atomic write: write to a temp file and rename so concurrent readers
        // never see a partially-written manifest (rename() is atomic on POSIX).
        $tmpPath = $cacheDir . '/manifest.tmp.' . getmypid();
        File::put($tmpPath, json_encode($manifest, JSON_PRETTY_PRINT));
        rename($tmpPath, $cacheDir . '/manifest.json');

        return $variants;
    }

    /**
     * Calculate variant widths by applying size_factors to the base width.
     * Skips variants wider than the original or narrower than minWidth.
     */
    protected function calculateWidths(int $baseWidth, int $originalWidth): array
    {
        $widths = [];

        foreach ($this->sizeFactors as $factor) {
            $w = (int) round($baseWidth * $factor);

            if ($w > $originalWidth || $w < $this->minWidth) {
                continue;
            }

            $widths[] = $w;
        }

        // Include original width as additional breakpoint if it is not too large.
        if ($originalWidth <= $baseWidth * 2 && ! in_array($originalWidth, $widths, true)) {
            $widths[] = $originalWidth;
        }

        $widths = array_values(array_unique($widths));
        sort($widths);

        if (empty($widths)) {
            $widths[] = min($baseWidth, $originalWidth);
        }

        return $widths;
    }

    /**
     * Return cached variants if they already exist and are still valid — without generating new ones.
     *
     * Used as a cache-first guard so that already-processed images are always served
     * from cache, even when shouldBypassOptimization() would return true for the current request.
     *
     * Returns null on cache miss (manifest absent, invalid JSON, or source timestamp mismatch).
     */
    public function getCached(
        string $sourcePath,
        int    $sourceModified,
        ?int   $displayWidth,
        string $format,
        bool   $singleOnly,
        string $operationsFingerprint,
        int    $quality,
    ): ?array {
        $hash         = $this->buildCacheHash($sourcePath, $displayWidth, $format, $singleOnly, $operationsFingerprint, $quality);
        $manifestPath = $this->publicPath . '/' . $this->outputDir . '/' . $hash . '/manifest.json';

        if (! File::exists($manifestPath)) {
            return null;
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! is_array($manifest) || ($manifest['source_modified'] ?? 0) !== $sourceModified) {
            return null;
        }

        $variants = $manifest['variants'] ?? [];

        return empty($variants) ? null : $variants;
    }

    // ─────────────────────────────────────────────────────────────
    //  ORIGINAL FILE (no processing)
    // ─────────────────────────────────────────────────────────────

    /**
     * Copy the source file unchanged to public/outputDir/originals/.
     * A timestamp check prevents unnecessary re-copying.
     */
    public function copyOriginal(string $sourcePath): string
    {
        $fileName = basename($sourcePath);
        $hash     = substr(md5($sourcePath), 0, 8);
        $destDir  = $this->publicPath . '/' . $this->outputDir . '/originals';
        $destFile = $destDir . '/' . $hash . '-' . $fileName;
        $urlPath  = '/' . $this->outputDir . '/originals/' . $hash . '-' . $fileName;

        File::ensureDirectoryExists($destDir, 0755, true);

        if (! File::exists($destFile) || File::lastModified($sourcePath) > File::lastModified($destFile)) {
            File::copy($sourcePath, $destFile);
        }

        return $urlPath;
    }

    // ─────────────────────────────────────────────────────────────
    //  CACHE MANAGEMENT
    // ─────────────────────────────────────────────────────────────

    /**
     * Delete all cached variant directories.
     *
     * @param  string|null $type  Reserved for future multi-type support (e.g. 'image').
     * @return int  Number of directories deleted.
     */
    public function clearCache(?string $type = null): int
    {
        $dir = $this->publicPath . '/' . $this->outputDir;

        if (! File::isDirectory($dir)) {
            return 0;
        }

        $dirs  = File::directories($dir);
        $count = count($dirs);

        File::deleteDirectory($dir);

        return $count;
    }

    /**
     * Check all cached manifests and regenerate any whose source file has changed.
     *
     * @param  string|null $type  Reserved for future multi-type support.
     * @return array{regenerated: int, skipped: int, errors: string[]}
     */
    public function warmCache(?string $type = null): array
    {
        $dir     = $this->publicPath . '/' . $this->outputDir;
        $results = ['regenerated' => 0, 'skipped' => 0, 'errors' => []];

        if (! File::isDirectory($dir)) {
            return $results;
        }

        foreach (File::directories($dir) as $cacheDir) {
            $manifestPath = $cacheDir . '/manifest.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest   = json_decode(File::get($manifestPath), true);
            $sourcePath = $manifest['source'] ?? null;

            if (! $sourcePath || ! File::exists($sourcePath)) {
                $results['errors'][] = "Source file not found: {$sourcePath}";
                continue;
            }

            $currentModified = File::lastModified($sourcePath);

            if ($currentModified !== ($manifest['source_modified'] ?? 0)) {
                File::deleteDirectory($cacheDir);

                // Rebuild with an empty operations array — fingerprint stored in manifest.
                // Old manifests without operations_fingerprint default to md5('').
                $fingerprint = $manifest['operations_fingerprint'] ?? md5('');

                try {
                    $this->createVariants(
                        sourcePath:             $sourcePath,
                        sourceModified:         $currentModified,
                        displayWidth:           $manifest['display_width'] ?? null,
                        format:                 $manifest['format'] ?? 'webp',
                        cacheDir:               $cacheDir,
                        singleOnly:             $manifest['single_only'] ?? false,
                        operations:             [],
                        operationsFingerprint:  $fingerprint,
                        quality:                (int) ($manifest['quality'] ?? 80),
                    );
                } catch (Throwable $e) {
                    $results['errors'][] = "Failed to regenerate {$sourcePath}: {$e->getMessage()}";
                    continue;
                }

                $results['regenerated']++;
            } else {
                $results['skipped']++;
            }
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────
    //  INTERNAL HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Build a short, stable cache directory name from all variant parameters.
     */
    protected function buildCacheHash(
        string $sourcePath,
        ?int   $displayWidth,
        string $format,
        bool   $singleOnly,
        string $operationsFingerprint,
        int    $quality,
    ): string {
        $key = implode('|', [
            $sourcePath,
            $displayWidth ?? 'auto',
            $format,
            $quality,
            $singleOnly ? 'single' : 'multi',
            $operationsFingerprint,
        ]);

        return substr(md5($key), 0, 12);
    }
}
