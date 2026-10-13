<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealerRequest;
use App\Http\Requests\ProductRequest;
use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Models\DealerDocument;
use App\Models\DealerPriceQuote;
use App\Models\DealerProfile;
use App\Models\Enquiry;
use App\Models\EnquiryStatusHistory;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Services\Analytics;
use App\Services\Audit;
use App\Services\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DealerController extends Controller
{
    public function dashboard(Request $r)
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $d = $r->user()->dealer;
        $stats = [];
        if ($d) {
            foreach (['dealer_view', 'product_view', 'whatsapp_click', 'enquiry_submission'] as $event) {
                $stats[$event] = AnalyticsEvent::where('dealer_profile_id', $d->id)->where('event', $event)->whereBetween('created_at', [$r->date('from') ?? now()->subDays(30), ($r->date('to') ?? now())->endOfDay()])->count();
            }
        }

        return view('dealer.dashboard', ['dealer' => $d, 'stats' => $stats, 'products' => $d?->products()->with('images')->latest()->paginate(10), 'enquiries' => $d?->status === 'approved' ? Enquiry::where('dealer_profile_id', $d->id)->latest()->paginate(10, ['*'], 'leads_page') : null, 'invoices' => $d ? Invoice::with('plan')->where('dealer_profile_id', $d->id)->latest()->get() : collect(), 'quotes' => $d ? DealerPriceQuote::where('dealer_profile_id', $d->id)->latest()->limit(10)->get() : collect(), 'activity' => $d?->currentPlan()?->advanced_analytics ? AnalyticsEvent::where('dealer_profile_id', $d->id)->whereBetween('created_at', [$r->date('from') ?? now()->subDays(30), ($r->date('to') ?? now())->endOfDay()])->latest()->paginate(20, ['event', 'product_id', 'created_at'], 'activity_page') : null, 'plans' => SubscriptionPlan::where('active', true)->get()]);
    }

    public function profile(Request $r)
    {
        return view('dealer.profile', ['dealer' => $r->user()->dealer, 'cities' => City::where('active', true)->orderBy('name')->get()]);
    }

    public function save(DealerRequest $r, ImageStorage $images)
    {
        $d = $r->user()->dealer;
        if ($d) {
            Gate::authorize('update', $d);
        }
        $v = $r->safe()->except(['logo', 'photos', 'document', 'document_label', 'intent']);
        $v['social_urls'] = array_values(array_filter($v['social_urls'] ?? []));
        $firstSubmission = $r->intent === 'submit' && ! $d?->submitted_at;
        $v['status'] = $r->intent === 'submit' ? 'submitted' : 'draft';
        $v['verified'] = false;
        if ($r->intent === 'submit') {
            $v['submitted_at'] = now();
        }
        DB::transaction(function () use ($r, $d, $v, $images, $firstSubmission) {
            if (! $d) {
                $v['user_id'] = $r->user()->id;
                $v['slug'] = Str::slug($v['business_name']).'-'.Str::lower(Str::random(6));
                $d = DealerProfile::create($v);
                if ($r->user()->role?->name === 'customer') {
                    $r->user()->forceFill(['role_id' => Role::where('name', 'dealer')->value('id')])->save();
                }

            } else {
                $d->update($v);
            }
            if ($firstSubmission) {
                app(Analytics::class)->track('dealer_registration', $d->id);
            }
            if ($r->hasFile('logo')) {
                $d->update(['logo_path' => $images->store($r->file('logo'), 'dealers')]);
            }if ($r->hasFile('photos')) {
                $d->update(['photos' => array_map(fn ($f) => $images->store($f, 'dealers'), $r->file('photos'))]);
            }
            if ($r->hasFile('document')) {
                $file = $r->file('document');
                DealerDocument::create(['dealer_profile_id' => $d->id, 'label' => $r->document_label, 'path' => $file->store('verification', 'local'), 'mime' => $file->getMimeType()]);
            }
            Audit::record('dealer.profile_saved', $d, ['status' => $v['status']]);
        });

        return redirect('/dealer')->with('status', 'Application saved. Submission requires an administrator review.');
    }

    public function productForm(Request $r, ?Product $product = null)
    {
        if ($product) {
            Gate::authorize('update', $product);
        }abort_unless($r->user()->dealer?->status === 'approved', 403);

        return view('dealer.product-form', ['product' => $product, 'categories' => ProductCategory::where('active', true)->get()]);
    }

    public function saveProduct(ProductRequest $r, ImageStorage $images, ?Product $product = null)
    {
        if ($product) {
            Gate::authorize('update', $product);
        }
        $dealer = $r->user()->dealer;
        DB::transaction(function () use ($r, $images, $product, $dealer) {
            $d = DealerProfile::lockForUpdate()->findOrFail($dealer->id);
            if (! $product && $d->products()->count() >= ($d->currentPlan()?->product_limit ?? 0)) {
                throw ValidationException::withMessages(['title' => 'Your plan listing limit has been reached.']);
            }
            $v = $r->safe()->except('images');
            $v['status'] = 'pending';
            $v['published_at'] = null;
            if (! $product) {
                $v['dealer_profile_id'] = $d->id;
                $v['slug'] = Str::slug($v['title']).'-'.Str::lower(Str::random(6));
                $product = Product::create($v);
            } else {
                $product->update($v);
            }if ($r->hasFile('images')) {
                $oldPaths = $product->images->pluck('path')->all();
                $product->images()->delete();
                DB::afterCommit(fn () => Storage::disk('public')->delete($oldPaths));
                foreach ($r->file('images') as $i => $file) {
                    ProductImage::create(['product_id' => $product->id, 'path' => $images->store($file, 'products'), 'alt' => $product->title, 'position' => $i]);
                }
            }
            Audit::record('product.saved', $product, ['status' => 'pending']);
        });

        return redirect('/dealer')->with('status', 'Product saved and sent for moderation.');
    }

    public function deleteProduct(Request $r, Product $product)
    {
        Gate::authorize('delete', $product);
        $product->delete();
        Audit::record('product.deleted', $product);

        return back()->with('status', 'Listing removed.');
    }

    public function lead(Request $r, Enquiry $enquiry)
    {
        Gate::authorize('update', $enquiry);
        $v = $r->validate(['status' => 'required|in:new,contacted,completed,lost,spam', 'notes' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($enquiry, $v) {
            $enquiry->update(['status' => $v['status']]);
            EnquiryStatusHistory::create(['enquiry_id' => $enquiry->id, 'user_id' => auth()->id()] + $v);
            Audit::record('enquiry.status', $enquiry, ['status' => $v['status']]);
        });

        return back()->with('status', 'Enquiry status updated.');
    }

    public function quote(Request $r)
    {
        $d = $r->user()->dealer;
        abort_unless($d?->status === 'approved', 403);
        $v = $r->validate(['pkr_per_gram' => 'required|numeric|gt:0|max:100000000', 'purity' => 'required|numeric|gt:0|max:100', 'source' => 'required|string|max:255', 'observed_at' => 'required|date|before_or_equal:now', 'expires_at' => 'required|date|after:now', 'notes' => 'nullable|string|max:1000']);
        $q = DealerPriceQuote::create($v + ['dealer_profile_id' => $d->id, 'entered_by' => $r->user()->id, 'classification' => 'dealer_quote']);
        Audit::record('dealer.quote', $q, $v);

        return back()->with('status', 'Dealer quote recorded separately from the benchmark.');
    }

    public function invoice(Request $r)
    {
        $d = $r->user()->dealer;
        abort_unless($d?->status === 'approved', 403);
        $v = $r->validate(['subscription_plan_id' => 'required|exists:subscription_plans,id']);
        $plan = SubscriptionPlan::where('active', true)->findOrFail($v['subscription_plan_id']);
        abort_if($plan->slug === 'free', 422);
        $invoice = Invoice::create(['dealer_profile_id' => $d->id, 'subscription_plan_id' => $plan->id, 'number' => 'CL-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)), 'amount' => $plan->monthly_price, 'due_at' => now()->addDays(7)]);
        Audit::record('invoice.created', $invoice);

        return back()->with('status', 'Invoice created. The operator will confirm payment before activation.');
    }

    public function document(Request $r, DealerDocument $document)
    {
        abort_unless($r->user()->canManage('dealers') || $document->dealer->user_id === $r->user()->id, 403);
        if ($r->user()->canManage('dealers')) {
            abort_unless($r->user()->hasVerifiedEmail(), 403);
            if ($r->user()->totp_enabled && $r->session()->get('totp_user') !== $r->user()->id) {
                return redirect('/account/two-factor/challenge');
            }
        }
        Audit::record('document.viewed', $document);

        return Storage::disk('local')->download($document->path, 'verification-'.$document->id.'.'.pathinfo($document->path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
