<?php

declare(strict_types=1);

namespace App\Service\Import;

final class SlugHelper
{
    public static function slugify(string $text): string
    {
        $text = \Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($text) ?? $text;
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }

    /** UID court non-devinable pour /slug-uid, ex: "9f3a2b". */
    public static function shortUid(): string
    {
        return bin2hex(random_bytes(4));
    }
}
