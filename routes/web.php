<?php

use App\Http\Controllers\DeployController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::post('deploy-hook', [DeployController::class, 'run'])->name('deploy-hook');

Route::get('/', function () {
    return view('welcome', [
        'categories' => Category::whereNull('parent_id')->orderBy('sort_order')->get(),
        'featuredProducts' => Product::where('is_active', true)
            ->where('is_featured', true)
            ->with('category')
            ->latest()
            ->limit(4)
            ->get(),
    ]);
})->name('home');

Volt::route('shop', 'shop.index')->name('shop.index');
Volt::route('shop/{product:slug}', 'shop.show')->name('shop.show');
Volt::route('suppliers/{supplier:slug}', 'suppliers.show')->name('suppliers.show');

Route::middleware('auth')->group(function () {
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
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
