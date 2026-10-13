<?php

namespace App\Http\Controllers;

use App\Actions\ActivateSubscription;
use App\Actions\ReviewDealer;
use App\Http\Requests\AdminRecordRequest;
use App\Jobs\RefreshMarket;
use App\Models\AnalyticsEvent;
use App\Models\DealerProfile;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\MarketDataProvider;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\SponsoredPlacement;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\AdminResources;
use App\Services\Analytics;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $r->date('from') ?? now()->subDays(30);
        $to = ($r->date('to') ?? now())->endOfDay();
        $range = fn ($q) => $q->whereBetween('created_at', [$from, $to]);
        $stats = ['Recorded daily visitor sessions' => $range(AnalyticsEvent::where('event', 'platform_visit'))->count(), 'Dealer applications' => DealerProfile::whereIn('status', ['submitted', 'under_review', 'information_requested'])->count(), 'Approved dealers' => DealerProfile::approved()->count(), 'Suspended dealers' => DealerProfile::where('status', 'suspended')->count(), 'Published listings' => Product::published()->count(), 'Submitted enquiries' => $range(Enquiry::query())->count(), 'Active subscriptions' => Subscription::where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())->count(), 'Confirmed payments (PKR)' => $range(Payment::query())->sum('amount'), 'Expiring in 7 days' => Subscription::where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays(7)])->count()];
        $stats = array_filter($stats, fn ($value, $key) => match ($key) {
            'Confirmed payments (PKR)','Active subscriptions','Expiring in 7 days' => $r->user()->canManage('commercial'),'Submitted enquiries','Recorded daily visitor sessions' => $r->user()->canManage('enquiries'),'Published listings' => $r->user()->canManage('products'),default => $r->user()->canManage('dealers')
        }, ARRAY_FILTER_USE_BOTH);
        $funnel = $range(AnalyticsEvent::query())->select('event', DB::raw('COUNT(*) as total'))->groupBy('event')->pluck('total', 'event');
        if (! $r->user()->canManage('enquiries')) {
            $funnel = collect();
        }

        return view('admin.dashboard', ['stats' => $stats, 'funnel' => $funnel, 'providers' => MarketDataProvider::all(), 'resources' => AdminResources::all()]);
    }

    private function definition(string $resource): array
    {
        $d = AdminResources::get($resource);
        Gate::authorize('manage', $d[1]);

        return $d;
    }

    public function listing(Request $r, string $resource)
    {
        [$model,$permission,$columns,$rules,$search] = $this->definition($resource);
        $v = $r->validate(['q' => 'nullable|string|max:150', 'sort' => 'nullable|in:'.implode(',', ['id', 'created_at', ...$columns]), 'direction' => 'nullable|in:asc,desc', 'status' => 'nullable|string|max:50']);
        $q = $model::query();
        if ($r->filled('q') && $search) {
            $q->where(function ($q) use ($r, $search) {
                foreach ($search as $c) {
                    $q->orWhere($c, 'like', '%'.$r->q.'%');
                }
            });
        }if ($r->filled('status') && in_array('status', $columns)) {
            $q->where('status', $r->status);
        }
        $records = $q->orderBy($r->input('sort', 'id'), $r->input('direction', 'desc'))->paginate(20)->withQueryString();

        return view('admin.list', compact('resource', 'columns', 'rules', 'records'));
    }

    public function edit(string $resource, ?int $id = null)
    {
        [$model,$permission,$columns,$rules] = $this->definition($resource);
        $record = $id ? $model::findOrFail($id) : null;
        if (! $id && in_array($resource, ['providers', 'placements', 'reports', 'users'])) {
            abort(404);
        }

        return view('admin.record', compact('resource', 'record', 'rules', 'columns'));
    }

    public function save(AdminRecordRequest $r, string $resource, ?int $id = null)
    {
        [$model] = $this->definition($resource);
        $v = $r->validated();
        $record = $id ? $model::findOrFail($id) : null;
        if (! $id && in_array($resource, ['providers', 'placements', 'reports', 'users'])) {
            abort(404);
        }
        if ($resource === 'users') {
            abort_unless($r->user()->role?->name === 'super_admin', 403);
            if ($record->id === $r->user()->id && (! $v['active'] || $v['role_id'] != $record->role_id)) {
                throw ValidationException::withMessages(['active' => 'You cannot remove your own administrator access.']);
            }
        }
        if ($resource === 'plans' && $record?->slug === 'free' && ($v['slug'] !== 'free' || bccomp($v['monthly_price'], '0', 8) !== 0 || ! $v['active'])) {
            throw ValidationException::withMessages(['slug' => 'The free fallback plan must remain active and free.']);
        }
        if ($resource === 'articles' && ! $record) {
            $v['author_id'] = $r->user()->id;
        }
        if ($resource === 'quotes') {
            abort_if($record, 403, 'Reference quotations are append-only.');
            $v['entered_by'] = $r->user()->id;
            $v['classification'] = 'admin_reference';
        }
        if ($resource === 'invoices') {
            abort_if($record, 403, 'Invoices are immutable after creation.');
            $plan = SubscriptionPlan::where('active', true)->findOrFail($v['subscription_plan_id']);
            $v += ['number' => 'CL-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)), 'amount' => $plan->monthly_price, 'due_at' => now()->addDays(7)];
        }
        DB::transaction(function () use (&$record, $model, $v, $resource) {
            if ($record) {
                $resource === 'users' ? $record->forceFill($v)->save() : $record->update($v);
            } else {
                $record = $model::create($v);
            }if ($resource === 'users' && ! $v['active'] && $record->dealer) {
                $record->dealer->update(['status' => 'suspended', 'verified' => false]);
            }
            Audit::record('admin.'.$resource.'.saved', $record, $v);
        });

        return redirect('/admin/'.$resource)->with('status', 'Record saved.');
    }

    public function review(Request $r, DealerProfile $dealer, ReviewDealer $action)
    {
        $v = $r->validate(['decision' => 'required|in:under_review,information_requested,approved,rejected,suspended', 'notes' => 'required|string|min:10|max:3000', 'verified' => 'nullable|boolean']);
        $action->handle($dealer, $v);

        return back()->with('status', 'Review recorded; dealer notification queued.');
    }

    public function moderate(Request $r, Product $product)
    {
        Gate::authorize('manage', 'products');
        $v = $r->validate(['status' => 'required|in:published,rejected,hidden', 'notes' => 'required|string|min:10|max:1000']);
        if ($v['status'] === 'published') {
            abort_unless($product->dealer->status === 'approved', 422, 'Dealer must be approved.');
        }
        $product->update(['status' => $v['status'], 'published_at' => $v['status'] === 'published' ? now() : null]);
        Audit::record('product.moderated', $product, $v);

        return back()->with('status', 'Moderation saved.');
    }

    public function confirm(Request $r, Invoice $invoice, ActivateSubscription $a)
    {
        $v = $r->validate(['amount' => 'required|numeric|min:0', 'reference' => 'required|string|max:150|unique:payments,reference', 'notes' => 'required|string|min:10|max:1000', 'months' => 'required|in:1', 'confirmed' => 'accepted']);
        $s = $a->handle($invoice, $v);
        app(Analytics::class)->track('subscription_activation', $s->dealer_profile_id);

        return back()->with('status', 'Offline payment confirmed and membership activated.');
    }

    public function cancel(Request $r, Subscription $subscription)
    {
        Gate::authorize('manage', 'commercial');
        $r->validate(['confirmed' => 'accepted']);
        $subscription->update(['status' => 'cancelled']);
        SponsoredPlacement::where('subscription_id', $subscription->id)->update(['active' => false]);
        Audit::record('subscription.cancelled', $subscription);

        return back()->with('status', 'Membership cancelled. Payment history retained.');
    }

    public function refresh(Request $r)
    {
        Gate::authorize('manage', 'market');
        RefreshMarket::dispatch();
        Audit::record('market.refresh_requested');

        return back()->with('status', 'Central market refresh queued; quota and refresh interval remain enforced.');
    }

    public function settings()
    {
        Gate::authorize('manage', 'settings');

        return view('admin.settings', ['settings' => SiteSetting::where('key', '!=', 'metals_dev_api_key')->pluck('value', 'key')]);
    }

    public function saveSettings(Request $r)
    {
        Gate::authorize('manage', 'settings');
        $v = $r->validate(['contact_email' => 'nullable|email|max:255', 'contact_phone' => 'nullable|string|max:30', 'brand_tagline' => 'nullable|string|max:150', 'search_console' => 'nullable|regex:/^[a-zA-Z0-9_-]{1,100}$/', 'analytics_id' => 'nullable|regex:/^G-[A-Z0-9]{1,20}$/', 'home_intro' => 'nullable|string|max:500', 'legal_operator' => 'nullable|string|max:150']);
        foreach ($v as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('site.public_settings.'.app()->environment());
        Audit::record('settings.updated');

        return back()->with('status', 'Platform settings saved.');
    }

    public function credential(Request $r)
    {
        Gate::authorize('manage', 'market');
        $v = $r->validate(['api_key' => 'required|string|max:255', 'license_confirmed' => 'accepted']);
        SiteSetting::updateOrCreate(['key' => 'metals_dev_api_key'], ['value' => Crypt::encryptString($v['api_key'])]);
        SiteSetting::updateOrCreate(['key' => 'market_license_confirmed'], ['value' => '1']);
        Audit::record('provider.credential_updated');

        return back()->with('status', 'Credential encrypted at rest. Select MARKET_PROVIDER=metals_dev in the server environment.');
    }
}
