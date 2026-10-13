<?php

namespace App\Livewire;

use App\Http\Requests\CalculatorRequest;
use App\Services\Analytics;
use App\Services\Market\MarketRepository;
use App\Services\Pricing;
use Livewire\Attributes\Url;
use Livewire\Component;

class PriceCalculator extends Component
{
    #[Url]
    public string $weight = '1';

    #[Url]
    public string $unit = 'tola';

    #[Url]
    public string $purity = '99.9';

    #[Url]
    public string $quantity = '1';

    #[Url]
    public string $benchmark = '';

    #[Url]
    public string $premium = '0';

    #[Url]
    public string $charges = '0';

    #[Url]
    public string $tax = '0';

    public ?array $result = null;

    public string $source = 'Manually entered benchmark (PKR per pure gram)';

    public function mount(MarketRepository $m): void
    {
        if ($this->benchmark === '') {
            if ($s = $m->latest()) {
                $this->benchmark = bcadd((string) app(Pricing::class)->perGram($s->price, $s->fx->rate), '0', 8);
                $this->source = ucfirst($m->state($s)).' observation · '.$s->provider->name.' · '.$s->observed_at->timezone('Asia/Karachi')->format('d M Y H:i').' PKT';
            }
        }
    }

    public function calculate(Pricing $p): void
    {
        $rules = (new CalculatorRequest)->rules();
        $v = $this->validate($rules);
        $this->result = $p->calculate($v);
        app(Analytics::class)->track('calculator_usage');
    }

    public function updated($name): void
    {
        $this->result = null;
        if ($name === 'benchmark') {
            $this->source = 'Manually entered benchmark (PKR per pure gram)';
        }
    }

    public function render()
    {
        return view('livewire.price-calculator');
    }
}
