<?php

namespace Tests\Unit;

use App\Services\Pricing;
use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    public function test_exact_weight_conventions(): void
    {
        $p = new Pricing;
        $this->assertSame('31.1034768', (string) $p->grams('1', 'oz'));
        $this->assertSame('11.6638038', (string) $p->grams('1', 'tola'));
        $this->assertSame('1000', (string) $p->grams('1', 'kg'));
        $this->assertSame('1', (string) $p->grams('1', 'g'));
    }

    public function test_xag_fx_and_tola_conversion(): void
    {
        $p = new Pricing;
        $this->assertSame('100.000000000000', (string) $p->perGram('31.1034768', '100'));
        $this->assertSame('1166.380380000000', $p->unitPrice('31.1034768', '100', 'tola'));
        $this->assertSame('31.103476800000', $p->unitPrice('31.1034768', '100', 'oz', 'USD'));
    }

    public function test_purity_premium_charges_fees_and_quantity(): void
    {
        $r = (new Pricing)->calculate(['weight' => '10', 'unit' => 'g', 'purity' => '92.5', 'quantity' => '2', 'benchmark' => '100', 'premium' => '10', 'charges' => '50', 'tax' => '5']);
        $this->assertSame('20.00000000', $r['gross_weight']);
        $this->assertSame('18.50000000', $r['fine_weight']);
        $this->assertSame('1850.00000000', $r['intrinsic']);
        $this->assertSame('185.00000000', $r['premium']);
        $this->assertSame('100.00000000', $r['charges']);
        $this->assertSame('106.75000000', $r['tax']);
        $this->assertSame('2241.75000000', $r['total']);
    }

    public function test_decimal_precision_and_discount(): void
    {
        $r = (new Pricing)->calculate(['weight' => '0.1', 'unit' => 'g', 'purity' => '99.9', 'quantity' => '3', 'benchmark' => '0.2', 'premium' => '-10', 'charges' => '0', 'tax' => '0']);
        $this->assertSame('0.29970000', $r['fine_weight']);
        $this->assertSame('0.05994000', $r['intrinsic']);
        $this->assertSame('0.05394600', $r['total']);
    }

    public function test_invalid_unit_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Pricing)->grams('1', 'ordinary_ounce');
    }

    public function test_display_formatting_retains_large_decimal_precision(): void
    {
        $this->assertSame('1,234,567,890,123,456,789.13', Decimal::format('1234567890123456789.125'));
        $this->assertSame('-185.00', Decimal::format('-185'));
        $this->assertSame('0.100000', Decimal::format('0.1', 6));
    }
}
