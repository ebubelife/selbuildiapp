<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Component;

class QuickAddToCart extends Component
{
    public Product $product;

    public bool $justAdded = false;

    public function add(): void
    {
        app(CartService::class)->add($this->product, max(1, $this->product->min_order_qty));

        $this->justAdded = true;
        $this->dispatch('cart-updated');
    }

    public function render()
    {
        return view('livewire.quick-add-to-cart');
    }
}
