<?php

use Laraextend\MediaToolkit\Exceptions\MediaBuilderException;
use Laraextend\MediaToolkit\Facades\Media;

// ─────────────────────────────────────────────────────────────
//  url()
// ─────────────────────────────────────────────────────────────

test('url() copies the video to public and returns a URL path', function (): void {
    $url = Media::video($this->testVideo)->url();

    expect($url)->toStartWith('/')->toContain('media/optimized');

    $absolute = public_path(ltrim($url, '/'));
    expect(file_exists($absolute))->toBeTrue();
});

test('url() returns the same path on repeated calls', function (): void {
    $first  = Media::video($this->testVideo)->url();
    $second = Media::video($this->testVideo)->url();

    expect($first)->toBe($second);
});

test('url() throws MediaBuilderException when file not found', function (): void {
    expect(fn () => Media::video('resources/videos/nonexistent.mp4')->url())
        ->toThrow(MediaBuilderException::class, 'Video file not found');
});

// ─────────────────────────────────────────────────────────────
//  html() — tag structure
// ─────────────────────────────────────────────────────────────

test('html() renders a <video> tag with closing tag', function (): void {
    $html = Media::video($this->testVideo)->html();

    expect($html)->toContain('<video')->toContain('</video>');
});

test('html() contains the public src attribute', function (): void {
    $html = Media::video($this->testVideo)->html();

    expect($html)->toContain('src="');
});

test('html() includes controls attribute by default', function (): void {
    $html = Media::video($this->testVideo)->html();

    expect($html)->toContain('controls');
});

test('html() includes preload="metadata" by default', function (): void {
    $html = Media::video($this->testVideo)->html();

    expect($html)->toContain('preload="metadata"');
});

// ─────────────────────────────────────────────────────────────
//  chain methods
// ─────────────────────────────────────────────────────────────

test('controls(false) omits the controls attribute', function (): void {
    $html = Media::video($this->testVideo)->controls(false)->html();

    expect($html)->not->toContain('controls');
});

test('autoplay() adds the autoplay attribute', function (): void {
    $html = Media::video($this->testVideo)->autoplay()->html();

    expect($html)->toContain('autoplay');
});

test('muted() adds the muted attribute', function (): void {
    $html = Media::video($this->testVideo)->muted()->html();

    expect($html)->toContain('muted');
});

test('loop() adds the loop attribute', function (): void {
    $html = Media::video($this->testVideo)->loop()->html();

    expect($html)->toContain('loop');
});

test('preload() sets the preload attribute', function (): void {
    $html = Media::video($this->testVideo)->preload('none')->html();

    expect($html)->toContain('preload="none"');
});

test('invalid preload value falls back to metadata', function (): void {
    $html = Media::video($this->testVideo)->preload('invalid')->html();

    expect($html)->toContain('preload="metadata"');
});

test('width() sets the width attribute', function (): void {
    $html = Media::video($this->testVideo)->width(1280)->html();

    expect($html)->toContain('width="1280"');
});

test('height() sets the height attribute', function (): void {
    $html = Media::video($this->testVideo)->height(720)->html();

    expect($html)->toContain('height="720"');
});

// ─────────────────────────────────────────────────────────────
//  html() — HTML attributes
// ─────────────────────────────────────────────────────────────

test('class is applied to the video element', function (): void {
    $html = Media::video($this->testVideo)->html(class: 'w-full rounded');

    expect($html)->toContain('class="w-full rounded"');
});

test('id is applied to the video element', function (): void {
    $html = Media::video($this->testVideo)->html(id: 'hero-video');

    expect($html)->toContain('id="hero-video"');
});

test('extra attributes are forwarded to the video element', function (): void {
    $html = Media::video($this->testVideo)->html(
        attributes: ['data-player' => 'custom', 'aria-label' => 'Intro video'],
    );

    expect($html)->toContain('data-player="custom"')->toContain('aria-label="Intro video"');
});

// ─────────────────────────────────────────────────────────────
//  security
// ─────────────────────────────────────────────────────────────

test('path traversal throws MediaBuilderException', function (): void {
    expect(fn () => Media::video('../../etc/passwd')->url())
        ->toThrow(MediaBuilderException::class, 'directory traversal');
});

test('null byte in path throws MediaBuilderException', function (): void {
    expect(fn () => Media::video("resources/videos/test.mp4\0")->url())
        ->toThrow(MediaBuilderException::class, 'control characters');
});

test('CRLF in path throws MediaBuilderException', function (): void {
    expect(fn () => Media::video("resources/videos/test.mp4\nINJECTED")->url())
        ->toThrow(MediaBuilderException::class, 'control characters');
});

test('disallowed extension throws MediaBuilderException', function (): void {
    expect(fn () => Media::video('resources/videos/hack.php')->url())
        ->toThrow(MediaBuilderException::class, 'not an allowed media format');
});

// ─────────────────────────────────────────────────────────────
//  playsinline()
// ─────────────────────────────────────────────────────────────

test('playsinline() adds the playsinline attribute', function (): void {
    $html = Media::video($this->testVideo)->playsinline()->html();

    expect($html)->toContain('playsinline');
});

test('playsinline is absent by default', function (): void {
    $html = Media::video($this->testVideo)->html();

    expect($html)->not->toContain('playsinline');
});

// ─────────────────────────────────────────────────────────────
//  source() — multi-format / multi-codec output
// ─────────────────────────────────────────────────────────────

test('source() renders <source> children instead of src attribute on the video element', function (): void {
    $html = Media::video($this->testVideo)
        ->source($this->testVideo, 'video/mp4')
        ->html();

    // <source> children must appear
    expect($html)->toContain('<source');

    // The <video> opening tag itself must NOT carry a src="…" attribute.
    // Extract the opening tag up to the first '>' to check.
    preg_match('/<video[^>]*>/', $html, $matches);
    expect($matches[0] ?? '')->not->toContain(' src="');
});

test('source() includes the type attribute when provided', function (): void {
    $html = Media::video($this->testVideo)
        ->source($this->testVideo, 'video/mp4; codecs="hvc1"')
        ->html();

    expect($html)->toContain('type="video/mp4; codecs=&quot;hvc1&quot;"');
});

test('source() omits the type attribute when null', function (): void {
    $html = Media::video($this->testVideo)
        ->source($this->testVideo)
        ->html();

    expect($html)->not->toContain('type=');
});

test('multiple source() calls render multiple <source> elements in order', function (): void {
    $html = Media::video($this->testVideo)
        ->source($this->testVideo, 'video/mp4')
        ->source($this->testVideo2, 'video/webm')
        ->html();

    expect(substr_count($html, '<source'))->toBe(2);
    // mp4 source must appear before webm source
    expect(strpos($html, 'video/mp4'))->toBeLessThan(strpos($html, 'video/webm'));
});

test('full multi-source video element renders correctly', function (): void {
    $html = Media::video($this->testVideo)
        ->autoplay()
        ->loop()
        ->muted()
        ->playsinline()
        ->source($this->testVideo, 'video/mp4; codecs="hvc1"')
        ->source($this->testVideo2, 'video/webm')
        ->html(class: 'w-full h-full object-cover', id: 'hero-video');

    expect($html)
        ->toContain('id="hero-video"')
        ->toContain('class="w-full h-full object-cover"')
        ->toContain('autoplay')
        ->toContain('loop')
        ->toContain('muted')
        ->toContain('playsinline')
        ->toContain('<source');

    // The <video> opening tag itself must NOT carry a src="…" attribute.
    preg_match('/<video[^>]*>/', $html, $matches);
    expect($matches[0] ?? '')->not->toContain(' src="');
});
