<?php

namespace Laraextend\MediaToolkit\Rendering;

class AudioHtmlRenderer
{
    /**
     * Build an <audio> HTML element.
     *
     * @param  string       $url         Public URL path of the audio file
     * @param  string       $class       CSS class(es) for the <audio> element
     * @param  string       $preload     Preload hint: 'none' | 'metadata' | 'auto'
     * @param  bool         $controls    Render the native browser controls bar
     * @param  bool         $autoplay    Start playback automatically
     * @param  bool         $muted       Mute the audio
     * @param  bool         $loop        Loop the audio when it ends
     * @param  string|null  $id          HTML id attribute
     * @param  array        $attributes  Additional HTML attributes as key-value pairs
     */
    public function buildAudioTag(
        string  $url,
        string  $class,
        string  $preload,
        bool    $controls,
        bool    $autoplay,
        bool    $muted,
        bool    $loop,
        ?string $id,
        array   $attributes,
    ): string {
        $attrs = ['src' => asset($url)];

        if ($controls) { $attrs['controls'] = true; }
        if ($autoplay) { $attrs['autoplay'] = true; }
        if ($muted)    { $attrs['muted']    = true; }
        if ($loop)     { $attrs['loop']     = true; }

        $attrs['preload'] = $preload;

        if ($class) { $attrs['class'] = $class; }
        if ($id)    { $attrs['id']    = $id; }

        $attrs = array_merge($attrs, $attributes);

        $html = '<audio';
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
        $html .= ">\n    Your browser does not support the audio tag.\n</audio>";

        return $html;
    }
}
