<?php

namespace App\Livewire;

use App\Models\CartItem;
use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartWidget extends Component
{
    public bool $open = false;

    #[On('cart-updated')]
    public function refresh(): void
    {
        // Re-render is enough - the cart is loaded fresh in render() below.
    }

    public function openDrawer(): void
    {
        $this->open = true;
    }

    public function closeDrawer(): void
    {
        $this->open = false;
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = CartItem::find($itemId);

        if (! $item || $item->cart_id !== app(CartService::class)->current()->id) {
            return;
        }

        if ($quantity < 1) {
            $item->delete();

            return;
        }

        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(int $itemId): void
    {
        $item = CartItem::find($itemId);

        if ($item && $item->cart_id === app(CartService::class)->current()->id) {
            $item->delete();
        }
    }

    public function render()
    {
        $cart = app(CartService::class)->current()->load('items.product.category', 'items.productVariant');

        return view('livewire.cart-widget', ['cart' => $cart]);
    }
}
