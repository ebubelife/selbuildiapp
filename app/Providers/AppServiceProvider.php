<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\CurrencyContext;
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

        // Same reasoning: CurrencyContext resolves the active currency
        // once (session lookup + a query) and every <x-price>, the
        // switcher, and checkout within one request should share that
        // single resolution rather than each re-deriving it.
        $this->app->singleton(CurrencyContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
