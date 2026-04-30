<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

test('img generates optimized image with explicit width', function (): void {
    $html = img(
        src: $this->landscapeImage,
        alt: 'Landscape',
        width: 400,
        format: 'jpg',
    );

    expect($html)
        ->toContain('<img')
        ->toContain('alt="Landscape"')
        ->toContain('width="400"')
        ->toContain('height="200"')
        ->toContain('.jpg');

    $this->assertGeneratedPublicFileExists($html);
});

test('img without dimensions uses original dimensions', function (): void {
    $html = img(
        src: $this->landscapeImage,
        alt: 'Original size',
        format: 'jpg',
    );

    expect($html)->toContain('width="800"')->toContain('height="400"');
});

test('img with only height calculates width proportionally', function (): void {
    $html = img(
        src: $this->landscapeImage,
        alt: 'Only height',
        height: 300,
        format: 'jpg',
    );

    expect($html)->toContain('width="600"')->toContain('height="300"');
});

test('responsive_img generates srcset and sizes', function (): void {
    $html = responsive_img(
        src: $this->landscapeImage,
        alt: 'Responsive',
        width: 400,
        sizes: '50vw',
        format: 'jpg',
    );

    expect($html)
        ->toContain('<img')
        ->toContain('srcset="')
        ->toContain(' 200w')
        ->toContain(' 300w')
        ->toContain(' 400w')
        ->toContain('sizes="50vw"')
        ->toContain('width="400"')
        ->toContain('height="200"');

    $this->assertGeneratedPublicFileExists($html);
});

test('picture generates sources and fallback image', function (): void {
    $html = picture(
        src: $this->landscapeImage,
        alt: 'Picture tag',
        width: 400,
        formats: ['png'],
        fallbackFormat: 'jpg',
        sizes: '100vw',
    );

    expect($html)
        ->toContain('<picture')
        ->toContain('<source')
        ->toContain('type="image/png"')
        ->toContain('<img')
        ->toContain('width="400"')
        ->toContain('height="200"');
});

test('picture helper generates real variant widths for width-only resize', function (): void {
    picture(
        src: $this->landscapeImage,
        alt: 'Variant widths',
        width: 538,
        formats: ['jpg'],
        fallbackFormat: 'jpg',
    );

    $dirs = File::directories(public_path('media/optimized'));
    expect($dirs)->not->toBeEmpty();

    $checked = false;

    foreach ($dirs as $dir) {
        foreach (['269', '404', '538'] as $w) {
            $file = $dir . '/landscape-' . $w . 'w.jpg';
            if (! File::exists($file)) {
                continue;
            }

            $dims = getimagesize($file);
            expect($dims)->not->toBeFalse();
            expect($dims[0])->toBe((int) $w);
            $checked = true;
        }
    }

    expect($checked)->toBeTrue();
});

test('picture fetchpriority high forces eager loading', function (): void {
    $html = picture(
        src: $this->landscapeImage,
        alt: 'High priority',
        width: 400,
        formats: ['jpg'],
        fallbackFormat: 'jpg',
        loading: 'lazy',
        fetchpriority: 'high',
    );

    expect($html)->toContain('fetchpriority="high"')->toContain('loading="eager"');
});

test('img_url returns paths for optimized and original variants', function (): void {
    $optimized = img_url(
        src: $this->landscapeImage,
        width: 400,
        format: 'jpg',
    );

    $original = img_url(
        src: $this->landscapeImage,
        original: true,
    );

    expect(Str::startsWith($optimized, '/'))->toBeTrue();
    expect($optimized)->toContain('.jpg')->toContain('/media/optimized/');

    expect(Str::startsWith($original, '/'))->toBeTrue();
    expect($original)->toContain('/originals/');
    expect(public_path(ltrim($original, '/')))->toBeFile();
});

test('img_url uses a distinct cache path when quality changes', function (): void {
    $q60 = img_url(
        src: $this->landscapeImage,
        width: 400,
        format: 'jpg',
        quality: 60,
    );

    $q90 = img_url(
        src: $this->landscapeImage,
        width: 400,
        format: 'jpg',
        quality: 90,
    );

    expect($q60)->toContain('/media/optimized/');
    expect($q90)->toContain('/media/optimized/');
    expect($q60)->not->toBe($q90);
});

test('missing source returns placeholder img by default and empty string for url', function (): void {
    $html = img(
        src: 'tests/fixtures/image-optimizer/missing.jpg',
        alt: 'Missing',
        format: 'jpg',
    );

    $url = img_url(
        src: 'tests/fixtures/image-optimizer/missing.jpg',
        format: 'jpg',
    );

    // Default on_not_found = 'placeholder' → inline SVG data URI
    expect($html)->toContain('<img')->toContain('data:image/svg+xml;base64,');
    expect($url)->toBe('');
});
