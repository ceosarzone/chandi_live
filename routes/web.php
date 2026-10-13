<?php

use App\Http\Controllers\AdminController as A;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\DealerController as D;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController as P;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [P::class, 'home'])->name('home');
foreach (['live-silver-rates', 'silver-rate-in-usa', 'silver-rate-in-india', 'silver-rate-in-pakistan', 'silver-price-history'] as $path) {
    Route::get('/'.$path, [P::class, 'liveRates']);
}
Route::get('/market/rates', [P::class, 'countryRates'])->middleware('throttle:120,1');
foreach (['silver-rate-per-tola', 'silver-rate-per-gram', 'silver-rate-per-ounce', 'silver-calculator', 'gold-silver-ratio'] as $path) {
    Route::get('/'.$path, [P::class, 'rate']);
}
Route::get('/silver-rate/{city}', [P::class, 'rate']);
Route::get('/market/chart', [P::class, 'chart'])->middleware('throttle:120,1');
Route::post('/calculator', [P::class, 'calculate'])->middleware('throttle:calculator');
Route::get('/dealers/{slug?}', [P::class, 'dealers']);
Route::get('/products/{slug?}', [P::class, 'products']);
Route::get('/become-a-dealer', [P::class, 'become']);
Route::get('/blog/{slug?}', [P::class, 'blog']);
Route::get('/guides', [P::class, 'blog']);
foreach (['about', 'contact', 'advertise', 'privacy-policy', 'terms-and-conditions', 'disclaimer', 'dealer-policy'] as $slug) {
    Route::get('/'.$slug, fn () => app(P::class)->page($slug));
}
Route::post('/enquiries', [P::class, 'enquiry'])->middleware('throttle:enquiries');
Route::get('/whatsapp/{dealer}', [P::class, 'whatsapp'])->middleware('throttle:60,1');
Route::get('/contact-dealer/{dealer}', [P::class, 'contactDealer'])->middleware('throttle:60,1');
Route::post('/products/{product}/report', [P::class, 'report'])->middleware('throttle:enquiries');
Route::get('/sitemap.xml', [P::class, 'sitemap']);
Route::get('/health', [P::class, 'health']);
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /admin\nDisallow: /dealer\nDisallow: /account\nDisallow: /dashboard\nDisallow: /alerts\nDisallow: /whatsapp\nDisallow: /market\nSitemap: ".url('/sitemap.xml'))->header('Content-Type', 'text/plain'));
Route::get('/alerts/unsubscribe/{token}', [AlertController::class, 'unsubscribe']);
Route::post('/alerts/unsubscribe/{token}', [AlertController::class, 'confirmUnsubscribe']);
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return auth()->user()->canManage('admin') ? redirect('/admin') : (auth()->user()->dealer ? redirect('/dealer') : view('account.dashboard'));
    })->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dealer', [D::class, 'dashboard']);
    Route::get('/dealer/profile', [D::class, 'profile']);
    Route::post('/dealer/profile', [D::class, 'save'])->middleware('throttle:uploads');
    Route::get('/dealer/products/create', [D::class, 'productForm']);
    Route::get('/dealer/products/{product}/edit', [D::class, 'productForm']);
    Route::post('/dealer/products/{product?}', [D::class, 'saveProduct'])->middleware(['verified', 'throttle:uploads']);
    Route::delete('/dealer/products/{product}', [D::class, 'deleteProduct']);
    Route::post('/leads/{enquiry}', [D::class, 'lead']);
    Route::post('/dealer/quotes', [D::class, 'quote']);
    Route::post('/dealer/invoices', [D::class, 'invoice']);
    Route::get('/documents/{document}', [D::class, 'document']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts', [AlertController::class, 'store'])->middleware(['verified', 'throttle:20,1']);
    Route::delete('/alerts/{alert}', [AlertController::class, 'delete']);
    Route::get('/account/two-factor', [TwoFactorController::class, 'setup'])->middleware('password.confirm');
    Route::post('/account/two-factor', [TwoFactorController::class, 'enable'])->middleware('throttle:totp');
    Route::delete('/account/two-factor', [TwoFactorController::class, 'disable']);
    Route::get('/account/two-factor/challenge', [TwoFactorController::class, 'challenge']);
    Route::post('/account/two-factor/challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:totp');
});
Route::prefix('admin')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/', [A::class, 'index']);
    Route::get('/settings', [A::class, 'settings']);
    Route::post('/settings', [A::class, 'saveSettings']);
    Route::post('/provider-credential', [A::class, 'credential']);
    Route::post('/market-refresh', [A::class, 'refresh']);
    Route::post('/dealers/{dealer}/review', [A::class, 'review']);
    Route::post('/products/{product}/moderate', [A::class, 'moderate']);
    Route::post('/invoices/{invoice}/confirm', [A::class, 'confirm']);
    Route::post('/subscriptions/{subscription}/cancel', [A::class, 'cancel']);
    Route::get('/{resource}', [A::class, 'listing']);
    Route::get('/{resource}/create', [A::class, 'edit']);
    Route::get('/{resource}/{id}', [A::class, 'edit'])->whereNumber('id');
    Route::post('/{resource}/{id?}', [A::class, 'save'])->whereNumber('id');
});
require __DIR__.'/auth.php';

Route::get('/{slug}', [P::class, 'page'])->where('slug', '[a-z0-9-]+');
