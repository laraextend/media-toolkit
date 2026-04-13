<?php

namespace Laraextend\MediaToolkit\Tests;

use Illuminate\Support\Facades\File;
use Laraextend\MediaToolkit\Cache\ManifestCache;
use Laraextend\MediaToolkit\MediaToolkitServiceProvider;
use Laraextend\MediaToolkit\Processing\ImageProcessor;
use Laraextend\MediaToolkit\Rendering\ImageHtmlRenderer;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected string $fixturesRoot = 'tests/fixtures/image-optimizer';

    protected string $landscapeImage = 'tests/fixtures/image-optimizer/landscape.jpg';

    protected string $portraitImage = 'tests/fixtures/image-optimizer/portrait.jpg';

    protected string $squareImage = 'tests/fixtures/image-optimizer/square.jpg';

    // ── Media fixtures (Phase 2–4) ─────────────────────────────────────────

    protected string $fixturesMediaRoot = 'tests/fixtures/media';

    protected string $testVideo  = 'tests/fixtures/media/test-video.mp4';
    protected string $testVideo2 = 'tests/fixtures/media/test-video.webm';

    protected string $testAudio = 'tests/fixtures/media/test-audio.mp3';

    protected string $testSvg = 'tests/fixtures/media/test-icon.svg';

    protected string $testSvgWithScript = 'tests/fixtures/media/test-icon-script.svg';

    protected function getPackageProviders($app): array
    {
        return [
            MediaToolkitServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.env', 'local');
        $app['config']->set('app.url', 'http://localhost');
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['media-toolkit' => $this->defaultConfig()]);
        $this->resetSingletons();

        $this->prepareFixtureImages();
        $this->prepareMediaFixtures();
        $this->cleanOutputDirectories();
    }

    protected function tearDown(): void
    {
        $this->cleanOutputDirectories();
        parent::tearDown();
    }

    protected function defaultConfig(): array
    {
        return require dirname(__DIR__).'/config/media-toolkit.php';
    }

    /**
     * Override package config at runtime and reset all cached singletons.
     * The next call to Media::image() or any helper will pick up the new config.
     */
    protected function setPackageConfig(array $overrides): void
    {
        $merged = array_replace_recursive($this->defaultConfig(), $overrides);
        config(['media-toolkit' => $merged]);
        $this->resetSingletons();
    }

    /**
     * Forget all singletons that capture config values at instantiation time,
     * so that the next resolve picks up the currently active config.
     */
    protected function resetSingletons(): void
    {
        $this->app->forgetInstance(ImageProcessor::class);
        $this->app->forgetInstance(ManifestCache::class);
        $this->app->forgetInstance(ImageHtmlRenderer::class);
    }

    protected function cleanOutputDirectories(): void
    {
        // Legacy default (pre-v2) — kept so tests do not leave orphaned files
        File::deleteDirectory(public_path('img/optimized'));

        // Current defaults
        File::deleteDirectory(public_path('media/optimized'));
        File::deleteDirectory(public_path('custom/optimized'));

        // Whatever the config currently says
        $configuredDir = config('media-toolkit.output_dir', 'media/optimized');
        File::deleteDirectory(public_path($configuredDir));
    }

    protected function prepareFixtureImages(): void
    {
        $dir = base_path($this->fixturesRoot);
        File::ensureDirectoryExists($dir, 0755, true);

        $this->createTestImage(base_path($this->landscapeImage), 800, 400);
        $this->createTestImage(base_path($this->portraitImage), 400, 800);
        $this->createTestImage(base_path($this->squareImage), 300, 300);
    }

    protected function createTestImage(string $path, int $width, int $height): void
    {
        if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
            $image = imagecreatetruecolor($width, $height);
            $background = imagecolorallocate($image, 120, 160, 210);
            imagefill($image, 0, 0, $background);
            imagejpeg($image, $path, 90);
            imagedestroy($image);

            return;
        }

        if (class_exists(\Imagick::class)) {
            $image = new \Imagick;
            $image->newImage($width, $height, new \ImagickPixel('rgb(120,160,210)'), 'jpg');
            $image->setImageFormat('jpg');
            $image->writeImage($path);
            $image->clear();

            return;
        }

        $this->markTestSkipped('Neither GD nor Imagick is available to create fixture images.');
    }

    // ── Media fixtures (Phase 2–4) ─────────────────────────────────────────

    protected function prepareMediaFixtures(): void
    {
        $dir = base_path($this->fixturesMediaRoot);
        File::ensureDirectoryExists($dir, 0755, true);

        // Minimal dummy MP4 — just needs to exist with the right extension.
        if (! File::exists(base_path($this->testVideo))) {
            File::put(base_path($this->testVideo), "\x00\x00\x00\x18ftypisom");
        }

        // Minimal dummy WebM — EBML magic bytes.
        if (! File::exists(base_path($this->testVideo2))) {
            File::put(base_path($this->testVideo2), "\x1A\x45\xDF\xA3");
        }

        // Minimal dummy MP3 — frame sync header bytes.
        if (! File::exists(base_path($this->testAudio))) {
            File::put(base_path($this->testAudio), "\xFF\xFB\x90\x00");
        }

        // Clean SVG icon.
        File::put(base_path($this->testSvg), <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" fill="blue"/>
            </svg>
            SVG);

        // SVG with XSS payloads for sanitization tests.
        File::put(base_path($this->testSvgWithScript), <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <script>alert('xss')</script>
                <circle cx="12" cy="12" r="10" onclick="alert('click')" fill="blue"/>
                <a href="javascript:alert('href')">link</a>
            </svg>
            SVG);
    }

    protected function assertGeneratedPublicFileExists(string $html): void
    {
        preg_match('/\ssrc="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'No src attribute found in rendered HTML.');

        $path = parse_url($matches[1], PHP_URL_PATH);
        $this->assertNotFalse($path);
        $this->assertNotNull($path);
        $this->assertTrue(str_starts_with($path, '/'));

        $absolute = public_path(ltrim($path, '/'));
        $this->assertFileExists($absolute);
    }
}
