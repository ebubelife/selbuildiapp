<?php

namespace App\Providers;

use App\Services\CartService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so CartService's per-instance cart memoization (see
        // CartService::current()) holds across every component that
        // resolves it within one request - the nav cart widget, quick-add
        // buttons, and the page itself all otherwise re-querying the same
        // cart independently.
        $this->app->singleton(CartService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
