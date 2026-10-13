<?php

namespace App\Services\Market;

use Carbon\CarbonImmutable;

class DemoProvider implements Provider
{
    public function latest(): array
    {
        if (app()->environment('production') || ! config('market.demo')) {
            throw new \RuntimeException('Demo mode is disabled.');
        }

return ['xag' => '30.125000', 'fx' => '278.500000', 'fx_inr' => '84.000000', 'gold' => '2400.000000', 'observed_at' => CarbonImmutable::parse('2026-10-01T12:00:00Z'), 'is_demo' => true];
    }

    public function historical(string $from, string $to): array
    {
        $v = $this->latest();
        $rows = [];
        for ($i = 29; $i >= 0; $i--) {
            $r = $v;
            $r['resolution'] = 'daily';
            $r['observed_at'] = $v['observed_at']->subDays($i);
            $r['xag'] = bcadd('29.4', bcdiv((string) ((29 - $i) * 25), '1000', 6), 6);
            $rows[] = $r;
        }

return $rows;
    }
}
