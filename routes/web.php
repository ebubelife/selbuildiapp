<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\SitemapController;
use App\Models\Category;
use App\Models\CreditTierSetting;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::post('deploy-hook', [DeployController::class, 'run'])->name('deploy-hook');

// Diagnostic only - confirms the mail transport is actually configured and
// reachable (SMTP host/port/credentials), independent of any notification
// class. Admin-gated so it can't be triggered or scraped for error detail
// by an anonymous visitor.
Route::get('test-email', function () {
    try {
        Mail::raw('This is a test email from Selbuildi, confirming the mail transport is working.', function ($message) {
            $message->to('ebubeemeka19@gmail.com')->subject('Selbuildi - Test Email');
        });

        return response("Sent successfully to ebubeemeka19@gmail.com.\n\nMailer: ".config('mail.default')."\nHost: ".config('mail.mailers.smtp.host'))
            ->header('Content-Type', 'text/plain');
    } catch (\Throwable $e) {
        return response("Failed to send.\n\nMailer: ".config('mail.default')."\nHost: ".config('mail.mailers.smtp.host')."\n\nError: {$e->getMessage()}\n\n{$e->getTraceAsString()}", 500)
            ->header('Content-Type', 'text/plain');
    }
})->middleware('auth:admin')->name('test-email');

// The provider segment is constrained to the three known drivers so a
// garbage value 404s at the routing layer instead of reaching
// PaymentGatewayManager::make() and throwing.
Route::middleware('auth')
    ->get('payments/{provider}/callback', PaymentCallbackController::class)
    ->whereIn('provider', ['flutterwave', 'paystack', 'fapshi'])
    ->name('payments.callback');

// Unauthenticated (the provider's own servers call this, not a logged-in
// browser) and CSRF-exempt (see bootstrap/app.php) - signature
// verification inside the controller is what actually authenticates it.
Route::post('payments/{provider}/webhook', PaymentWebhookController::class)
    ->whereIn('provider', ['flutterwave', 'paystack', 'fapshi'])
    ->name('payments.webhook');

Route::get('impersonate/{user}', [ImpersonationController::class, 'start'])
    ->middleware('auth:admin')
    ->name('impersonation.start');

Route::post('stop-impersonating', [ImpersonationController::class, 'stop'])
    ->middleware('auth:web')
    ->name('impersonation.stop');

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/', function () {
    return view('welcome', [
        'categories' => Category::whereNull('parent_id')->orderBy('sort_order')->get(),
        'featuredProducts' => Product::where('is_active', true)
            ->where('is_featured', true)
            ->with(['category', 'supplierProfile', 'images', 'inventories'])
            ->latest()
            ->limit(4)
            ->get(),
        'creditTiers' => CreditTierSetting::orderBy('id')->get(),
    ]);
})->name('home');

Volt::route('shop', 'shop.index')->name('shop.index');
Volt::route('shop/{product:slug}', 'shop.show')->name('shop.show');
Volt::route('suppliers/{supplier:slug}', 'suppliers.show')->name('suppliers.show');

Route::middleware('auth')->group(function () {
    Volt::route('addresses', 'addresses.index')->name('addresses.index');
    Volt::route('support/new', 'support.create')->name('support.create');
    Volt::route('checkout', 'checkout.index')->name('checkout.index');
    Volt::route('orders', 'orders.index')->name('orders.index');
    Volt::route('orders/{order}', 'orders.show')->name('orders.show');
    Volt::route('projects', 'projects.index')->name('projects.index');
    Volt::route('projects/{project}', 'projects.show')->name('projects.show');
    Volt::route('credit', 'credit.index')->name('credit.index');

    Volt::route('supplier/products', 'supplier.products.index')->name('supplier.products.index');
    Volt::route('supplier/products/create', 'supplier.products.form')->name('supplier.products.create');
    Volt::route('supplier/products/{product}/edit', 'supplier.products.form')->name('supplier.products.edit');
    Volt::route('supplier/orders', 'supplier.orders.index')->name('supplier.orders.index');
});

Route::get('dashboard', function () {
    $user = auth()->user();
    $supplier = $user->isSupplier() ? $user->supplierProfile : null;

    return view('dashboard', [
        'recentOrders' => $user->isSupplier()
            ? collect()
            : $user->orders()->latest('placed_at')->limit(5)->get(),
        'orderCount' => $user->isSupplier() ? 0 : $user->orders()->count(),
        'recentProjects' => $user->isContractor()
            ? $user->projects()->withCount('orders')->latest()->limit(3)->get()
            : collect(),
        'projectCount' => $user->isContractor() ? $user->projects()->count() : 0,
        'trustScore' => ! $user->isSupplier() ? $user->trustScore : null,
        'productCount' => $supplier?->isVerified() ? $supplier->products()->count() : 0,
        'pendingFulfillmentCount' => $supplier?->isVerified()
            ? \App\Models\OrderItem::where('supplier_profile_id', $supplier->id)
                ->whereIn('fulfillment_status', ['pending', 'confirmed', 'shipped'])
                ->count()
            : 0,
        'suggestedProducts' => $user->isSupplier()
            ? collect()
            : Product::where('is_active', true)
                ->with(['category', 'images'])
                ->inRandomOrder()
                ->limit(4)
                ->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
