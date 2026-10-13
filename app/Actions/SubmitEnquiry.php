<?php

namespace App\Actions;

use App\Jobs\SendPlatformMail;
use App\Models\DealerProfile;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\Analytics;

class SubmitEnquiry
{
    public function handle(array $v): Enquiry
    {
        $dealer = isset($v['dealer_profile_id']) ? DealerProfile::approved()->findOrFail($v['dealer_profile_id']) : null;
        $product = isset($v['product_id']) ? Product::published()->findOrFail($v['product_id']) : null;
        if ($product) {
            abort_unless($dealer && $product->dealer_profile_id === $dealer->id, 422, 'Selected product does not belong to this dealer.');
        }
        $key = hash_hmac('sha256', strtolower($v['email']).'|'.$dealer?->id.'|'.$product?->id.'|'.trim($v['message']).'|'.now()->toDateString(), config('app.key'));
        $e = Enquiry::firstOrCreate(['dedup_key' => $key], ['dealer_profile_id' => $dealer?->id, 'product_id' => $product?->id, 'buyer_name' => $v['buyer_name'], 'email' => $v['email'], 'phone' => $v['phone'] ?? null, 'message' => $v['message'], 'consented_at' => now()]);
        if ($e->wasRecentlyCreated) {
            $email = $dealer?->user->email ?? SiteSetting::where('key', 'contact_email')->value('value') ?? config('market.admin_email');
            if ($email) {
                SendPlatformMail::dispatch($email, 'New CHANDI.LIVE enquiry', 'A buyer enquiry is waiting in your protected dashboard.', url($dealer ? '/dealer' : '/admin/enquiries'))->afterCommit();
            }app(Analytics::class)->track('enquiry_submission', $dealer?->id, $product?->id);
        }

        return $e;
    }
}
