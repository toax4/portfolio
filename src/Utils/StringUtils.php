<?php

namespace App\Utils;

use Symfony\Component\String\Slugger\AsciiSlugger;

class StringUtils
{
    public static function slugify(string $text): string
    {
        $slugger = new AsciiSlugger();

        return $slugger->slug($text)->lower()->toString();
    }
}
