<?php

namespace App\Http\Controllers;

use App\Actions\SubmitEnquiry;
use App\Http\Requests\CalculatorRequest;
use App\Http\Requests\EnquiryRequest;
use App\Models\BlogPost;
use App\Models\City;
use App\Models\ContentReport;
use App\Models\DealerPriceQuote;
use App\Models\DealerProfile;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PslQuote;
use App\Models\SponsoredPlacement;
use App\Models\SubscriptionPlan;
use App\Services\Analytics;
use App\Services\Market\MarketRepository;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function sponsoredIds()
    {
        return SponsoredPlacement::where('active', true)->where('starts_at', '<=', now())->where('expires_at', '>', now())->whereHas('subscription', fn ($q) => $q->where('status', 'active'))->pluck('dealer_profile_id');
    }

    public function home(MarketRepository $market)
    {
        return $this->liveRates(request(), $market);
    }

    public function liveRates(Request $request, MarketRepository $market)
    {
        if (config('chandi.marketplace_enabled') && $request->is('silver-rate-in-pakistan')) {
            return $this->rate($request, $market);
        }
        $selected = match ($request->path()) {
            'silver-rate-in-pakistan' => 'PKR', 'silver-rate-in-india' => 'INR', default => 'USD',
        };

        return view('public.home', ['countries' => $market->countries(), 'selected' => $selected]);
    }

    public function countryRates(MarketRepository $market)
    {
        return response()->view('components.country-rates', ['countries' => $market->countries()])
            ->header('Cache-Control', 'no-store');
    }

    public function rate(Request $r, MarketRepository $market, ?string $city = null)
    {
        $city = $city ? City::where('active', true)->where('slug', $city)->firstOrFail() : null;
        $titles = ['silver-rate-in-pakistan' => 'Silver rate in Pakistan', 'silver-rate-per-tola' => 'Silver rate per tola', 'silver-rate-per-gram' => 'Silver rate per gram', 'silver-rate-per-ounce' => 'Silver rate per troy ounce', 'silver-price-history' => 'Silver price history', 'silver-calculator' => 'Silver value calculator', 'gold-silver-ratio' => 'Gold / silver ratio'];
        $info = config('rate_pages.'.$r->path(), config('rate_pages.silver-rate-in-pakistan'));
        if ($r->is('silver-calculator')) {
            $r->validate(array_fill_keys(['weight', 'unit', 'purity', 'quantity', 'benchmark', 'premium', 'charges', 'tax'], 'sometimes|nullable|string|max:64'));
        }
        $title = $city ? 'Silver benchmark & dealers in '.$city->name : ($titles[$r->path()] ?? 'Silver market');

        return view('public.rate', ['description' => $city ? 'Silver benchmark context and approved physical silver dealer discovery in '.$city->name.'. Nationwide benchmark; local shop quotations vary.' : $info['description'], 'info' => $info, 'title' => $title, 'city' => $city, 'rate' => $market->latest(), 'state' => $market->state($market->latest()), 'cityDealers' => $city ? DealerProfile::approved()->with('city')->where('city_id', $city->id)->limit(4)->get() : collect(), 'sponsoredIds' => $this->sponsoredIds(), 'references' => DealerPriceQuote::where('classification', 'admin_reference')->where('expires_at', '>', now())->latest()->limit(5)->get(), 'calculator' => $r->is('silver-calculator'), 'ratio' => $r->is('gold-silver-ratio'), 'noindex' => $city && ! DealerProfile::approved()->where('city_id', $city->id)->exists()]);
    }

    public function chart(Request $r, MarketRepository $m)
    {
        $v = $r->validate(['period' => 'nullable|in:24h,7d,30d,90d,1y,max', 'currency' => 'nullable|in:USD,PKR,INR,PSL', 'unit' => 'nullable|in:g,10g,tola,oz,kg']);
        $period = $v['period'] ?? '30d';
        $currency = $v['currency'] ?? 'USD';
        $unit = $v['unit'] ?? match ($currency) {
            'PKR' => 'tola', 'INR' => 'kg', default => 'oz'
        };
        if ($currency === 'PSL') {
            $days = match ($period) {
                '24h' => 1,'7d' => 7,'30d' => 30,'90d' => 90,'1y' => 365,default => 36500
            };
            $rows = PslQuote::where('observed_at', '>=', now()->subDays($days))->orderBy('observed_at')->get()
                ->map(fn ($q) => ['at' => $q->observed_at->toIso8601String(), 'value' => $q->price, 'source' => $q->source, 'resolution' => 'timestamp', 'series' => 'recorded'])->all();
            $note = 'GoldToday published PSL quotes, collected since 13 Oct 2026. The source labels 10 tola, but the feed unit is unconfirmed. No unit conversions or earlier PSL history are inferred.';
        } else {
            $rows = $m->history($period, $currency, $unit);
            $note = 'Daily reference history: Currency API with same-day FX. Recorded spot: Gold API with dated FX. Sources can differ. Missing dates are left as gaps; ranges and changes describe available observations, not exchange OHLC.';
        }
        $values = array_values(array_filter($rows, fn ($p) => $p['value'] !== null));
        $change = null;
        if (count($values) > 1) {
            $first = $values[0];
            $last = $values[count($values) - 1];
            $absolute = bcsub($last['value'], $first['value'], 12);
            $change = ['absolute' => $absolute, 'percent' => bcmul(bcdiv($absolute, $first['value'], 12), '100', 8), 'from' => $first['at'], 'to' => $last['at']];
        }

        return response()->json(['points' => $rows, 'currency' => $currency === 'PSL' ? 'PKR' : $currency, 'unit' => $currency === 'PSL' ? 'published quote' : $unit,
            'change' => $change, 'state' => $m->state($m->latest()), 'limited' => count($values) < 2, 'note' => $note,
            'low' => $values ? min(array_column($values, 'value')) : null, 'high' => $values ? max(array_column($values, 'value')) : null]);
    }

    public function calculate(CalculatorRequest $r, Pricing $p, Analytics $a)
    {
        $a->track('calculator_usage');

        return response()->json(['inputs' => $r->validated(), 'results' => $p->calculate($r->validated())]);
    }

    public function dealers(Request $r, ?string $slug = null)
    {
        $city = $slug ? City::where('slug', $slug)->where('active', true)->first() : null;
        if ($slug && ! $city) {
            $dealer = DealerProfile::approved()->with('city')->where('slug', $slug)->firstOrFail();
            app(Analytics::class)->track('dealer_view', $dealer->id);
            if ($r->query('placement') === 'sponsored' && $this->sponsoredIds()->contains($dealer->id)) {
                app(Analytics::class)->track('sponsored_placement_click', $dealer->id);
            }

            return view('public.dealer', ['dealer' => $dealer, 'products' => $dealer->products()->published()->with('images')->paginate(12), 'quotes' => DealerPriceQuote::where('dealer_profile_id', $dealer->id)->where('expires_at', '>', now())->latest()->limit(3)->get()]);
        }
        $filters = $r->validate(['q' => 'nullable|string|max:100', 'city' => 'nullable|exists:cities,slug', 'verified' => 'nullable|in:1', 'category' => 'nullable|exists:product_categories,slug', 'purity' => 'nullable|numeric|min:0|max:100', 'availability' => 'nullable|in:in_stock,out_of_stock,on_request']);
        $q = DealerProfile::approved()->with('city');
        if ($city) {
            $q->where('city_id', $city->id);
        }if ($r->filled('city')) {
            $q->whereHas('city', fn ($c) => $c->where('slug', $filters['city']));
        }if ($r->filled('q')) {
            $q->where('business_name', 'like', '%'.$filters['q'].'%');
        }if ($r->boolean('verified')) {
            $q->where('verified', true);
        }if ($r->filled('category') || $r->filled('purity') || $r->filled('availability')) {
            $q->whereHas('products', function ($p) use ($r) {
                $p->published();
                if ($r->filled('category')) {
                    $p->whereHas('category', fn ($c) => $c->where('slug', $r->category));
                }if ($r->filled('purity')) {
                    $p->where('purity', '>=', $r->purity);
                }if ($r->filled('availability')) {
                    $p->where('availability', $r->availability);
                }
            });
        }

        return view('public.directory', ['title' => $city ? 'Silver dealers in '.$city->name : 'Find your silver dealer', 'city' => $city, 'items' => $q->orderBy('business_name')->paginate(12)->withQueryString(), 'cities' => City::where('active', true)->get(), 'categories' => ProductCategory::where('active', true)->get(), 'mode' => 'dealers', 'sponsoredIds' => $this->sponsoredIds(), 'noindex' => count($r->except('page')) > 0 || ($city && ! DealerProfile::approved()->where('city_id', $city->id)->exists())]);
    }

    public function products(Request $r, ?string $slug = null)
    {
        if ($slug) {
            $p = Product::published()->with(['dealer.city', 'images', 'category'])->where('slug', $slug)->firstOrFail();
            app(Analytics::class)->track('product_view', $p->dealer_profile_id, $p->id);

            return view('public.product', ['product' => $p]);
        }
        $v = $r->validate(['q' => 'nullable|string|max:100', 'dealer' => 'nullable|string|max:100', 'city' => 'nullable|exists:cities,slug', 'category' => 'nullable|exists:product_categories,slug', 'purity' => 'nullable|numeric|min:0|max:100', 'availability' => 'nullable|in:in_stock,out_of_stock,on_request', 'min_price' => 'nullable|numeric|min:0', 'max_price' => 'nullable|numeric|min:0', 'min_weight' => 'nullable|numeric|min:0', 'max_weight' => 'nullable|numeric|min:0', 'verified' => 'nullable|in:1', 'currency' => 'nullable|in:PKR,USD']);
        $q = Product::published()->with(['dealer.city', 'images', 'category']);
        if ($r->filled('q')) {
            $q->where('title', 'like', '%'.$r->q.'%');
        }if ($r->filled('dealer')) {
            $q->whereHas('dealer', fn ($d) => $d->where('business_name', 'like', '%'.$r->dealer.'%'));
        }if ($r->filled('city')) {
            $q->whereHas('dealer.city', fn ($c) => $c->where('slug', $r->city));
        }if ($r->filled('category')) {
            $q->whereHas('category', fn ($c) => $c->where('slug', $r->category));
        }if ($r->filled('purity')) {
            $q->where('purity', '>=', $r->purity);
        }if ($r->filled('availability')) {
            $q->where('availability', $r->availability);
        }if ($r->boolean('verified')) {
            $q->whereHas('dealer', fn ($d) => $d->where('verified', true));
        }foreach (['min_price' => '>=', 'max_price' => '<='] as $k => $op) {
            if ($r->filled($k)) {
                $q->where('currency', $r->input('currency', 'PKR'))->where('price', $op, $r->$k);
            }
        }
        // Weight filters are normalized to grams at query time, using exact decimal constants.
        foreach (['min_weight' => '>=', 'max_weight' => '<='] as $k => $op) {
            if ($r->filled($k)) {
                $q->whereRaw("(weight * CASE weight_unit WHEN 'tola' THEN 11.6638038 WHEN 'oz' THEN 31.1034768 WHEN 'kg' THEN 1000 ELSE 1 END) $op ?", [$r->$k]);
            }
        }

        return view('public.directory', ['title' => 'Physical silver, from real dealers', 'items' => $q->latest()->paginate(12)->withQueryString(), 'cities' => City::where('active', true)->get(), 'categories' => ProductCategory::where('active', true)->get(), 'mode' => 'products', 'city' => null, 'noindex' => count($r->except('page')) > 0]);
    }

    public function become()
    {
        return view('public.become', ['plans' => SubscriptionPlan::where('active', true)->get()]);
    }

    public function page(string $slug)
    {
        $page = Page::where('published', true)->where('slug', $slug)->firstOrFail();

        return view('public.page', compact('page'));
    }

    public function blog(Request $r, ?string $slug = null)
    {
        if ($slug) {
            $post = BlogPost::with('author')->where('published_at', '<=', now())->where('slug', $slug)->firstOrFail();

            return view('public.article', compact('post'));
        }

        return view('public.blog', ['posts' => BlogPost::where('published_at', '<=', now())->when($r->is('guides'), fn ($q) => $q->where('type', 'guide'))->latest('published_at')->paginate(12), 'title' => $r->is('guides') ? 'The silver library' : 'Market notes & insights']);
    }

    public function enquiry(EnquiryRequest $r, SubmitEnquiry $a)
    {
        $a->handle($r->validated());

        return back()->with('status', 'Enquiry received. Your contact details are shared only with the selected dealer and authorized staff.');
    }

    public function whatsapp(Request $r, DealerProfile $dealer)
    {
        abort_unless($dealer->status === 'approved' && $dealer->user->active && $dealer->whatsapp, 404);
        $p = $r->filled('product') ? Product::published()->where('dealer_profile_id', $dealer->id)->findOrFail($r->product) : null;
        app(Analytics::class)->track('whatsapp_click', $dealer->id, $p?->id);

        return redirect()->away('https://wa.me/'.ltrim($dealer->whatsapp, '+').'?text='.rawurlencode('Hello, I found '.($p ? $p->title : $dealer->business_name).' on CHANDI.LIVE. Please share availability, purity and your current quotation.'));
    }

    public function contactDealer(DealerProfile $dealer)
    {
        abort_unless($dealer->status === 'approved' && $dealer->user->active, 404);
        app(Analytics::class)->track('contact_click', $dealer->id);

        return redirect()->away('tel:'.$dealer->phone);
    }

    public function report(Request $r, Product $product)
    {
        abort_unless(Product::published()->whereKey($product->id)->exists(), 404);
        $v = $r->validate(['reason' => 'required|string|min:10|max:1000', 'email' => 'nullable|email|max:255', 'consent' => 'accepted']);
        ContentReport::create(['product_id' => $product->id, 'reason' => $v['reason'], 'email' => $v['email'] ?? null]);

        return back()->with('status', 'Report received for moderation.');
    }

    public function sitemap()
    {
        if (! config('chandi.marketplace_enabled')) {
            $paths = ['', 'live-silver-rates', 'silver-rate-in-usa', 'silver-rate-in-pakistan', 'silver-rate-in-india', 'silver-price-history', 'silver-calculator', 'privacy-policy', 'terms-and-conditions', 'disclaimer'];

            return response()->view('public.sitemap', compact('paths'))->header('Content-Type', 'application/xml');
        }
        $paths = ['', 'live-silver-rates', 'silver-rate-in-usa', 'silver-rate-in-india', 'silver-rate-in-pakistan', 'silver-rate-per-tola', 'silver-rate-per-gram', 'silver-rate-per-ounce', 'silver-price-history', 'silver-calculator', 'gold-silver-ratio', 'dealers', 'products', 'become-a-dealer', 'blog', 'guides'];
        foreach (Page::where('published', true)->pluck('slug') as $p) {
            $paths[] = $p;
        }foreach (DealerProfile::approved()->pluck('slug') as $p) {
            $paths[] = 'dealers/'.$p;
        }foreach (Product::published()->pluck('slug') as $p) {
            $paths[] = 'products/'.$p;
        }foreach (BlogPost::where('published_at', '<=', now())->pluck('slug') as $p) {
            $paths[] = 'blog/'.$p;
        }foreach (City::where('active', true)->whereIn('id', DealerProfile::approved()->select('city_id'))->pluck('slug') as $p) {
            $paths[] = 'silver-rate/'.$p;
            $paths[] = 'dealers/'.$p;
        }

        return response()->view('public.sitemap', compact('paths'))->header('Content-Type', 'application/xml');
    }

    public function health()
    {
        try {
            $version = DB::selectOne('SELECT VERSION() AS version');
            if (! str_contains($version->version, 'MariaDB')) {
                return response()->json(['status' => 'unsupported_database'], 503);
            }

            return response()->json(['status' => 'ok', 'database' => 'mariadb', 'market' => app(MarketRepository::class)->state(app(MarketRepository::class)->latest())]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
