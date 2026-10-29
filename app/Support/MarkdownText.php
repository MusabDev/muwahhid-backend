<?php

namespace App\Support;

use Illuminate\Support\Str;

class MarkdownText
{
    /**
     * Strip inline markdown and footnote markers, leaving the plain text that
     * highlight offsets and search operate on.
     */
    public static function toPlain(string $markdown): string
    {
        $text = preg_replace('/\[\^[^\]]+\]/u', '', $markdown) ?? $markdown;
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/<[^>]+>/u', '', $text) ?? $text;
        $text = preg_replace('/(\*\*|__|~~|`)/u', '', $text) ?? $text;
        $text = preg_replace('/(?<![\p{L}\p{N}])[*_](?=\S)|(?<=\S)[*_](?![\p{L}\p{N}])/u', '', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}(#{1,6}\s+|>\s?|[-+*]\s+|\d+\.\s+)/mu', '', $text) ?? $text;
        $text = preg_replace('/\\\\([\\\\`*_{}\[\]()#+\-.!>~])/u', '$1', $text) ?? $text;

        return trim(preg_replace('/[ \t]+/u', ' ', $text) ?? $text);
    }

    /**
     * Render inline markdown to safe HTML (used by the PDF print template).
     */
    public static function toInlineHtml(string $markdown): string
    {
        $escaped = e($markdown, false);
        $escaped = preg_replace('/\[\^([^\]]+)\]/u', '<sup class="fn-ref">$1</sup>', $escaped) ?? $escaped;

        $html = Str::inlineMarkdown($escaped, [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        return strip_tags($html, '<strong><em><a><sup><code><del><br>');
    }
}
