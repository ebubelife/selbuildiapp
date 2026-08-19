<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Logs an admin into a shopper's account on the storefront's 'web'
     * guard, for support/debugging - the admin's own session on the
     * separate 'admin' guard is untouched throughout, which is what makes
     * returning to it afterward (stop()) trivial.
     */
    public function start(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'Admin accounts cannot be impersonated.');

        session(['impersonator_id' => Auth::guard('admin')->id()]);

        Auth::guard('web')->login($user);

        return redirect()->route('dashboard');
    }

    /**
     * Ends impersonation and drops the admin back at the panel - no
     * re-login needed, since the 'admin' guard's session was never
     * touched by start() above.
     */
    public function stop(): RedirectResponse
    {
        Auth::guard('web')->logout();

        session()->forget('impersonator_id');

        return redirect('/s/admin/build');
    }
}
