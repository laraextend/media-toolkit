<?php

namespace Laraextend\MediaToolkit\Processing;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Laraextend\MediaToolkit\DTOs\ResizeOptions;
use Laraextend\MediaToolkit\Operations\Image\ImageOperationInterface;
use Laraextend\MediaToolkit\Operations\Image\ResizeOperation;

class ImageProcessor
{
    protected const ALLOWED_DRIVERS    = ['auto', 'gd', 'imagick'];
    protected const ALLOWED_FORMATS    = ['webp', 'avif', 'jpg', 'jpeg', 'png'];
    protected const DEFAULT_DRIVER     = 'auto';
    protected const DEFAULT_FORMAT     = 'webp';

    public function __construct(
        protected readonly string       $driverName,
        protected readonly ImageManager $manager,
    ) {}

    /**
     * Factory: resolve the driver, instantiate and return a ready ImageProcessor.
     */
    public static function make(mixed $configuredDriver): self
    {
        $driverName = self::resolveDriverNameStatic($configuredDriver);
        $driver     = $driverName === 'imagick' ? new ImagickDriver : new GdDriver;
        $manager    = new ImageManager($driver);

        return new self($driverName, $manager);
    }

    // ─────────────────────────────────────────────────────────────
    //  CORE PROCESSING
    // ─────────────────────────────────────────────────────────────

    /**
     * Process a single image variant:
     * 1. Load the source
     * 2. Apply the scale-to-target-width step (from the first size operation in $operations)
     * 3. Apply all remaining operations in order (filters, watermark)
     * 4. Encode and return
     *
     * @param  array  $operations  ImageOperationInterface[]
     */
    public function processVariant(
        string $sourcePath,
        int    $targetWidth,
        array  $operations,
        string $format,
        int    $quality,
    ): \Intervention\Image\EncodedImage {
        $image = $this->manager->read($sourcePath);

        $normalizedOperations = [];

        // For multi-variant generation, keep resize(width: X) responsive to the
        // current variant target width instead of forcing every variant to X.
        foreach ($operations as $op) {
            if ($op instanceof ResizeOperation) {
                $opts = $op->options;

                if ($opts->width !== null && $opts->height === null) {
                    $normalizedOperations[] = new ResizeOperation(
                        new ResizeOptions($targetWidth, null, $opts->allowUpscale)
                    );
                    continue;
                }
            }

            $normalizedOperations[] = $op;
        }

        // If there is an explicit size operation (ResizeOperation, StretchOperation, etc.),
        // we let it run at its natural place in the pipeline.
        // If there is NO size operation, we scale to targetWidth proportionally by default
        // (this handles responsive variants where no explicit resize() was chained).
        $hasSizeOp = false;

        foreach ($normalizedOperations as $op) {
            if ($op instanceof ResizeOperation
                || $op instanceof \Laraextend\MediaToolkit\Operations\Image\StretchOperation
                || $op instanceof \Laraextend\MediaToolkit\Operations\Image\FitOperation
                || $op instanceof \Laraextend\MediaToolkit\Operations\Image\CropOperation
            ) {
                $hasSizeOp = true;
                break;
            }
        }

        if (! $hasSizeOp) {
            // Default: scale proportionally to the responsive target width,
            // capped to the original width.
            $cappedWidth = min($targetWidth, $image->width());
            $image       = $image->scale(width: $cappedWidth);
        }

        foreach ($normalizedOperations as $op) {
            $image = $op->apply($image);
        }

        return $image->encode($this->getEncoder($format, $quality));
    }

    // ─────────────────────────────────────────────────────────────
    //  FORMAT SUPPORT
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if the current driver supports a given format.
     */
    public function supportsFormat(string $format): bool
    {
        $format = strtolower($format);

        if ($format === 'avif') {
            if ($this->driverName === 'gd') {
                return function_exists('imageavif');
            }
            if ($this->driverName === 'imagick') {
                if (! class_exists(\Imagick::class)) {
                    return false;
                }

                return in_array('AVIF', \Imagick::queryFormats('AVIF'), true);
            }

            return false;
        }

        if ($format === 'webp') {
            if ($this->driverName === 'gd') {
                return function_exists('imagewebp');
            }

            return true;
        }

        // jpg, png — always supported
        return true;
    }

    /**
     * Return the format unchanged if supported, otherwise fall back through the chain:
     * avif → webp → jpg
     */
    public function safeFormat(string $format): string
    {
        if ($this->supportsFormat($format)) {
            return $format;
        }

        if ($format === 'avif' && $this->supportsFormat('webp')) {
            return 'webp';
        }

        return 'jpg';
    }

    // ─────────────────────────────────────────────────────────────
    //  MEMORY SAFETY (GD)
    // ─────────────────────────────────────────────────────────────

    /**
     * Estimate whether GD would exceed the PHP memory limit during processing.
     * Always returns false for Imagick (assumed safe).
     */
    public function shouldBypassOptimization(string $sourcePath, ?int $targetWidth, ?int $targetHeight): bool
    {
        if ($this->driverName !== 'gd') {
            return false;
        }

        $memoryLimit = $this->memoryLimitInBytes();
        if ($memoryLimit === null) {
            return false;
        }

        $originalDimensions = $this->readImageDimensions($sourcePath);
        if ($originalDimensions === null) {
            return false;
        }

        [$sourceWidth, $sourceHeight] = $originalDimensions;
        $targetWidth  ??= $sourceWidth;
        $targetHeight ??= $sourceHeight;

        if ($targetWidth <= 0 || $targetHeight <= 0) {
            return false;
        }

        $estimatedBytes  = $this->estimateGdProcessingBytes($sourceWidth, $sourceHeight, $targetWidth, $targetHeight);
        $availableBytes  = $memoryLimit - memory_get_usage(true);

        // Keep a 15% headroom to avoid fatal OOM in GD operations.
        return $availableBytes > 0 && $estimatedBytes > (int) floor($availableBytes * 0.85);
    }

    protected function estimateGdProcessingBytes(
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
    ): int {
        // Cap each dimension to prevent integer overflow on adversarially crafted
        // image metadata that reports unrealistically large dimensions.
        $maxDimension = 65535;
        $sourceWidth  = min($sourceWidth,  $maxDimension);
        $sourceHeight = min($sourceHeight, $maxDimension);
        $targetWidth  = min($targetWidth,  $maxDimension);
        $targetHeight = min($targetHeight, $maxDimension);

        $bytesPerPixel = 4;
        $sourceBuffer  = $sourceWidth  * $sourceHeight  * $bytesPerPixel * 2;
        $targetBuffer  = $targetWidth  * $targetHeight  * $bytesPerPixel * 2;
        $overhead      = 32 * 1024 * 1024;

        return $sourceBuffer + $targetBuffer + $overhead;
    }

    protected function memoryLimitInBytes(): ?int
    {
        $limit = ini_get('memory_limit');
        if (! is_string($limit) || $limit === '' || $limit === '-1') {
            return null;
        }

        $limit = trim($limit);
        $unit  = strtolower(substr($limit, -1));
        $value = (float) $limit;

        if ($value <= 0) {
            return null;
        }

        return match ($unit) {
            'g'     => (int) ($value * 1024 * 1024 * 1024),
            'm'     => (int) ($value * 1024 * 1024),
            'k'     => (int) ($value * 1024),
            default => (int) $value,
        };
    }

    // ─────────────────────────────────────────────────────────────
    //  IMAGE METADATA
    // ─────────────────────────────────────────────────────────────

    /**
     * Read image dimensions from the source file without loading it fully into memory.
     * Returns [width, height] or null if unreadable.
     */
    public function readImageDimensions(string $sourcePath): ?array
    {
        $dimensions = @getimagesize($sourcePath);

        if (! is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1])) {
            return null;
        }

        $width  = (int) $dimensions[0];
        $height = (int) $dimensions[1];

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [$width, $height];
    }

    // ─────────────────────────────────────────────────────────────
    //  DRIVER & CONFIG NORMALIZATION
    // ─────────────────────────────────────────────────────────────

    public function getDriverName(): string
    {
        return $this->driverName;
    }

    /**
     * Normalize and validate the output directory path.
     */
    public function normalizeOutputDir(mixed $outputDir): string
    {
        if (! is_string($outputDir)) {
            return 'media/optimized';
        }

        $normalized = trim(str_replace('\\', '/', $outputDir));
        $normalized = preg_replace('#/+#', '/', $normalized) ?? '';
        $normalized = trim($normalized, '/');

        if ($normalized === '' || str_contains($normalized, '..')) {
            return 'media/optimized';
        }

        return $normalized;
    }

    /**
     * Resolve driver name from config, with auto-detection fallback.
     */
    protected static function resolveDriverNameStatic(mixed $configuredDriver): string
    {
        $requested = is_string($configuredDriver)
            ? strtolower(trim($configuredDriver))
            : self::DEFAULT_DRIVER;

        if (! in_array($requested, self::ALLOWED_DRIVERS, true)) {
            $requested = self::DEFAULT_DRIVER;
        }

        $hasImagick = extension_loaded('imagick');
        $hasGd      = extension_loaded('gd');

        return match ($requested) {
            'imagick' => $hasImagick ? 'imagick' : 'gd',
            'gd'      => $hasGd ? 'gd' : ($hasImagick ? 'imagick' : 'gd'),
            default   => $hasImagick ? 'imagick' : 'gd',
        };
    }

    // ─────────────────────────────────────────────────────────────
    //  ENCODER
    // ─────────────────────────────────────────────────────────────

    public function getEncoder(string $format, int $quality): EncoderInterface
    {
        return match ($format) {
            'avif'       => new AvifEncoder(quality: $quality),
            'jpg', 'jpeg' => new JpegEncoder(quality: $quality),
            'png'        => new PngEncoder,
            default      => new WebpEncoder(quality: $quality),
        };
    }
}
