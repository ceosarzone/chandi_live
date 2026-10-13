<?php

namespace App\Services;

use Brick\Math\BigDecimal as D;
use Brick\Math\RoundingMode as R;

class Pricing
{
    const OUNCE = '31.1034768';

    const TOLA = '11.6638038';

    const SCALE = 12;

    public function grams(string $weight, string $unit): D
    {
        return D::of($weight)->multipliedBy(match ($unit) {
            'g' => '1','tola' => self::TOLA,'kg' => '1000','oz' => self::OUNCE,default => throw new \InvalidArgumentException('Invalid unit')
        });
    }

    public function perGram(string $xag, string $fx): D
    {
        return D::of($xag)->multipliedBy($fx)->dividedBy(self::OUNCE, self::SCALE, R::HALF_UP);
    }

    public function unitPrice(string $xag, string $fx, string $unit, string $currency = 'PKR'): string
    {
        if (! in_array($currency, ['USD', 'PKR', 'INR'], true)) {
            throw new \InvalidArgumentException('Invalid currency');
        }
        return (string) D::of($xag)->multipliedBy($currency === 'USD' ? '1' : $fx)
            ->multipliedBy($this->grams('1', $unit))->dividedBy(self::OUNCE, 12, R::HALF_UP);
    }

    public function calculate(array $v): array
    {
        $gross = $this->grams((string) $v['weight'], $v['unit'])->multipliedBy($v['quantity']);
        $fine = $gross->multipliedBy($v['purity'])->multipliedBy('0.01');
        $intrinsic = $fine->multipliedBy($v['benchmark']);
        $premium = $intrinsic->multipliedBy($v['premium'])->multipliedBy('0.01');
        $charges = D::of($v['charges'])->multipliedBy($v['quantity']);
        $subtotal = $intrinsic->plus($premium)->plus($charges);
        $tax = $subtotal->multipliedBy($v['tax'])->multipliedBy('0.01');
        $total = $subtotal->plus($tax);
        $results = ['gross_weight' => $gross, 'fine_weight' => $fine, 'intrinsic' => $intrinsic, 'premium' => $premium, 'charges' => $charges, 'tax' => $tax, 'total' => $total, 'per_gram' => $total->dividedBy($gross, 12, R::HALF_UP), 'per_tola' => $total->dividedBy($gross, 12, R::HALF_UP)->multipliedBy(self::TOLA)];

        return array_map(fn ($d) => (string) $d->toScale(8, R::HALF_UP), $results);
    }
}
