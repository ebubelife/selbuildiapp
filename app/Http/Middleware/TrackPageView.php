<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    /**
     * Records a hit per real page load - GET requests only, and excluding
     * the admin panel (its own traffic isn't "site visits"), Livewire's
     * internal polling/upload endpoints, and the deploy/health-check
     * routes, which would otherwise dwarf real visit counts.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('get') && ! $request->is('s/admin/build*', 'livewire/*', 'deploy-hook', 'up')) {
            PageView::create([
                'path' => '/'.ltrim($request->path(), '/'),
                'user_id' => Auth::id(),
                'session_id' => $request->session()->getId(),
                'ip_address' => $request->ip(),
                'referrer' => $request->headers->get('referer'),
            ]);
        }

        return $response;
    }
}
