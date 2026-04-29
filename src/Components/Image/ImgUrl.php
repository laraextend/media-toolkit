<?php

namespace Laraextend\MediaToolkit\Components\Image;

use Closure;
use Illuminate\View\Component;
use Laraextend\MediaToolkit\Facades\Media;

/**
 * <x-media::img-url>
 *
 * Returns only the URL of the processed image (not an HTML tag).
 * Useful in Blade expressions where you need a raw URL string.
 *
 * @example
 *   <div style="background-image: url('{{ $url }}')">
 *     <x-media::img-url src="resources/images/hero.jpg" :width="1200" />
 *   </div>
 *
 *   <meta property="og:image"
 *         content="<x-media::img-url src='resources/images/og.jpg' :width='1200' format='jpg' />">
 */
class ImgUrl extends Component
{
    public function __construct(
        public readonly string  $src,
        public readonly ?int    $width    = null,
        public readonly ?string $format   = null,
        public readonly ?int    $quality  = null,
        public readonly bool    $original = false,
    ) {}

    public function render(): Closure
    {
        $builder = Media::image($this->src);

        if ($this->original) {
            $builder->original();
        } else {
            if ($this->width !== null) {
                $builder->resize(width: $this->width);
            }
            if ($this->format !== null) {
                $builder->format($this->format);
            }
            if ($this->quality !== null) {
                $builder->quality($this->quality);
            }
        }

        $url = $builder->url();

        return fn () => $url;
    }
}
