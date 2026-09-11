<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * Resolves which currency the current visitor sees prices in and pays in.
 * Resolution order: an explicit choice already made this session -> a
 * logged-in user's saved preference -> their registered country's
 * currency -> XAF. Once resolved, the choice is written back to the
 * session so it stays stable for the rest of the visit even if the
 * underlying account data is ambiguous.
 */
class CurrencyContext
{
    private const SESSION_KEY = 'currency_code';

    private ?Currency $resolved = null;

    public function current(): Currency
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        $code = Session::get(self::SESSION_KEY) ?? $this->deriveDefaultCode();

        $currency = Currency::where('code', $code)->where('is_supported', true)->first()
            ?? $this->xaf();

        Session::put(self::SESSION_KEY, $currency->code);

        return $this->resolved = $currency;
    }

    /**
     * Switches the active currency for the rest of this session, and - if
     * logged in - saves it as the account's standing preference too, so
     * it's still their default currency next time they visit from
     * another device.
     */
    public function switchTo(string $code, ?User $user = null): Currency
    {
        $currency = Currency::where('code', $code)->where('is_supported', true)->firstOrFail();

        Session::put(self::SESSION_KEY, $currency->code);
        $this->resolved = $currency;

        if ($user) {
            $user->update(['preferred_currency' => $currency->code]);
        }

        return $currency;
    }

    private function deriveDefaultCode(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'XAF';
        }

        if ($user->preferred_currency && Currency::where('code', $user->preferred_currency)->where('is_supported', true)->exists()) {
            return $user->preferred_currency;
        }

        $countryCurrency = \App\Models\Country::where('name', $user->country)->value('currency_code');

        if ($countryCurrency && Currency::where('code', $countryCurrency)->where('is_supported', true)->exists()) {
            return $countryCurrency;
        }

        return 'XAF';
    }

    private function xaf(): Currency
    {
        return Currency::where('code', 'XAF')->firstOrFail();
    }
}
