<p align="center">
  <a href="https://packagist.org/packages/laraextend/media-toolkit"><img src="https://img.shields.io/packagist/v/laraextend/media-toolkit.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/laraextend/media-toolkit"><img src="https://img.shields.io/packagist/dt/laraextend/media-toolkit.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/laraextend/media-toolkit"><img src="https://img.shields.io/packagist/php-v/laraextend/media-toolkit.svg?style=flat-square" alt="PHP Version"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square" alt="License"></a>
</p>

# Laravel Media Toolkit

**A comprehensive Laravel media toolkit for automatic image optimization, transformations, responsive variants, next-gen formats and more — ready to use directly in Blade.**

`laraextend/media-toolkit` handles the heavy lifting for you: images are resized, cropped, filtered, compressed, converted to modern formats (WebP, AVIF) and rendered as responsive `<img>` or `<picture>` tags via a clean fluent API.

> **Roadmap:** Future releases will extend the toolkit to cover animated images (GIF/APNG/WebP animated) and video transcoding — all behind the same `Media::` facade.

---

## ✨ Features

- **🔗 Fluent Builder API** — `Media::image($path)->resize(800)->grayscale()->html(alt: 'Hero')`
- **🎬 Video Support** — `Media::video($path)->controls()->muted()->html(class: 'w-full')`
- **🎵 Audio Support** — `Media::audio($path)->controls()->preload('none')->html()`
- **🖼️ SVG Support** — `Media::svg($path)->html(alt: 'Icon')` or `->inline()->html(class: 'fill-current')`
- **🧩 Four Blade Components** — `<x-media::img>`, `<x-media::responsive-img>`, `<x-media::picture>`, `<x-media::img-url>`
- **🖼️ Four Blade Helpers** — `img()`, `responsive_img()`, `picture()` and `img_url()` (deprecated, still available)
- **📐 Image Transformations** — resize, fit, stretch, crop with automatic proportional scaling
- **🎨 Image Filters** — grayscale, sepia, negate, brightness, contrast, colorize, blur, smooth, rotate, flip, watermark
- **📱 Automatic Responsive Variants** — Generates 5 breakpoint sizes (0.5×, 0.75×, 1×, 1.5×, 2×) with `srcset`
- **🌐 Next-Gen Formats** — WebP, AVIF, JPEG, PNG with automatic fallback
- **⚡ Smart Caching** — Manifest-based cache with automatic invalidation and filter-aware cache keys
- **🔧 Artisan Commands** — `media:cache-clear` and `media:cache-warm` with optional `--type=` flag
- **⚙️ Configurable** — Publish `config/media-toolkit.php` to customize quality, formats, breakpoints and more
- **🏎️ Performance-Optimized** — Lazy loading, `fetchpriority`, `decoding="async"` by default
- **🛡️ Memory-Safe Fallback** — Automatically serves original images when GD memory would be exceeded
- **📦 Zero Config** — Works immediately after installation
- **🔄 GD & Imagick** — Automatic driver detection
- **⚡ Livewire & Alpine.js Ready** — Blade components forward `wire:*`, `x-*` and `data-*` attributes automatically

---

## 📋 Requirements

- **PHP** >= 8.2
- **Laravel** >= 10.x
- **Intervention Image** >= 3.0 (`intervention/image`)
- **GD** or **Imagick** PHP extension
- Optional: AVIF support in GD (`imageavif`) or Imagick

---

## 🚀 Installation

```bash
composer require laraextend/media-toolkit
```

> The ServiceProvider and `Media` Facade alias are registered automatically via Laravel's Auto-Discovery.

### Optional: Publish Configuration

```bash
php artisan vendor:publish --tag=media-toolkit-config
```

---

## 🔗 Fluent API — `Media::image()`

The primary API is a fluent builder accessed through the `Media` facade.

```php
use Laraextend\MediaToolkit\Facades\Media;

// Simple optimized URL
$url = Media::image('resources/images/hero.jpg')
    ->resize(width: 800)
    ->format('webp')
    ->url();

// Full responsive <picture> with filters
echo Media::image('resources/images/hero.jpg')
    ->resize(width: 1200)
    ->grayscale()
    ->picture(formats: ['avif', 'webp'], fallback: 'jpg')
    ->fetchpriority('high')
    ->html(alt: 'Hero', class: 'w-full');
```

---

## 📐 Transformations

### `resize(?int $width, ?int $height)` — Proportional Resize

Scale the image while preserving aspect ratio. Provide width, height, or both (contain-box).

```php
Media::image('photo.jpg')->resize(width: 800)              // → 800px wide, height proportional
Media::image('photo.jpg')->resize(height: 600)             // → 600px tall, width proportional
Media::image('photo.jpg')->resize(width: 800, height: 600) // → fit inside 800×600, no crop
```

Chain `->upscale()` to allow resizing beyond the original dimensions (capped by default):

```php
Media::image('small.jpg')->resize(width: 1200)->upscale()->url();
```

### `fit(int $width, int $height)` — Cover + Crop

Scale so the image fills the frame completely, cropping the overflow from center.

```php
Media::image('photo.jpg')->fit(400, 400)->url();  // Always exactly 400×400
```

### `stretch(int $width, int $height)` — Exact Dimensions, No Aspect Ratio

Resize to exact dimensions, ignoring aspect ratio.

```php
Media::image('photo.jpg')->stretch(200, 200)->url();
```

### `crop(int $width, int $height, int|string $x = 0, int|string $y = 0)` — Region Extract

Extract a region from the original image without scaling. String offsets: `'left'`, `'center'`, `'right'`, `'top'`, `'bottom'`.

```php
Media::image('photo.jpg')->crop(400, 200, 'center', 'center')->url();
Media::image('photo.jpg')->crop(400, 200, 100, 50)->url();  // pixel offsets
```

> **Note:** Only one size/crop operation can be used per chain. Combining `resize()` + `fit()` etc. throws a `MediaBuilderException`.

### `original()` — No Processing

Serve the original file without any transformation or optimization.

```php
Media::image('resources/images/photo.jpg')->original()->url();
```

> `original()` locks the chain — calling `resize()`, `format()`, `quality()`, filters or `watermark()` afterwards throws a `MediaBuilderException`.

---

## 🎨 Filters

Filters are stackable and can be combined in any order.

```php
Media::image('photo.jpg')
    ->resize(width: 800)
    ->grayscale()
    ->blur(3)
    ->brightness(20)
    ->html(alt: 'Photo');
```

| Method | Description |
|--------|-------------|
| `->grayscale()` | Convert to black & white |
| `->sepia()` | Apply a warm sepia tone |
| `->negate()` | Invert all colors |
| `->brightness(int $level)` | Adjust brightness: −255 (darkest) to +255 (brightest) |
| `->contrast(int $level)` | Adjust contrast: −100 to +100 |
| `->colorize(int $r, int $g, int $b)` | Tint with RGB offset: −255 to +255 per channel |
| `->blur(int $amount = 1)` | Apply blur (amount = number of passes) |
| `->smooth(int $level)` | Smooth/sharpen: −10 (max sharpen) to +10 (max smooth) |
| `->rotate(int\|string $angle)` | Rotate degrees CCW, or `'auto'` for EXIF-based rotation |
| `->flipHorizontal()` | Mirror left-right |
| `->flipVertical()` | Mirror top-bottom |
| `->flipBoth()` | Mirror both axes |

### `watermark(string $source, string $position, int $padding, int $opacity)` — Overlay

```php
Media::image('photo.jpg')
    ->resize(width: 1200)
    ->watermark(
        source:   'resources/images/watermark.png',
        position: 'bottom-right',
        padding:  20,
        opacity:  80,
    )
    ->html(alt: 'Photo');
```

Position values: `'top-left'` `'top-center'` `'top-right'` `'center-left'` `'center'` `'center-right'` `'bottom-left'` `'bottom-center'` `'bottom-right'`

**The `source` parameter accepts multiple formats:**

```php
// Relative path (resolved via base_path())
->watermark('resources/images/logo.png', 'bottom-right')

// Web path — as returned by ->url() (resolved via public_path())
$logoUrl = Media::image('resources/images/logo.png')->resize(width: 200)->url();
->watermark($logoUrl, 'bottom-right', 10, 80)

// Absolute filesystem path
->watermark('/var/www/html/storage/watermarks/logo.png', 'center')
```

---

## 🖼️ Output Methods

### `->url()` — URL String

```php
$url = Media::image('resources/images/og.jpg')->resize(width: 1200)->format('jpg')->url();
```

### `->html(string $alt, string $class, ?string $id, array $attributes)` — HTML Tag

Output depends on the active output mode set via `->responsive()` or `->picture()`:

```php
// Simple <img>
echo Media::image('logo.png')->resize(width: 200)->html(alt: 'Logo', class: 'h-8');

// <img> with srcset
echo Media::image('hero.jpg')->resize(width: 800)->responsive('(max-width: 768px) 100vw, 800px')->html(alt: 'Hero');

// <picture> with <source> elements
echo Media::image('hero.jpg')->resize(width: 800)->picture()->html(alt: 'Hero');
```

### `->responsive(?string $sizes)` — Switch to srcset Mode

```php
Media::image('hero.jpg')
    ->resize(width: 800)
    ->responsive('(max-width: 768px) 100vw, 800px')
    ->fetchpriority('high')
    ->html(alt: 'Hero Banner', class: 'w-full');
```

### `->picture(?array $formats, ?string $fallback, string $imgClass, string $sourceClass)` — Switch to `<picture>` Mode

```php
Media::image('hero.jpg')
    ->resize(width: 1200)
    ->picture(formats: ['avif', 'webp'], fallback: 'jpg', imgClass: 'w-full')
    ->fetchpriority('high')
    ->html(alt: 'Hero', class: 'hero-picture');
```

---

## ⚙️ Output Modifiers

These can be chained anywhere before `->url()` or `->html()`:

| Method | Description |
|--------|-------------|
| `->format(string $format)` | Output format: `webp`, `avif`, `jpg`, `jpeg`, `png` |
| `->quality(int $quality)` | Quality 1–100 (overrides config) |
| `->loading(string $loading)` | `'lazy'` or `'eager'` |
| `->fetchpriority(string $priority)` | `'auto'`, `'high'`, `'low'` (high → forces eager) |
| `->noCache()` | Skip the manifest cache, always regenerate |

---

## 🧩 Blade Components

All components use the `media` namespace and map to the `Media::image()` builder.

> Attributes placed directly on the component tag (`wire:key`, `data-*`, `x-*`) are forwarded automatically. Use `:extra-attributes="[...]"` for programmatic attribute arrays.

### `<x-media::img>` — Single Optimized Image

```blade
<x-media::img
    src="resources/images/logo.png"
    alt="Company Logo"
    :width="200"
    format="webp"
    loading="eager"
/>
```

**Props:** `src`, `alt`, `width`, `height`, `class`, `format`, `quality`, `loading`, `fetchpriority`, `id`, `original`, `extra-attributes`

---

### `<x-media::responsive-img>` — Responsive with srcset

```blade
<x-media::responsive-img
    src="resources/images/hero.jpg"
    alt="Hero Banner"
    :width="800"
    fetchpriority="high"
    sizes="(max-width: 768px) 100vw, 800px"
/>
```

**Additional props:** `sizes`, `quality`

---

### `<x-media::picture>` — Multi-Format with Fallback

```blade
<x-media::picture
    src="resources/images/hero.jpg"
    alt="Hero Banner"
    :width="800"
    :formats="['avif', 'webp']"
    fallback-format="jpg"
    fetchpriority="high"
    sizes="(max-width: 768px) 100vw, 800px"
    class="hero-picture"
    img-class="hero-img"
/>
```

**Additional props:** `formats`, `fallback-format`, `img-class`, `source-class`, `quality`

> The Blade attribute bag (`wire:key`, `x-*`, `@*` etc.) is applied to the outer `<picture>` element. Use `extra-attributes` for the inner `<img>`.

---

### `<x-media::img-url>` — URL Only

```blade
<div style="background-image: url('<x-media::img-url src="resources/images/bg.jpg" :width="1920" />')">
```

**Props:** `src`, `width`, `format`, `quality`, `original`

---

## 🖼️ Legacy Blade Helpers

The four global helper functions are still available but marked `@deprecated`. They are now thin wrappers around `Media::image()`.

```blade
{{-- Still works: --}}
{!! img(src: 'resources/images/logo.jpg', alt: 'Logo', width: 200, format: 'webp') !!}
{!! responsive_img(src: 'resources/images/hero.jpg', alt: 'Hero', width: 800) !!}
{!! picture(src: 'resources/images/hero.jpg', alt: 'Hero', width: 800) !!}
{{ img_url(src: 'resources/images/og.jpg', width: 1200, format: 'jpg') }}

{{-- Preferred (v2): --}}
{!! Media::image('resources/images/hero.jpg')->resize(width: 800)->html(alt: 'Hero') !!}
```

---

## ⚙️ Configuration

```bash
php artisan vendor:publish --tag=media-toolkit-config
```

**`config/media-toolkit.php`:**

```php
return [
    // Image processing driver: 'auto' (recommended), 'gd', or 'imagick'
    'driver'     => env('MEDIA_TOOLKIT_DRIVER', 'auto'),

    // Output directory relative to public/
    'output_dir' => env('MEDIA_TOOLKIT_OUTPUT_DIR', 'media/optimized'),

    'image' => [

        // Image quality per format (1–100)
        'quality' => [
            'webp'  => 80,
            'avif'  => 65,
            'jpg'   => 82,
            'jpeg'  => 82,
            'png'   => 85,
        ],

        // Responsive breakpoints
        'responsive' => [
            'size_factors' => [0.5, 0.75, 1.0, 1.5, 2.0], // multipliers of the requested width
            'min_width'    => 100,                           // skip variants narrower than this
        ],

        // Default HTML attribute values and format choices
        'defaults' => [
            'format'          => 'webp',
            'picture_formats' => ['avif', 'webp'],
            'fallback_format' => 'jpg',
            'loading'         => 'lazy',
            'fetchpriority'   => 'auto',
            'sizes'           => '100vw',
        ],

    ],
];
```

Example `.env` overrides:

```dotenv
MEDIA_TOOLKIT_DRIVER=imagick
MEDIA_TOOLKIT_OUTPUT_DIR=media/optimized
```

---

## 📐 Responsive Variants — How It Works

When you specify `width: 800`, the following variants are generated:

| Factor | Calculation | Result |
|--------|-------------|--------|
| 0.5× | 800 × 0.5 | **400w** |
| 0.75× | 800 × 0.75 | **600w** |
| 1.0× | 800 × 1.0 | **800w** |
| 1.5× | 800 × 1.5 | **1200w** |
| 2.0× | 800 × 2.0 | **1600w** |

**Automatic constraints:**
- Variants smaller than `min_width` (default 100px) are skipped
- Variants wider than the original image are skipped (no artificial upscaling)
- If the original width is ≤ 2× the target, the original width is added as an additional variant
- Duplicates are automatically removed

---

## ⚡ Performance

### Loading Behavior

```blade
{{-- Default: Lazy Loading (below the fold) --}}
{!! Media::image('photo.jpg')->resize(width: 600)->html(alt: 'Photo') !!}
{{-- → loading="lazy" decoding="async" fetchpriority="auto" --}}

{{-- Above the Fold: High Priority --}}
{!! Media::image('hero.jpg')->resize(width: 1200)->fetchpriority('high')->html(alt: 'Hero') !!}
{{-- → loading="eager" decoding="async" fetchpriority="high" --}}
```

> Setting `fetchpriority('high')` automatically forces `loading="eager"`, even if `lazy` was set explicitly.

---

## 🔧 Artisan Commands

### Clear Cache

Deletes all optimized media variants from `public/<output_dir>/`:

```bash
php artisan media:cache-clear
```

```
✓ 42 cache entries deleted.
```

### Warm Cache

Regenerates any variants whose source file has changed since they were last generated:

```bash
php artisan media:cache-warm
```

```
Checking cache for outdated media variants...
✓ 3 regenerated, 39 up to date.
⚠ Source file not found: resources/images/deleted-image.jpg
```

Both commands accept `--type=` for future multi-type support (image, video, audio).

### In Your Deployment Pipeline

```bash
php artisan media:cache-clear   # Optional: rebuild everything from scratch
php artisan media:cache-warm    # Recommended: only regenerate what changed
```

---

## 💾 Caching — How It Works

Each unique combination of source file, dimensions, format, operations and filters gets its own **cache directory** in `public/<output_dir>/` (default: `public/media/optimized/`):

```
public/media/optimized/
├── a1b2c3d4e5f6/          ← Hash of source + options + filter fingerprint
│   ├── manifest.json       ← Metadata + modification timestamp
│   ├── hero-400w.webp
│   ├── hero-600w.webp
│   └── hero-800w.webp
├── f6e5d4c3b2a1/          ← Same image, different filter chain = different cache
│   ├── manifest.json
│   ├── hero-400w.webp     ← With grayscale applied
│   └── hero-800w.webp
└── originals/              ← Unmodified originals (when original: true)
    └── a1b2c3d4-photo.jpg
```

The `manifest.json` stores the **last-modified timestamp** of the source file. On every request:

1. Does the cache directory with manifest exist? → **Yes**: Check timestamp
2. Has the source changed? → **No**: Serve from cache ✓
3. Has the source changed? → **Yes**: Delete old cache, regenerate variants

**You never need to clear the cache manually when replacing images** — changes are detected automatically.

### Concurrent Request Safety

Manifest files are written **atomically** using a temp-file-then-rename pattern:

1. Image variants are processed and saved to disk
2. The manifest is written to a temporary file (`manifest.tmp.<pid>`)
3. The temp file is **renamed** to `manifest.json` — an atomic operation on POSIX systems (Linux, macOS)

This guarantees that concurrent readers never encounter partially-written JSON, even when multiple requests try to generate the same image simultaneously.

### Memory Check Order — Cache Always Wins

The memory-bypass check (`on_memory_limit`) happens **after** the cache check, not before:

1. **Cache exists?** → Serve immediately — no processing, no memory check needed
2. **Cache miss** → Check if GD can process the image within the PHP memory limit
3. **Memory too low** → Show placeholder / fallback per `on_memory_limit` setting
4. **Memory ok** → Process, cache, and serve

**Result:** A cached image is **always served from disk** regardless of current PHP memory availability. The `on_memory_limit` fallback only activates when generating a variant for the first time.

---

## 🛠️ Practical Examples

### Hero Banner (Above the Fold)

```blade
{{-- Fluent API --}}
{!! Media::image('resources/images/hero.jpg')
    ->resize(width: 1200)
    ->picture(formats: ['avif', 'webp'], fallback: 'jpg')
    ->fetchpriority('high')
    ->html(alt: 'Welcome', class: 'w-full', attributes: ['id' => 'hero']) !!}

{{-- Blade Component --}}
<x-media::picture
    src="resources/images/hero.jpg"
    alt="Welcome"
    :width="1200"
    :formats="['avif', 'webp']"
    fallback-format="jpg"
    fetchpriority="high"
    class="w-full"
    img-class="w-full h-auto object-cover"
/>
```

### Logo (Fixed Size, Eager)

```blade
<x-media::img
    src="resources/images/logo.png"
    alt="Company Logo"
    :width="180"
    loading="eager"
/>
```

### Product Gallery with Lightbox

```blade
@foreach ($images as $image)
    {!! Media::image($image->path)
        ->resize(width: 600)
        ->responsive('(max-width: 768px) 100vw, 33vw')
        ->html(
            alt:        $image->caption,
            class:      'gallery-thumb cursor-pointer',
            attributes: [
                'data-lightbox' => 'gallery',
                'data-full'     => Media::image($image->path)->resize(width: 1800)->format('jpg')->url(),
            ],
        ) !!}
@endforeach
```

### Open Graph Meta Tags

```blade
<meta property="og:image" content="{{ url(Media::image('resources/images/og.jpg')->resize(width: 1200)->format('jpg')->url()) }}">
```

### CSS Background

```blade
<section style="background-image: url('{{ Media::image('resources/images/bg.jpg')->resize(width: 1920)->url() }}')">
    <h1>Welcome</h1>
</section>
```

### Grayscale Thumbnail Grid

```blade
@foreach ($products as $product)
    {!! Media::image($product->image)
        ->fit(300, 300)
        ->grayscale()
        ->html(alt: $product->name, class: 'product-thumb') !!}
@endforeach
```

### Livewire Repeater

```blade
{{-- wire:key on <img> --}}
@foreach ($items as $item)
    <x-media::img
        :src="$item->image"
        :alt="$item->name"
        :width="300"
        wire:key="item-{{ $item->id }}"
        class="rounded"
    />
@endforeach

{{-- wire:key on <picture> (outermost element) --}}
@foreach ($items as $item)
    <x-media::picture
        :src="$item->image"
        :alt="$item->name"
        :width="300"
        wire:key="item-{{ $item->id }}"
    />
@endforeach
```

---

## ⚠️ Error Handling

Error handling behaviour is configurable per error type in `config/media-toolkit.php`:

```php
'image' => [
    'errors' => [
        'on_not_found'    => env('MEDIA_ON_NOT_FOUND',    'placeholder'),  // file does not exist
        'on_error'        => env('MEDIA_ON_ERROR',        'placeholder'),  // processing failed
        'on_memory_limit' => env('MEDIA_ON_MEMORY_LIMIT', 'placeholder'),  // GD memory bypass

        // Placeholder label text per error type
        'not_found_text'     => 'Media could not be found.',
        'error_text'         => 'Media could not be displayed!',
        'memory_limit_text'  => 'Media will be displayed shortly.',

        // Placeholder background colour per error type
        'not_found_color'    => '#f87171',   // red-400
        'error_color'        => '#f87171',   // red-400
        'memory_limit_color' => '#9ca3af',   // gray-400
    ],
],
```

### Modes

| Mode | `html()` returns | `url()` returns |
|------|-----------------|-----------------|
| `'placeholder'` | Inline SVG `<img>` with label text | `''` (empty string) |
| `'broken'` | `<img src="original-path">` — browser shows broken-image icon | `''` (empty string) |
| `'exception'` | Throws `MediaBuilderException` | Throws `MediaBuilderException` |
| `'original'` *(memory-limit only)* | Serve the unprocessed source file | URL of source file copy |

Default values: `on_not_found=placeholder`, `on_error=placeholder`, `on_memory_limit=placeholder`.

**Override via `.env`:**

```env
MEDIA_ON_NOT_FOUND=placeholder        # placeholder | broken | exception
MEDIA_ON_ERROR=placeholder            # placeholder | broken | exception
MEDIA_ON_MEMORY_LIMIT=placeholder     # placeholder | original | broken | exception
```

### Memory-Safe Fallback (GD)

When the GD driver detects that processing a large image would exceed the PHP memory limit, the behaviour is controlled by `on_memory_limit` (default: `'placeholder'`).

Setting `MEDIA_ON_MEMORY_LIMIT=original` serves the raw source file unchanged with `data-media-toolkit-status="original-fallback"` and `data-media-toolkit-reason="memory-limit"` attributes on the `<img>`.

---

## 📋 Logging & Failure Registry

Every error (not found, processing failure, memory bypass) is written to the Laravel application log and recorded in a local failure registry for offline retry.

### Log Configuration

```php
'image' => [
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
```

**Override via `.env`:**

```env
MEDIA_LOGGING_ENABLED=true
MEDIA_LOG_CHANNEL=daily     # optional dedicated channel
```

### Failure Registry

Failed images are persisted to `storage/media-toolkit/failures.json` so you can retry them later without re-deploying:

```json
{
  "resources/images/hero.jpg": {
    "reason": "memory_limit",
    "count": 3,
    "first_occurred": "2026-02-22T12:00:00+00:00",
    "last_occurred":  "2026-02-22T12:05:00+00:00",
    "params": {
      "display_width": 800,
      "format": "webp",
      "quality": 80,
      "operations_fingerprint": "d41d8cd9...",
      "single_only": true
    }
  }
}
```

### `media:process-pending` Command

Retry all registered failures with an unlimited memory limit (useful for processing large images that were bypassed at request time due to GD memory constraints):

```bash
# List all pending failures
php artisan media:process-pending --list

# Attempt offline generation (unlimited memory by default)
php artisan media:process-pending

# Use a custom memory limit
php artisan media:process-pending --memory=512M

# Clear the registry
php artisan media:process-pending --clear
```

> **Note:** The command regenerates the base resize/format variant. Operations (filters, watermarks) cannot be reproduced from the fingerprint alone — a warning is shown for entries with non-trivial operation chains.

---

## 🔀 GD vs. Imagick

| Feature | GD | Imagick |
|---------|:--:|:-------:|
| JPEG | ✅ | ✅ |
| PNG | ✅ | ✅ |
| WebP | ✅ (if `imagewebp` available) | ✅ |
| AVIF | ✅ (if `imageavif` available, PHP 8.1+) | ✅ (if compiled with AVIF) |

> Imagick is automatically preferred when available and generally offers better quality and performance for large images.

---

---

## 🎬 Video — `Media::video()`

Serves a video file unchanged and renders a `<video>` HTML element. No transcoding is performed — the source file is copied once to the public output directory.

**Supported extensions:** `mp4`, `webm`, `ogg`, `mov`

```php
// Minimal usage
echo Media::video('resources/videos/intro.mp4')->html(class: 'w-full');

// With chain options
echo Media::video('resources/videos/hero.mp4')
    ->controls()
    ->muted()
    ->autoplay()
    ->loop()
    ->width(1280)
    ->height(720)
    ->poster('resources/images/poster.jpg')
    ->html(class: 'w-full rounded-lg', id: 'hero-video');

// URL only (e.g. for custom players)
$url = Media::video('resources/videos/clip.mp4')->url();
```

### Chain Methods

| Method | Description |
|--------|-------------|
| `->controls(bool $show = true)` | Show/hide the native browser controls bar (default: `true`) |
| `->autoplay()` | Start playback automatically (requires `->muted()` in most browsers) |
| `->muted()` | Mute the audio track |
| `->loop()` | Loop the video when it ends |
| `->preload(string)` | Preload hint: `'none'` \| `'metadata'` (default) \| `'auto'` |
| `->poster(string $path)` | Poster image shown before playback; same path format as the source |
| `->width(int)` | Display width in pixels |
| `->height(int)` | Display height in pixels |

### `html()` Signature

```php
->html(string $class = '', ?string $id = null, array $attributes = []): string
```

---

## 🎵 Audio — `Media::audio()`

Serves an audio file unchanged and renders an `<audio>` HTML element. No transcoding is performed.

**Supported extensions:** `mp3`, `ogg`, `wav`, `aac`, `m4a`, `opus`, `flac`

```php
// Minimal usage
echo Media::audio('resources/audio/podcast.mp3')->html(class: 'w-full');

// With chain options
echo Media::audio('resources/audio/track.ogg')
    ->controls()
    ->preload('none')
    ->loop()
    ->html(class: 'w-full', id: 'audio-player');

// URL only
$url = Media::audio('resources/audio/intro.mp3')->url();
```

### Chain Methods

| Method | Description |
|--------|-------------|
| `->controls(bool $show = true)` | Show/hide the native browser controls bar (default: `true`) |
| `->autoplay()` | Start playback automatically |
| `->muted()` | Mute the audio |
| `->loop()` | Loop the audio when it ends |
| `->preload(string)` | Preload hint: `'none'` \| `'metadata'` (default) \| `'auto'` |

### `html()` Signature

```php
->html(string $class = '', ?string $id = null, array $attributes = []): string
```

---

## 🖼️ SVG — `Media::svg()`

Serves an SVG file as a public URL or embeds it inline in the HTML. No processing is performed.

```php
// Default: <img> tag pointing to the served SVG
echo Media::svg('resources/images/icon.svg')->html(alt: 'Icon', class: 'w-6 h-6');

// Inline SVG embedded directly in the HTML (scripts and on* handlers filtered)
echo Media::svg('resources/images/logo.svg')
    ->inline()
    ->width(120)
    ->html(alt: 'Company Logo', class: 'fill-current');

// Skip sanitization (developer responsibility)
echo Media::svg('resources/images/animation.svg')
    ->inline(sanitize: false)
    ->html(class: 'w-full');

// URL only
$url = Media::svg('resources/images/sprite.svg')->url();
```

### Chain Methods

| Method | Description |
|--------|-------------|
| `->inline(bool $sanitize = true)` | Embed SVG content directly in HTML; `$sanitize = true` (default) removes `<script>` and `on*` attributes |
| `->width(int)` | Width in pixels — applied to `<img>` or the `<svg>` element |
| `->height(int)` | Height in pixels — applied to `<img>` or the `<svg>` element |

### `html()` Signature

```php
->html(string $alt = '', string $class = '', ?string $id = null, array $attributes = []): string
```

- **Default mode**: `$alt` is the `<img alt>` attribute.
- **Inline mode**: `$alt` sets `aria-label` and `role="img"` on the `<svg>` element.

### SVG Sanitization

When `->inline()` is called with the default `$sanitize = true`, the following are removed from the SVG before output:

| Threat | What is removed |
|--------|----------------|
| Scripting | All `<script>` elements |
| Event handlers | All `on*` attributes (e.g. `onclick`, `onload`, `onerror`) |
| Protocol injection | `href`, `src`, `xlink:href` values starting with `javascript:` |

> **Note:** This is a structural filter, not a full HTML sanitizer. For untrusted user-uploaded SVGs, use a dedicated library such as [Symfony's HtmlSanitizer](https://symfony.com/doc/current/html_sanitizer.html).

---

## 🆙 Upgrading from v1

**Breaking Changes in v2:**

| Area | v1 | v2 |
|------|----|----|
| Blade namespace | `<x-laraextend::img>` | `<x-media::img>` |
| Output directory | `public/img/optimized/` | `public/media/optimized/` |
| Config structure | flat keys | nested under `image.*` |
| Config key `quality.webp` | `config('media-toolkit.quality.webp')` | `config('media-toolkit.image.quality.webp')` |
| Config key `responsive.*` | `config('media-toolkit.responsive.*')` | `config('media-toolkit.image.responsive.*')` |
| Config key `defaults.*` | `config('media-toolkit.defaults.*')` | `config('media-toolkit.image.defaults.*')` |
| Artisan: clear cache | `media:img-clear` | `media:cache-clear` |
| Artisan: warm cache | `media:img-warm` | `media:cache-warm` |
| Error behavior | env-based (empty string / HTML comment) | configurable `placeholder` / `broken` / `exception` |

**Migration steps:**

1. Update `config/media-toolkit.php` to the new nested structure (or re-publish it):
   ```bash
   php artisan vendor:publish --tag=media-toolkit-config --force
   ```

2. Clear the old cache directory:
   ```bash
   php artisan media:cache-clear
   rm -rf public/img/optimized   # remove old directory manually if needed
   ```

3. Update `.gitignore`:
   ```gitignore
   # Remove:
   /public/img/optimized/
   # Add:
   /public/media/optimized/
   ```

4. Update Blade templates — replace `<x-laraextend::*>` with `<x-media::*>`.
   The helper functions (`img()`, `responsive_img()`, `picture()`, `img_url()`) are still available but now go through the new builder and are marked deprecated.

---

## 📂 .gitignore

```gitignore
/public/media/optimized/
```

---

## 🤝 Contributing

Contributions are welcome! Please fork the repository, create your feature branch and submit a pull request.

```bash
git clone https://github.com/laraextend/media-toolkit.git
cd media-toolkit
composer install
./vendor/bin/pest
```

---

## 📄 Changelog

All notable changes are documented in the [CHANGELOG](CHANGELOG.md).

---

## 🔒 Security

### Built-in Input Validation

Every source path and watermark path is validated before any filesystem access occurs. The following are rejected with a `MediaBuilderException`:

| Threat | Example | Check |
|---|---|---|
| Directory traversal | `../../etc/passwd` | `..` in any path segment |
| Null byte injection | `image.jpg\0.php` | `\x00` in path |
| Log injection (CRLF) | `image.jpg\nFAKE_LOG` | `\r` / `\n` in path |
| Disallowed file type | `config/database.php` | Extension whitelist |

**Allowed image extensions:** `jpg`, `jpeg`, `png`, `gif`, `webp`, `avif`, `bmp`, `tiff`, `tif`

```php
// All of these throw MediaBuilderException:
Media::image('../../etc/passwd')->html();            // traversal
Media::image("logo.jpg\nINJECTED")->html();          // CRLF
Media::image('config/database.php')->html();          // disallowed extension
Media::image('resources/img.svg')->html();            // SVG excluded (scripting risk)

Media::image($src)->watermark('/../../etc/shadow')->html();   // traversal in watermark
Media::image($src)->watermark('http://x.com/../../etc')->html(); // traversal in URL
```

### Watermark Path Confinement

Watermark sources are validated against their respective root directories:
- **Relative paths** → must resolve within `base_path()`
- **Web paths** (`/...`) → must resolve within `public_path()`
- **HTTP(S) URLs** → extracted URL path must resolve within `public_path()`

### Memory Safety (GD)

When the GD driver estimates that processing would exceed the PHP memory limit, the image is never loaded — preventing fatal OOM errors. Image dimensions from EXIF metadata are capped at 65 535 px per axis to prevent integer overflow through adversarially crafted image headers.

### Developer Responsibility

The package validates paths structurally. It does **not** enforce which directories developers may use as image sources. If you accept image paths from user input, ensure the input is validated by your application before passing it to `Media::image()`.

### Reporting Vulnerabilities

If you discover a security issue, please send an email to [security@laraextend.dev](mailto:security@laraextend.dev) instead of creating a public issue.

---

## 📜 License

MIT License. See [LICENSE](LICENSE.md) for details.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/laraextend">LaraExtend</a>
</p>
