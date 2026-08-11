<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    /**
     * Resolve the cart for the current request: the authenticated user's
     * cart, or a session-backed guest cart, creating either as needed.
     */
    public function current(): Cart
    {
        if (Auth::check()) {
            return Cart::firstOrCreate(['user_id' => Auth::id()]);
        }

        return Cart::firstOrCreate(['session_id' => Session::getId(), 'user_id' => null]);
    }

    public function add(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $cart = $this->current();
        $unitPrice = $variant?->price ?? $product->price;

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if ($item) {
            $item->update(['quantity' => $item->quantity + $quantity]);

            return;
        }

        $cart->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => max($quantity, $product->min_order_qty),
            'unit_price_snapshot' => $unitPrice,
        ]);
    }

    /**
     * Merge a guest session cart into the user's cart after login, keeping
     * the user's own cart items and adding any the guest cart had that
     * aren't already there.
     *
     * $guestSessionId must be captured by the caller BEFORE Auth::attempt()/
     * Auth::login() runs - Laravel's SessionGuard::updateSession() calls
     * $session->regenerate(true) internally as soon as login succeeds, so by
     * the time this method could read Session::getId() itself, the guest ID
     * is already gone.
     */
    public function mergeSessionCartInto(User $user, string $guestSessionId): void
    {
        $sessionCart = Cart::where('session_id', $guestSessionId)
            ->whereNull('user_id')
            ->with('items')
            ->first();

        if (! $sessionCart || $sessionCart->items->isEmpty()) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

        foreach ($sessionCart->items as $guestItem) {
            $existing = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('product_variant_id', $guestItem->product_variant_id)
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $guestItem->quantity]);
            } else {
                $userCart->items()->create([
                    'product_id' => $guestItem->product_id,
                    'product_variant_id' => $guestItem->product_variant_id,
                    'quantity' => $guestItem->quantity,
                    'unit_price_snapshot' => $guestItem->unit_price_snapshot,
                ]);
            }
        }

        $sessionCart->delete();
    }
}
