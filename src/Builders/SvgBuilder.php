<?php

namespace Laraextend\MediaToolkit\Builders;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Laraextend\MediaToolkit\Cache\ManifestCache;
use Laraextend\MediaToolkit\Exceptions\MediaBuilderException;

/**
 * Phase-4 builder — serve an SVG file as a public URL or as inline HTML.
 *
 * Two rendering modes:
 *   - Default  → renders <img src="..."> pointing to the copied SVG file.
 *   - ->inline() → reads and embeds the SVG content directly in the HTML.
 *     Inline scripts and event-handler attributes are filtered by default.
 *
 * Usage:
 *   Media::svg('resources/images/icon.svg')->html(alt: 'Icon', class: 'w-6');
 *   Media::svg('resources/images/icon.svg')->inline()->html(class: 'w-6 fill-current');
 *   $url = Media::svg('resources/images/logo.svg')->url();
 */
class SvgBuilder extends BaseBuilder
{
    private const ALLOWED_EXTENSIONS = ['svg'];

    private bool $isInline = false;
    private bool $sanitize = true;
    private ?int $width    = null;
    private ?int $height   = null;

    public function __construct(
        protected readonly string        $path,
        protected readonly ManifestCache $cache,
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  CHAIN METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Switch to inline-SVG rendering mode.
     *
     * Instead of an <img> tag, the SVG content is embedded directly in the HTML.
     * <script> blocks and on* event-handler attributes are removed by default.
     *
     * @param  bool  $sanitize  true (default) → strip <script> and on* attributes;
     *                          false → output the raw SVG content unchanged (developer responsibility)
     */
    public function inline(bool $sanitize = true): static
    {
        $this->isInline = true;
        $this->sanitize = $sanitize;

        return $this;
    }

    /**
     * Set the rendered width in pixels (applied to <img> or the <svg> element).
     */
    public function width(int $width): static
    {
        $this->width = $width > 0 ? $width : null;

        return $this;
    }

    /**
     * Set the rendered height in pixels (applied to <img> or the <svg> element).
     */
    public function height(int $height): static
    {
        $this->height = $height > 0 ? $height : null;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    //  OUTPUT METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Copy the SVG to the public output directory and return its public URL path.
     *
     * @throws MediaBuilderException  if the source file is missing or the path is invalid
     */
    public function url(): string
    {
        $this->assertSafeMediaPath($this->path, self::ALLOWED_EXTENSIONS);

        $sourcePath = base_path($this->path);

        if (! File::exists($sourcePath)) {
            throw new MediaBuilderException("SVG file not found: {$this->path}");
        }

        return $this->cache->copyOriginal($sourcePath);
    }

    /**
     * Return an HTML representation of the SVG.
     *
     * Default:      <img src="..." alt="..." ...>
     * With ->inline(): the SVG content is embedded directly in the HTML.
     *
     * @param  string       $alt         Alt text (used as <img alt> in default mode;
     *                                   sets aria-label + role="img" in inline mode)
     * @param  string       $class       CSS class(es)
     * @param  string|null  $id          HTML id attribute
     * @param  array        $attributes  Additional HTML attributes as key-value pairs
     *
     * @throws MediaBuilderException  if the source file is missing or the path is invalid
     */
    public function html(
        string  $alt        = '',
        string  $class      = '',
        ?string $id         = null,
        array   $attributes = [],
    ): string {
        if ($this->isInline) {
            return $this->renderInline($alt, $class, $id, $attributes);
        }

        $url = $this->url();

        $attrs = ['src' => asset($url), 'alt' => $alt];

        if ($this->width)  { $attrs['width']  = $this->width; }
        if ($this->height) { $attrs['height'] = $this->height; }
        if ($class)        { $attrs['class']  = $class; }
        if ($id)           { $attrs['id']     = $id; }

        $attrs = array_merge($attrs, $attributes);

        $html = '<img';
        foreach ($attrs as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($value === true || $value === '') {
                $html .= ' ' . e($key) . '=""';
                continue;
            }
            $html .= ' ' . e($key) . '="' . e($value) . '"';
        }
        $html .= '>';

        return $html;
    }

    // ─────────────────────────────────────────────────────────────
    //  INLINE SVG RENDERING
    // ─────────────────────────────────────────────────────────────

    /**
     * Read, optionally sanitize, inject attributes, and return inline SVG markup.
     */
    private function renderInline(string $alt, string $class, ?string $id, array $attributes): string
    {
        $this->assertSafeMediaPath($this->path, self::ALLOWED_EXTENSIONS);

        $sourcePath = base_path($this->path);

        if (! File::exists($sourcePath)) {
            throw new MediaBuilderException("SVG file not found: {$this->path}");
        }

        $content = File::get($sourcePath);

        $dom = new DOMDocument();
        @$dom->loadXML($content, LIBXML_NOERROR | LIBXML_NOWARNING);

        $svgElement = $dom->documentElement;

        if (! $svgElement instanceof DOMElement) {
            throw new MediaBuilderException("Could not parse SVG file: {$this->path}");
        }

        if ($this->sanitize) {
            $this->sanitizeDom($dom);
        }

        if ($class)        { $svgElement->setAttribute('class',  $class); }
        if ($id)           { $svgElement->setAttribute('id',     $id); }
        if ($this->width)  { $svgElement->setAttribute('width',  (string) $this->width); }
        if ($this->height) { $svgElement->setAttribute('height', (string) $this->height); }

        if ($alt !== '') {
            $svgElement->setAttribute('aria-label', $alt);
            $svgElement->setAttribute('role',       'img');
        }

        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $svgElement->setAttribute((string) $key, (string) $value);
            }
        }

        return $dom->saveXML($svgElement) ?: $content;
    }

    /**
     * Remove <script> elements and on* event-handler attributes from the DOM.
     * Also strips javascript: protocol from href/src/xlink:href attributes.
     */
    private function sanitizeDom(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        // Remove all <script> elements (any namespace)
        $scripts = $xpath->query('//*[local-name()="script"]');
        if ($scripts !== false) {
            foreach (iterator_to_array($scripts) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Strip on* event handlers and javascript: protocol from all elements
        $allElements = $xpath->query('//*');
        if ($allElements === false) {
            return;
        }

        foreach ($allElements as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $attrsToRemove = [];

            foreach ($element->attributes as $attr) {
                $name  = strtolower($attr->name);
                $value = strtolower(trim($attr->value));

                if (str_starts_with($name, 'on')) {
                    $attrsToRemove[] = $attr->name;
                    continue;
                }

                if (
                    in_array($name, ['href', 'src', 'xlink:href'], true)
                    && str_starts_with($value, 'javascript:')
                ) {
                    $attrsToRemove[] = $attr->name;
                }
            }

            foreach ($attrsToRemove as $attrName) {
                $element->removeAttribute($attrName);
            }
        }
    }
}
