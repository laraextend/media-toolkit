<?php

namespace Laraextend\MediaToolkit\Facades;

use Illuminate\Support\Facades\Facade;
use Laraextend\MediaToolkit\Builders\AudioBuilder;
use Laraextend\MediaToolkit\Builders\ImageBuilder;
use Laraextend\MediaToolkit\Builders\SvgBuilder;
use Laraextend\MediaToolkit\Builders\VideoBuilder;

/**
 * @method static ImageBuilder image(string $path)
 * @method static VideoBuilder video(string $path)
 * @method static AudioBuilder audio(string $path)
 * @method static SvgBuilder   svg(string $path)
 *
 * @see \Laraextend\MediaToolkit\MediaToolkitServiceProvider
 */
class Media extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'media-toolkit';
    }
}
