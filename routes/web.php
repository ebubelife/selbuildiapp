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
});

Route::get('dashboard', function () {
    $user = auth()->user();

    return view('dashboard', [
        'recentOrders' => $user->isSupplier()
            ? collect()
            : $user->orders()->latest('placed_at')->limit(5)->get(),
        'orderCount' => $user->isSupplier() ? 0 : $user->orders()->count(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
