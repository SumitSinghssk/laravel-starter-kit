<?php

namespace App\Support;

class SvgGuard
{
    private const FORBIDDEN_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'handler', 'listener', 'set', 'animate'];

    public static function problem(string $contents): ?string
    {
        if (stripos($contents, '<svg') === false) {
            return 'This file is not an SVG image.';
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
            return 'SVG files with a DOCTYPE or entities are not allowed.';
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($contents, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || strtolower($dom->documentElement?->localName ?? '') !== 'svg') {
            return 'This SVG file could not be read. It may be damaged.';
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (in_array(strtolower($element->localName), self::FORBIDDEN_ELEMENTS, true)) {
                return "SVG files may not contain <{$element->localName}> elements.";
            }

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->localName);
                $value = strtolower(preg_replace('/[\s\x00-\x1f]+/', '', $attribute->value));

                if (str_starts_with($name, 'on')) {
                    return 'SVG files may not contain event handlers (such as onload).';
                }

                if (str_contains($value, 'javascript:') || str_contains($value, 'vbscript:') || str_contains($value, 'data:text/html')) {
                    return 'SVG files may not contain script links.';
                }

                if ($name === 'href' && $value !== '' && ! str_starts_with($value, '#') && ! str_starts_with($value, 'data:image/')) {
                    return 'SVG files may not link to other files or websites.';
                }
            }
        }

        return null;
    }
}
