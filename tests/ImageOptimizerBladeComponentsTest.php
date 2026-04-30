<?php

use Illuminate\Support\Facades\Blade;
use Laraextend\MediaToolkit\Components\Image\Img;
use Laraextend\MediaToolkit\Components\Image\Picture;
use Laraextend\MediaToolkit\Components\Image\ResponsiveImg;

// ─────────────────────────────────────────────────────────────
//  <x-media::img>
// ─────────────────────────────────────────────────────────────

test('img blade component renders optimized markup', function (): void {
    $html = Blade::render(
        '<x-media::img :src="$src" alt="Component image" :width="320" format="jpg" />',
        ['src' => $this->landscapeImage],
    );

    expect($html)
        ->toContain('<img')
        ->toContain('alt="Component image"')
        ->toContain('width="320"')
        ->toContain('height="160"')
        ->toContain('.jpg');
});

test('img blade component forwards bag attributes including wire directives', function (): void {
    // Blade::render() does not populate ComponentAttributeBag in test context,
    // so we instantiate the component directly and use withAttributes() to simulate
    // what the Blade compiler does in a real app.
    $component = new Img(src: $this->landscapeImage, alt: 'Image', width: 320, format: 'jpg');
    $component->withAttributes(['wire:key' => 'hero-image', 'data-track' => '1']);
    $html = $component->render();

    expect($html)
        ->toContain('wire:key="hero-image"')
        ->toContain('data-track="1"');
});

test('img blade component forwards explicit extra-attributes', function (): void {
    $html = Blade::render(
        '<x-media::img :src="$src" alt="Image" :width="320" format="jpg" :extra-attributes="[\'wire:key\' => \'hero-image\', \'data-track\' => \'1\']" />',
        ['src' => $this->landscapeImage],
    );

    expect($html)
        ->toContain('wire:key="hero-image"')
        ->toContain('data-track="1"');
});

test('extra-attributes override bag attributes with the same key on img', function (): void {
    $html = Blade::render(
        '<x-media::img :src="$src" alt="Image" :width="320" format="jpg" wire:key="from-bag" :extra-attributes="[\'wire:key\' => \'from-extra\']" />',
        ['src' => $this->landscapeImage],
    );

    expect($html)->toContain('wire:key="from-extra"');
    expect(substr_count($html, 'wire:key='))->toBe(1);
});

// ─────────────────────────────────────────────────────────────
//  <x-media::responsive-img>
// ─────────────────────────────────────────────────────────────

test('responsive-img blade component renders srcset markup', function (): void {
    $html = Blade::render(
        '<x-media::responsive-img :src="$src" alt="Responsive" :width="400" format="jpg" />',
        ['src' => $this->landscapeImage],
    );

    expect($html)
        ->toContain('<img')
        ->toContain('srcset="')
        ->toContain('alt="Responsive"');
});

test('responsive-img blade component forwards wire directives', function (): void {
    $component = new ResponsiveImg(src: $this->landscapeImage, alt: 'Responsive', width: 400, format: 'jpg');
    $component->withAttributes(['wire:key' => 'responsive-item']);
    $html = $component->render();

    expect($html)->toContain('wire:key="responsive-item"');
});

// ─────────────────────────────────────────────────────────────
//  <x-media::picture>
// ─────────────────────────────────────────────────────────────

test('picture blade component renders picture markup', function (): void {
    $html = Blade::render(
        '<x-media::picture :src="$src" alt="Picture" :width="400" :formats="[\'jpg\']" fallback-format="jpg" />',
        ['src' => $this->landscapeImage],
    );

    expect($html)
        ->toContain('<picture')
        ->toContain('<source')
        ->toContain('<img')
        ->toContain('alt="Picture"');
});

test('picture blade component applies custom sizes to picture output', function (): void {
    $html = Blade::render(
        '<x-media::picture :src="$src" alt="Picture" :width="400" :formats="[\'jpg\']" fallback-format="jpg" sizes="50vw" />',
        ['src' => $this->landscapeImage],
    );

    expect($html)->toContain('sizes="50vw"');
});

test('picture blade component forwards wire:key to picture element', function (): void {
    $component = new Picture(
        src: $this->landscapeImage,
        alt: 'Picture',
        width: 400,
        formats: ['jpg'],
        fallbackFormat: 'jpg',
    );
    $component->withAttributes(['wire:key' => 'picture-item']);
    $html = $component->render();

    // wire:key must appear on the <picture> opening tag (before the first >)
    $pictureOpenClose = strpos($html, '>');
    $wireKeyPos       = strpos($html, 'wire:key=');

    expect($wireKeyPos)->not->toBeFalse();
    expect($wireKeyPos)->toBeLessThan($pictureOpenClose);
});

test('picture blade component wire:key appears exactly once', function (): void {
    $component = new Picture(
        src: $this->landscapeImage,
        alt: 'Picture',
        width: 400,
        formats: ['jpg'],
        fallbackFormat: 'jpg',
    );
    $component->withAttributes(['wire:key' => 'picture-item']);
    $html = $component->render();

    expect(substr_count($html, 'wire:key="picture-item"'))->toBe(1);
});

test('picture extra-attributes go to img element not picture element', function (): void {
    $html = Blade::render(
        '<x-media::picture :src="$src" alt="Picture" :width="400" :formats="[\'jpg\']" fallback-format="jpg" :extra-attributes="[\'data-caption\' => \'My photo\']" />',
        ['src' => $this->landscapeImage],
    );

    // data-caption should appear after the <picture> opening tag closes
    $pictureOpenClose = strpos($html, '>');
    $captionPos       = strpos($html, 'data-caption=');

    expect($captionPos)->not->toBeNull();
    expect($captionPos)->toBeGreaterThan($pictureOpenClose);
});

// ─────────────────────────────────────────────────────────────
//  <x-media::img-url>
// ─────────────────────────────────────────────────────────────

test('img-url blade component returns an optimized url', function (): void {
    $url = Blade::render(
        '<x-media::img-url :src="$src" :width="400" format="jpg" />',
        ['src' => $this->landscapeImage],
    );

    expect($url)
        ->toContain('/media/optimized/')
        ->toContain('.jpg');
});
