<?php

namespace App\Support;

class ArabicNormalizer
{
    /**
     * Normalize Arabic text for searching: strip diacritics and tatweel, unify letter variants.
     * Non-Arabic text passes through (lowercased).
     */
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text) ?? $text;

        $text = strtr($text, [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ى' => 'ي',
            'ئ' => 'ي',
            'ؤ' => 'و',
            'ة' => 'ه',
        ]);

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower(trim($text));
    }
}
