<?php

namespace App\Twig\Filter;

use App\Utils\StringUtils;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class StringFilter extends AbstractExtension
{
    public function __construct(private StringUtils $stringUtils)
    {
    } // si service

    public function getFilters(): array
    {
        return [
            new TwigFilter('slugify', [$this, 'slugify']),
        ];
    }

    public function slugify(string $value): string
    {
        return StringUtils::slugify($value);
    }
}
