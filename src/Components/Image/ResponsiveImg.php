<?php

namespace Laraextend\MediaToolkit\Components\Image;

use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Laraextend\MediaToolkit\Facades\Media;

/**
 * <x-media::responsive-img>
 *
 * Renders a responsive <img> with srcset and sizes attributes.
 *
 * @example
 *   <x-media::responsive-img src="resources/images/hero.jpg" alt="Hero" :width="800" />
 *   <x-media::responsive-img src="resources/images/hero.jpg" alt="Hero" :width="800"
 *       sizes="(max-width: 768px) 100vw, 800px" fetchpriority="high" />
 */
class ResponsiveImg extends Component
{
    public function __construct(
        public readonly string  $src,
        public readonly string  $alt            = '',
        public readonly ?int    $width          = null,
        public readonly ?int    $height         = null,
        public readonly string  $class          = '',
        public readonly ?string $format         = null,
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
            if ($this->format !== null) {
                $builder->format($this->format);
            }
            if ($this->quality !== null) {
                $builder->quality($this->quality);
            }
        }

        if ($this->loading !== null)      { $builder->loading($this->loading); }
        if ($this->fetchpriority !== null) { $builder->fetchpriority($this->fetchpriority); }

        $builder->responsive($this->sizes);

        return $builder->html(
            alt:        $this->alt,
            class:      $this->class,
            id:         $this->id,
            attributes: $this->resolveAttributes(),
        );
    }

    /**
     * Merge the Blade attribute bag (wire:*, x-*, data-*, etc.) with explicit extra-attributes.
     * Extra-attributes take precedence.
     */
    protected function resolveAttributes(): array
    {
        $bladeAttributes = $this->attributes instanceof ComponentAttributeBag
            ? $this->attributes->getAttributes()
            : [];

        return array_replace($bladeAttributes, $this->extraAttributes);
    }
}
