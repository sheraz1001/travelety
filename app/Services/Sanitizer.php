<?php

namespace App\Services;

class Sanitizer
{
    /** Keep only safe structural HTML in generated or pasted content. */
    public static function html(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html);
        $allowed = '<h2><h3><h4><p><ul><ol><li><strong><b><em><i><blockquote><table><thead><tbody><tr><th><td><a><img><figure><figcaption><br><hr>';
        $html = strip_tags($html, $allowed);
        // drop event handlers, inline styles and javascript: urls
        $html = preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', '$1="#"', $html);

        return trim($html);
    }
}
