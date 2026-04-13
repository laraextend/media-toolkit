<?php

use Laraextend\MediaToolkit\Exceptions\MediaBuilderException;
use Laraextend\MediaToolkit\Facades\Media;

// ─────────────────────────────────────────────────────────────
//  url()
// ─────────────────────────────────────────────────────────────

test('url() copies the audio file to public and returns a URL path', function (): void {
    $url = Media::audio($this->testAudio)->url();

    expect($url)->toStartWith('/')->toContain('media/optimized');

    $absolute = public_path(ltrim($url, '/'));
    expect(file_exists($absolute))->toBeTrue();
});

test('url() returns the same path on repeated calls', function (): void {
    $first  = Media::audio($this->testAudio)->url();
    $second = Media::audio($this->testAudio)->url();

    expect($first)->toBe($second);
});

test('url() throws MediaBuilderException when file not found', function (): void {
    expect(fn () => Media::audio('resources/audio/nonexistent.mp3')->url())
        ->toThrow(MediaBuilderException::class, 'Audio file not found');
});

// ─────────────────────────────────────────────────────────────
//  html() — tag structure
// ─────────────────────────────────────────────────────────────

test('html() renders an <audio> tag with closing tag', function (): void {
    $html = Media::audio($this->testAudio)->html();

    expect($html)->toContain('<audio')->toContain('</audio>');
});

test('html() contains the public src attribute', function (): void {
    $html = Media::audio($this->testAudio)->html();

    expect($html)->toContain('src="');
});

test('html() includes controls attribute by default', function (): void {
    $html = Media::audio($this->testAudio)->html();

    expect($html)->toContain('controls');
});

test('html() includes preload="metadata" by default', function (): void {
    $html = Media::audio($this->testAudio)->html();

    expect($html)->toContain('preload="metadata"');
});

// ─────────────────────────────────────────────────────────────
//  chain methods
// ─────────────────────────────────────────────────────────────

test('controls(false) omits the controls attribute', function (): void {
    $html = Media::audio($this->testAudio)->controls(false)->html();

    expect($html)->not->toContain('controls');
});

test('autoplay() adds the autoplay attribute', function (): void {
    $html = Media::audio($this->testAudio)->autoplay()->html();

    expect($html)->toContain('autoplay');
});

test('muted() adds the muted attribute', function (): void {
    $html = Media::audio($this->testAudio)->muted()->html();

    expect($html)->toContain('muted');
});

test('loop() adds the loop attribute', function (): void {
    $html = Media::audio($this->testAudio)->loop()->html();

    expect($html)->toContain('loop');
});

test('preload() sets the preload attribute', function (): void {
    $html = Media::audio($this->testAudio)->preload('auto')->html();

    expect($html)->toContain('preload="auto"');
});

test('invalid preload value falls back to metadata', function (): void {
    $html = Media::audio($this->testAudio)->preload('bogus')->html();

    expect($html)->toContain('preload="metadata"');
});

// ─────────────────────────────────────────────────────────────
//  html() — HTML attributes
// ─────────────────────────────────────────────────────────────

test('class is applied to the audio element', function (): void {
    $html = Media::audio($this->testAudio)->html(class: 'w-full');

    expect($html)->toContain('class="w-full"');
});

test('id is applied to the audio element', function (): void {
    $html = Media::audio($this->testAudio)->html(id: 'podcast-player');

    expect($html)->toContain('id="podcast-player"');
});

test('extra attributes are forwarded to the audio element', function (): void {
    $html = Media::audio($this->testAudio)->html(
        attributes: ['data-track' => '1', 'aria-label' => 'Episode 1'],
    );

    expect($html)->toContain('data-track="1"')->toContain('aria-label="Episode 1"');
});

// ─────────────────────────────────────────────────────────────
//  security
// ─────────────────────────────────────────────────────────────

test('path traversal throws MediaBuilderException', function (): void {
    expect(fn () => Media::audio('../../etc/passwd')->url())
        ->toThrow(MediaBuilderException::class, 'directory traversal');
});

test('null byte in path throws MediaBuilderException', function (): void {
    expect(fn () => Media::audio("resources/audio/test.mp3\0")->url())
        ->toThrow(MediaBuilderException::class, 'control characters');
});

test('CRLF in path throws MediaBuilderException', function (): void {
    expect(fn () => Media::audio("resources/audio/test.mp3\nINJECTED")->url())
        ->toThrow(MediaBuilderException::class, 'control characters');
});

test('disallowed extension throws MediaBuilderException', function (): void {
    expect(fn () => Media::audio('resources/audio/hack.php')->url())
        ->toThrow(MediaBuilderException::class, 'not an allowed media format');
});
