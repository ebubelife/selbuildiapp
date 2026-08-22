<?php

namespace App\Providers;

use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
        // Log Viewer authenticates against the 'admin' guard specifically,
        // not the storefront's 'web' guard its own route middleware starts
        // a session for - an admin's login has nothing to do with 'web',
        // and a customer/contractor/supplier session on 'web' should never
        // satisfy this regardless.
        Gate::define('viewLogViewer', function () {
            $adminUser = Auth::guard('admin')->user();

            // TEMPORARY diagnostic logging - remove once the production
            // 403 on /logs is root-caused. Confirms exactly what this
            // gate sees at the moment Log Viewer checks it, since the
            // data (role) and the deployed code have both already been
            // verified correct in isolation.
            Log::info('viewLogViewer gate check', [
                'admin_guard_user_id' => $adminUser?->id,
                'admin_guard_user_email' => $adminUser?->email,
                'admin_guard_user_role' => $adminUser?->role,
                'web_guard_user_id' => Auth::guard('web')->id(),
                'session_id' => session()->getId(),
            ]);

            return $adminUser?->isAdmin() ?? false;
        });
    }
}
