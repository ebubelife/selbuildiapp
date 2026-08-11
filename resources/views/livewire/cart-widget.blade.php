<div>
    <!-- Cart trigger -->
    <button
        type="button"
        wire:click="openDrawer"
        :class="scrolled ? 'text-navy-700 hover:bg-navy-50' : 'text-white hover:bg-white/10'"
        class="relative flex items-center justify-center w-10 h-10 rounded-full transition-colors duration-150"
        aria-label="Open cart"
    >
        <x-icon name="cart" class="w-5 h-5" />
        @if ($cart->totalQuantity() > 0)
            <span class="absolute -top-1 -right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-gold-500 text-navy-900 text-[10px] font-bold">
                {{ $cart->totalQuantity() }}
            </span>
        @endif
    </button>

    <!-- Drawer: teleported to <body> so it isn't trapped inside the nav's
         backdrop-blur containing block, which would clip position:fixed
         descendants to the nav bar's own (short) height once scrolled. -->
    <template x-teleport="body">
        <div
            x-show="$wire.open"
            x-cloak
            class="fixed inset-0 z-50"
            style="display: none;"
        >
            <div
                x-show="$wire.open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 bg-navy-900/50"
                wire:click="closeDrawer"
            ></div>

            <div
                x-show="$wire.open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="absolute right-0 top-0 h-full w-full sm:w-[420px] bg-white shadow-2xl flex flex-col"
            >
                <div class="flex items-center justify-between px-6 py-5 border-b border-navy-100">
                    <h2 class="font-heading font-bold text-lg text-navy-900">Your Cart</h2>
                    <button type="button" wire:click="closeDrawer" class="text-navy-400 hover:text-navy-700 transition-colors">
                        <x-icon name="close" class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    @if ($cart->items->isEmpty())
                        <div class="flex flex-col items-center justify-center h-full text-center px-8">
                            <span class="flex items-center justify-center w-16 h-16 rounded-full bg-navy-50 text-navy-300 mb-4">
                                <x-icon name="cart" class="w-7 h-7" />
                            </span>
                            <h3 class="font-heading font-semibold text-navy-900">Your cart is empty</h3>
                            <p class="mt-2 text-sm text-navy-500">Browse materials and add items to get started.</p>
                            <a href="{{ route('shop.index') }}" wire:navigate wire:click="closeDrawer">
                                <x-secondary-button class="mt-6">Shop Materials</x-secondary-button>
                            </a>
                        </div>
                    @else
                        <ul class="divide-y divide-navy-100">
                            @foreach ($cart->items as $item)
                                <li wire:key="cart-item-{{ $item->id }}" class="flex gap-4 px-6 py-5">
                                    <span class="flex items-center justify-center w-16 h-16 rounded-xl bg-navy-50 text-navy-300 shrink-0">
                                        <x-icon :name="$item->product->category->icon ?? 'cart'" class="w-7 h-7" stroke-width="1.3" />
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-navy-900 text-sm truncate">{{ $item->product->name }}</h3>
                                        <p class="text-xs text-navy-400 mt-0.5">{{ number_format($item->unit_price_snapshot) }} XAF / {{ $item->product->unit }}</p>

                                        <div class="mt-3 flex items-center justify-between">
                                            <div class="flex items-center border border-navy-200 rounded-lg">
                                                <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="w-7 h-7 flex items-center justify-center text-navy-500 hover:text-navy-900 text-sm">&minus;</button>
                                                <span class="w-8 text-center text-sm font-semibold text-navy-900">{{ $item->quantity }}</span>
                                                <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="w-7 h-7 flex items-center justify-center text-navy-500 hover:text-navy-900 text-sm">&plus;</button>
                                            </div>
                                            <button type="button" wire:click="removeItem({{ $item->id }})" class="text-xs text-navy-400 hover:text-red-600 transition-colors">Remove</button>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if ($cart->items->isNotEmpty())
                    <div class="border-t border-navy-100 px-6 py-5">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm text-navy-500">Subtotal</span>
                            <span class="font-heading font-bold text-navy-900">{{ number_format($cart->subtotal()) }} XAF</span>
                        </div>
                        <a href="{{ route('checkout.index') }}" wire:navigate>
                            <x-primary-button class="w-full justify-center py-3">Checkout</x-primary-button>
                        </a>
                        <button type="button" wire:click="closeDrawer" class="w-full text-center text-sm text-navy-500 hover:text-navy-700 mt-3 transition-colors">
                            Continue Shopping
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
