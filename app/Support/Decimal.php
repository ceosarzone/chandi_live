<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Decimal
{
    public static function format(string $amount, int $places = 2): string
    {
        $rounded = (string) BigDecimal::of($amount)->toScale($places, RoundingMode::HALF_UP);
        [$whole,$fraction] = array_pad(explode('.', $rounded, 2), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return $places === 0 ? $whole : $whole.'.'.$fraction;
    }
}
