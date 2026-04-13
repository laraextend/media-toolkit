<?php

use Laraextend\MediaToolkit\Exceptions\MediaBuilderException;
use Laraextend\MediaToolkit\Facades\Media;

// ─────────────────────────────────────────────────────────────
//  url()
// ─────────────────────────────────────────────────────────────

test('url() copies the SVG to public and returns a URL path', function (): void {
    $url = Media::svg($this->testSvg)->url();

    expect($url)->toStartWith('/')->toContain('media/optimized');

    $absolute = public_path(ltrim($url, '/'));
    expect(file_exists($absolute))->toBeTrue();
});

test('url() returns the same path on repeated calls', function (): void {
    $first  = Media::svg($this->testSvg)->url();
    $second = Media::svg($this->testSvg)->url();

    expect($first)->toBe($second);
});

test('url() throws MediaBuilderException when file not found', function (): void {
    expect(fn () => Media::svg('resources/icons/nonexistent.svg')->url())
        ->toThrow(MediaBuilderException::class, 'SVG file not found');
});

// ─────────────────────────────────────────────────────────────
//  html() — default <img> mode
// ─────────────────────────────────────────────────────────────

test('html() renders an <img> tag by default', function (): void {
    $html = Media::svg($this->testSvg)->html(alt: 'Icon');

    expect($html)->toContain('<img')->toContain('alt="Icon"');
    expect($html)->not->toContain('<svg');
});

test('html() includes src pointing to the public URL', function (): void {
    $html = Media::svg($this->testSvg)->html();

    expect($html)->toContain('src="');
});

test('width() and height() are applied to the img tag', function (): void {
    $html = Media::svg($this->testSvg)->width(24)->height(24)->html();

    expect($html)->toContain('width="24"')->toContain('height="24"');
});

test('class is applied to the img element', function (): void {
    $html = Media::svg($this->testSvg)->html(class: 'icon w-6');

    expect($html)->toContain('class="icon w-6"');
});

test('id is applied to the img element', function (): void {
    $html = Media::svg($this->testSvg)->html(id: 'logo');

    expect($html)->toContain('id="logo"');
});

test('extra attributes are forwarded to the img element', function (): void {
    $html = Media::svg($this->testSvg)->html(attributes: ['data-icon' => 'home']);

    expect($html)->toContain('data-icon="home"');
});

// ─────────────────────────────────────────────────────────────
//  html() — inline SVG mode
// ─────────────────────────────────────────────────────────────

test('inline() renders inline SVG content instead of img', function (): void {
    $html = Media::svg($this->testSvg)->inline()->html();

    expect($html)->toContain('<svg')->toContain('</svg>');
    expect($html)->not->toContain('<img');
});

test('inline() injects class into the svg element', function (): void {
    $html = Media::svg($this->testSvg)->inline()->html(class: 'fill-current w-6');

    expect($html)->toContain('class="fill-current w-6"');
});

test('inline() injects id into the svg element', function (): void {
    $html = Media::svg($this->testSvg)->inline()->html(id: 'main-logo');

    expect($html)->toContain('id="main-logo"');
});

test('inline() injects width and height into the svg element', function (): void {
    $html = Media::svg($this->testSvg)->inline()->width(32)->height(32)->html();

    expect($html)->toContain('width="32"')->toContain('height="32"');
});

test('inline() sets aria-label and role="img" when alt is provided', function (): void {
    $html = Media::svg($this->testSvg)->inline()->html(alt: 'Home icon');

    expect($html)->toContain('aria-label="Home icon"')->toContain('role="img"');
});

test('inline() does not add aria-label when alt is empty', function (): void {
    $html = Media::svg($this->testSvg)->inline()->html();

    expect($html)->not->toContain('aria-label');
});

// ─────────────────────────────────────────────────────────────
//  SVG sanitization
// ─────────────────────────────────────────────────────────────

test('inline() removes script tags by default', function (): void {
    $html = Media::svg($this->testSvgWithScript)->inline()->html();

    expect($html)->not->toContain('<script');
});

test('inline() removes on* event-handler attributes by default', function (): void {
    $html = Media::svg($this->testSvgWithScript)->inline()->html();

    expect($html)->not->toContain('onclick');
});

test('inline() removes javascript: href values by default', function (): void {
    $html = Media::svg($this->testSvgWithScript)->inline()->html();

    expect($html)->not->toContain('javascript:');
});

test('inline(sanitize: false) preserves script tags', function (): void {
    $html = Media::svg($this->testSvgWithScript)->inline(sanitize: false)->html();

    expect($html)->toContain('<script');
});

// ─────────────────────────────────────────────────────────────
//  security
// ─────────────────────────────────────────────────────────────

test('path traversal throws MediaBuilderException', function (): void {
    expect(fn () => Media::svg('../../etc/passwd')->url())
        ->toThrow(MediaBuilderException::class, 'directory traversal');
});

test('null byte in path throws MediaBuilderException', function (): void {
    expect(fn () => Media::svg("resources/icons/test.svg\0")->url())
        ->toThrow(MediaBuilderException::class, 'control characters');
});

test('CRLF in path throws MediaBuilderException', function (): void {
    expect(fn () => Media::svg("resources/icons/test.svg\nINJECTED")->url())
        ->toThrow(MediaBuilderException::class, 'control characters');
});

test('disallowed extension throws MediaBuilderException', function (): void {
    expect(fn () => Media::svg('resources/icons/icon.png')->url())
        ->toThrow(MediaBuilderException::class, 'not an allowed media format');
});
