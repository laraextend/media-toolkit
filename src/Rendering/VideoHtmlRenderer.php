<?php

namespace Laraextend\MediaToolkit\Rendering;

class VideoHtmlRenderer
{
    /**
     * Build a <video> HTML element.
     *
     * When $sources is non-empty the element uses <source> children for
     * multi-format / multi-codec output instead of a bare src attribute.
     *
     * @param  string       $url         Public URL path of the video file (used when $sources is empty)
     * @param  string       $class       CSS class(es) for the <video> element
     * @param  string       $preload     Preload hint: 'none' | 'metadata' | 'auto'
     * @param  bool         $controls    Render the native browser controls bar
     * @param  bool         $autoplay    Start playback automatically
     * @param  bool         $muted       Mute the audio track
     * @param  bool         $loop        Loop the video when it ends
     * @param  bool         $playsinline Prevent fullscreen on iOS Safari
     * @param  string|null  $posterUrl   Fully-qualified URL of the poster image (optional)
     * @param  int|null     $width       Display width in pixels
     * @param  int|null     $height      Display height in pixels
     * @param  string|null  $id          HTML id attribute
     * @param  array        $attributes  Additional HTML attributes as key-value pairs
     * @param  array        $sources     Resolved source entries: [['url' => string, 'type' => string|null], …]
     */
    public function buildVideoTag(
        string  $url,
        string  $class,
        string  $preload,
        bool    $controls,
        bool    $autoplay,
        bool    $muted,
        bool    $loop,
        bool    $playsinline,
        ?string $posterUrl,
        ?int    $width,
        ?int    $height,
        ?string $id,
        array   $attributes,
        array   $sources = [],
    ): string {
        $multiSource = count($sources) > 0;

        $attrs = [];

        // In multi-source mode the src attribute is omitted — browsers pick
        // the first <source> they can decode.
        if (! $multiSource) {
            $attrs['src'] = asset($url);
        }

        if ($controls)    { $attrs['controls']    = true; }
        if ($autoplay)    { $attrs['autoplay']    = true; }
        if ($muted)       { $attrs['muted']       = true; }
        if ($loop)        { $attrs['loop']        = true; }
        if ($playsinline) { $attrs['playsinline'] = true; }

        $attrs['preload'] = $preload;

        if ($posterUrl) { $attrs['poster'] = $posterUrl; }
        if ($width)     { $attrs['width']  = $width; }
        if ($height)    { $attrs['height'] = $height; }
        if ($class)     { $attrs['class']  = $class; }
        if ($id)        { $attrs['id']     = $id; }

        $attrs = array_merge($attrs, $attributes);

        $html = '<video';
        foreach ($attrs as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($value === true) {
                $html .= ' ' . e($key);
                continue;
            }
            $html .= ' ' . e($key) . '="' . e($value) . '"';
        }
        $html .= '>';

        if ($multiSource) {
            foreach ($sources as $source) {
                $html .= "\n    <source src=\"" . e($source['url']) . '"';
                if ($source['type'] !== null) {
                    $html .= ' type="' . e($source['type']) . '"';
                }
                $html .= '>';
            }
            $html .= "\n    Your browser does not support the video tag.\n</video>";
        } else {
            $html .= "\n    Your browser does not support the video tag.\n</video>";
        }

        return $html;
    }
}
