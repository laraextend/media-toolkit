<?php

namespace Laraextend\MediaToolkit\Components\Image;

use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Laraextend\MediaToolkit\Facades\Media;

/**
 * <x-media::picture>
 *
 * Renders a <picture> element with <source> elements for modern formats
 * and a fallback <img>.
 *
 * @example
 *   <x-media::picture src="resources/images/hero.jpg" alt="Hero" :width="800" />
 *   <x-media::picture src="resources/images/hero.jpg" alt="Hero" :width="800"
 *       :formats="['avif','webp']" fallback-format="jpg"
 *       sizes="(max-width: 768px) 100vw, 800px" fetchpriority="high"
 *       img-class="rounded-xl" wire:key="hero-picture" />
 */
class Picture extends Component
{
    public function __construct(
        public readonly string  $src,
        public readonly string  $alt            = '',
        public readonly ?int    $width          = null,
        public readonly ?int    $height         = null,
        public readonly string  $class          = '',
        public readonly string  $imgClass       = '',
        public readonly string  $sourceClass    = '',
        public readonly ?array  $formats        = null,
        public readonly ?string $fallbackFormat = null,
        public readonly ?int    $quality        = null,
        public readonly ?string $loading        = null,
        public readonly ?string $fetchpriority  = null,
        public readonly ?string $sizes          = null,
        public readonly ?string $id             = null,
        public readonly bool    $original       = false,
        public readonly array   $extraAttributes = [],
    ) {}

    public function render(): string
    {
        $builder = Media::image($this->src);

        if ($this->original) {
            $builder->original();
        } else {
            if ($this->width !== null || $this->height !== null) {
                $builder->resize(width: $this->width, height: $this->height);
            }

            $builder->picture(
                formats:     $this->formats,
                fallback:    $this->fallbackFormat,
                imgClass:    $this->imgClass,
                sourceClass: $this->sourceClass,
            );
            if ($this->quality !== null) {
                $builder->quality($this->quality);
            }
        }

        if ($this->loading !== null)      { $builder->loading($this->loading); }
        if ($this->fetchpriority !== null) { $builder->fetchpriority($this->fetchpriority); }

        // The Blade attribute bag (wire:key, x-bind, @click, etc.) is forwarded to
        // the outer <picture> element so that Livewire and Alpine.js directives land
        // on the correct outermost element.
        $builder->pictureAttributes($this->resolvePictureAttributes());

        return $builder->html(
            alt:        $this->alt,
            class:      $this->class,
            id:         $this->id,
            attributes: $this->extraAttributes,
        );
    }

    /**
     * Forward the Blade attribute bag to the <picture> element.
     */
    protected function resolvePictureAttributes(): array
    {
        return $this->attributes instanceof ComponentAttributeBag
            ? $this->attributes->getAttributes()
            : [];
    }
}
