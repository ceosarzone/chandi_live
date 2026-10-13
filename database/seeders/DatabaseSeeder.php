<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\MarketDataProvider;
use App\Models\MetalPurity;
use App\Models\MetalType;
use App\Models\Page;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin' => ['admin', 'dealers', 'market', 'market_references', 'products', 'enquiries', 'cms', 'commercial', 'settings', 'users'], 'platform_admin' => ['admin', 'dealers', 'products', 'enquiries', 'cms', 'commercial', 'settings'], 'data_operator' => ['admin', 'market', 'market_references'], 'dealer' => [], 'customer' => []] as $name => $permissions) {
            Role::firstOrCreate(['name' => $name], ['permissions' => $permissions]);
        }
        $pk = Country::firstOrCreate(['code' => 'PK'], ['name' => 'Pakistan']);
        foreach (['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad'] as $city) {
            City::firstOrCreate(['slug' => strtolower($city)], ['country_id' => $pk->id, 'name' => $city, 'description' => 'Discover approved physical silver dealers in '.$city.'. Nationwide converted benchmark; local shop quotations depend on purity, form and dealer premiums.']);
        }
        foreach (['Bars', 'Coins', 'Jewellery', 'Utensils', 'Collectibles', 'Bullion', 'Other'] as $name) {
            ProductCategory::firstOrCreate(['slug' => strtolower($name)], ['name' => $name]);
        }
        foreach (['XAG' => 'Silver', 'XAU' => 'Gold'] as $symbol => $name) {
            MetalType::firstOrCreate(['symbol' => $symbol], ['name' => $name]);
        }
        foreach (['999 fine' => '99.9', '925 sterling' => '92.5'] as $name => $percentage) {
            MetalPurity::firstOrCreate(['name' => $name], ['percentage' => $percentage]);
        }
        foreach ([['Free Dealer', 'free', '0', 5, false, false], ['Premium Dealer', 'premium', '2500', 50, true, false], ['Featured Placement', 'featured', '5000', 100, true, true]] as [$name,$slug,$price,$limit,$analytics,$sponsored]) {
            SubscriptionPlan::firstOrCreate(['slug' => $slug], ['name' => $name, 'monthly_price' => $price, 'product_limit' => $limit, 'advanced_analytics' => $analytics, 'sponsored' => $sponsored, 'description' => 'Monthly membership. Manual payment confirmation required for paid plans.']);
        }
        foreach (['gold_api' => 'Gold API', 'metals_dev' => 'Metals.Dev', 'demo' => 'Deterministic development fixture'] as $slug => $name) {
            MarketDataProvider::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
        $pages = [
            'about' => ['About CHANDI.LIVE', 'Know the Price. Find the Silver. CHANDI.LIVE helps visitors understand silver benchmarks and discover physical silver dealers in Pakistan. Currency-converted benchmarks are theoretical intrinsic values, not retail offers. Dealers handle transactions directly.'],
            'contact' => ['Contact the platform', 'Use the contact form to reach the operating team about listings, technical issues or business enquiries. Do not send identity documents through this public form.'],
            'advertise' => ['Advertise with clarity', 'Sponsored dealer placement is labelled Sponsored. Paid visibility does not establish authenticity or guarantee a transaction. Contact the team to discuss available placements.'],
            'privacy-policy' => ['Privacy policy', 'We collect account details, dealer applications, private verification documents and enquiries to operate this platform. Enquiry contact details are shared with the selected dealer and authorized platform staff. Analytics records use rotating hashed identifiers, with no raw IP stored. Contact the operator to request access, correction or deletion. Emails are opt-in where required. Hosting and mail providers process operational data. Operational legal review and retention settings must be finalized before public launch.'],
            'terms-and-conditions' => ['Terms and conditions', 'CHANDI.LIVE publishes market information and connects visitors with independent dealers. It does not collect purchase payments, hold silver, provide escrow or arrange delivery. Users must inspect goods and agree terms directly. Listings may be moderated or removed. Information can be delayed or unavailable. No investment return or dealer performance is guaranteed.'],
            'disclaimer' => ['Market and marketplace disclaimer', 'International spot prices, converted Pakistan benchmarks, dealer quotes and administrator references are different data categories. Converted prices exclude dealer margins, making costs, taxes and delivery. Calculator estimates are not offers or investment advice. A platform review badge describes documented platform checks only; it does not establish government verification or guarantee authenticity.'],
            'dealer-policy' => ['Dealer policy', 'Only physical silver products are permitted. Dealers must accurately state purity, weight, price, availability and condition. Applications require platform review. Verification documents remain private. Material changes may require re-review. Sponsored placements must be labelled. Misleading claims, abusive enquiries and prohibited listings can result in suspension.'],
        ];
        foreach ($pages as $slug => [$title,$body]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'published' => true, 'meta_description' => substr($body, 0, 155)]);
        }
    }
}
