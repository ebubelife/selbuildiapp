<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    /**
     * The browser lands here after the customer completes (or abandons)
     * hosted checkout. 'reference' is always ours - it's put on the
     * callback URL by initialize(), not parsed from whatever query string
     * shape a given provider happens to append, so this works identically
     * for all three. This is purely for UX (fast feedback); the webhook
     * is the authoritative confirmation in case the browser never gets
     * back here.
     */
    public function __invoke(Request $request, string $provider, PaymentVerificationService $verification): RedirectResponse
    {
        $reference = $request->query('reference');

        $payment = Payment::where('provider', $provider)->where('reference', $reference)->first();

        if (! $payment) {
            return redirect()->route('orders.index');
        }

        $verification->confirm($provider, $reference);

        return redirect()->route('orders.show', $payment->order);
    }
}
