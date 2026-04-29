<?php

use Laraextend\MediaToolkit\Components\Image\Img;
use Laraextend\MediaToolkit\Components\Image\ImgUrl;
use Laraextend\MediaToolkit\Components\Image\Picture;
use Laraextend\MediaToolkit\Components\Image\ResponsiveImg;

test('img component has correct default values', function (): void {
    $component = new Img(src: 'test.jpg');

    expect($component->src)->toBe('test.jpg');
    expect($component->alt)->toBe('');
    expect($component->width)->toBeNull();
    expect($component->height)->toBeNull();
    expect($component->class)->toBe('');
    expect($component->format)->toBeNull();       // config-based default
    expect($component->loading)->toBeNull();      // config-based default
    expect($component->fetchpriority)->toBeNull(); // config-based default
    expect($component->id)->toBeNull();
    expect($component->original)->toBeFalse();
    expect($component->extraAttributes)->toBe([]);
});

test('responsive-img component has correct default values', function (): void {
    $component = new ResponsiveImg(src: 'test.jpg');

    expect($component->src)->toBe('test.jpg');
    expect($component->sizes)->toBeNull();        // config-based default
    expect($component->format)->toBeNull();       // config-based default
    expect($component->loading)->toBeNull();      // config-based default
    expect($component->fetchpriority)->toBeNull(); // config-based default
});

test('picture component has correct default values', function (): void {
    $component = new Picture(src: 'test.jpg');

    expect($component->src)->toBe('test.jpg');
    expect($component->formats)->toBeNull();       // config-based default
    expect($component->fallbackFormat)->toBeNull(); // config-based default
    expect($component->loading)->toBeNull();
    expect($component->fetchpriority)->toBeNull();
    expect($component->imgClass)->toBe('');
    expect($component->sourceClass)->toBe('');
});

test('img-url component has correct default values', function (): void {
    $component = new ImgUrl(src: 'test.jpg');

    expect($component->src)->toBe('test.jpg');
    expect($component->width)->toBeNull();
    expect($component->format)->toBeNull();        // config-based default
    expect($component->quality)->toBeNull();       // config-based default
    expect($component->original)->toBeFalse();
});

test('img-url render returns a callable that produces empty string for missing file', function (): void {
    $component = new ImgUrl(src: 'non-existent.jpg');
    $result = $component->render();

    expect($result)->toBeCallable();
    expect($result())->toBe('');
});

test('img component accepts extra attributes', function (): void {
    $component = new Img(
        src: 'test.jpg',
        extraAttributes: ['data-lightbox' => 'gallery', 'style' => 'border-radius: 8px'],
    );

    expect($component->extraAttributes)->toBe([
        'data-lightbox' => 'gallery',
        'style'         => 'border-radius: 8px',
    ]);
});

test('img component accepts quality prop', function (): void {
    $component = new Img(src: 'test.jpg', quality: 60);

    expect($component->quality)->toBe(60);
});

test('img component quality defaults to null (uses config)', function (): void {
    $component = new Img(src: 'test.jpg');

    expect($component->quality)->toBeNull();
});

test('responsive-img component accepts quality prop', function (): void {
    $component = new ResponsiveImg(src: 'test.jpg', quality: 60);

    expect($component->quality)->toBe(60);
});

test('responsive-img component quality defaults to null (uses config)', function (): void {
    $component = new ResponsiveImg(src: 'test.jpg');

    expect($component->quality)->toBeNull();
});

test('picture component accepts quality prop', function (): void {
    $component = new Picture(src: 'test.jpg', quality: 60);

    expect($component->quality)->toBe(60);
});

test('picture component quality defaults to null (uses config)', function (): void {
    $component = new Picture(src: 'test.jpg');

    expect($component->quality)->toBeNull();
});

test('img-url component accepts quality prop', function (): void {
    $component = new ImgUrl(src: 'test.jpg', quality: 60);

    expect($component->quality)->toBe(60);
});

test('img-url component quality defaults to null (uses config)', function (): void {
    $component = new ImgUrl(src: 'test.jpg');

    expect($component->quality)->toBeNull();
});
