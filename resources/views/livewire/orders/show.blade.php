<?php

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $this->order = $order->load(['items.product.category', 'items.supplierProfile', 'shippingAddress', 'statusHistory']);
    }
}; ?>

<div>
    <section class="pt-32 pb-16 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700 text-center">
        <div class="max-w-2xl mx-auto px-6">
            @if ($order->status === 'pending')
                <div
                    x-data="{ shown: false }"
                    x-init="setTimeout(() => shown = true, 100)"
                    :class="shown ? 'scale-100 opacity-100' : 'scale-50 opacity-0'"
                    class="mx-auto flex items-center justify-center w-20 h-20 rounded-full bg-gold-500 text-navy-900 transition-all duration-500 ease-out"
                >
                    <x-icon name="check" class="w-10 h-10" stroke-width="2.5" />
                </div>

                <h1 class="mt-6 font-heading text-2xl sm:text-3xl font-bold text-white">Order placed!</h1>
                <p class="mt-2 text-navy-200">Order <span class="font-semibold text-gold-500">{{ $order->order_number }}</span> is confirmed. We'll keep you updated as it moves.</p>
            @else
                <p class="text-xs font-semibold text-gold-500 uppercase tracking-wide">{{ $order->statusLabel() }}</p>
                <h1 class="mt-2 font-heading text-2xl sm:text-3xl font-bold text-white">Order {{ $order->order_number }}</h1>
                <p class="mt-2 text-navy-200">Placed {{ $order->placed_at->format('M j, Y') }} &middot; {{ $order->formattedTotal() }}</p>
            @endif
        </div>
    </section>

    <section class="py-12 bg-neutral-50">
        <div class="max-w-3xl mx-auto px-6 lg:px-8">
            <!-- Status timeline -->
            <div class="bg-white rounded-2xl border border-navy-100 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-heading font-bold text-navy-900">Delivery Status</h2>
                    <span class="text-xs font-semibold bg-gold-100 text-gold-700 px-3 py-1 rounded-full">{{ $order->statusLabel() }}</span>
                </div>

                <div class="mt-8 relative">
                    <div class="absolute top-4 left-4 right-4 h-0.5 bg-navy-100 hidden sm:block"></div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                        @php
                            $milestones = ['pending' => 'Order Placed', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
                            $order_progress = array_search($order->status, array_keys($milestones));
                            $order_progress = $order_progress === false ? 0 : $order_progress;
                        @endphp
                        @foreach ($milestones as $key => $label)
                            <div class="relative text-center">
                                <span @class([
                                    'relative z-10 mx-auto flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold',
                                    'bg-gold-500 text-navy-900' => $loop->index <= $order_progress,
                                    'bg-navy-100 text-navy-400' => $loop->index > $order_progress,
                                ])>
                                    @if ($loop->index < $order_progress || $order->status === 'delivered')
                                        <x-icon name="check" class="w-4 h-4" stroke-width="2.5" />
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </span>
                                <p class="mt-2 text-xs font-medium text-navy-600">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="mt-6 bg-white rounded-2xl border border-navy-100 p-6">
                <h2 class="font-heading font-bold text-navy-900">Items</h2>
                <ul class="mt-4 divide-y divide-navy-100">
                    @foreach ($order->items as $item)
                        <li class="flex items-center gap-4 py-4">
                            <span class="flex items-center justify-center w-12 h-12 rounded-lg bg-navy-50 text-navy-300 shrink-0">
                                <x-icon :name="$item->product->category->icon ?? 'cart'" class="w-5 h-5" />
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-navy-900 text-sm truncate">{{ $item->product_name }}</p>
                                <p class="text-xs text-navy-400">{{ $item->supplierProfile->business_name }} &middot; {{ $item->quantity }} &times; {{ number_format($item->unit_price) }} XAF</p>
                            </div>
                            <span class="font-semibold text-navy-900 text-sm">{{ number_format($item->total_price) }} XAF</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 pt-4 border-t border-navy-100 flex justify-between">
                    <span class="font-semibold text-navy-900">Total</span>
                    <span class="font-heading font-bold text-navy-900">{{ $order->formattedTotal() }}</span>
                </div>
            </div>

            <!-- Delivery + payment -->
            <div class="mt-6 grid sm:grid-cols-2 gap-6">
                @if ($order->shippingAddress)
                    <div class="bg-white rounded-2xl border border-navy-100 p-6">
                        <h3 class="font-heading font-semibold text-navy-900 text-sm flex items-center gap-2">
                            <x-icon name="map-pin" class="w-4 h-4" />
                            Delivery Address
                        </h3>
                        <p class="mt-2 text-sm text-navy-600">{{ $order->shippingAddress->recipient_name }} &middot; {{ $order->shippingAddress->phone }}</p>
                        <p class="text-sm text-navy-500">{{ $order->shippingAddress->street }}, {{ $order->shippingAddress->city }}, {{ $order->shippingAddress->country }}</p>
                    </div>
                @endif

                <div class="bg-white rounded-2xl border border-navy-100 p-6">
                    <h3 class="font-heading font-semibold text-navy-900 text-sm flex items-center gap-2">
                        <x-icon name="wallet" class="w-4 h-4" />
                        Payment
                    </h3>
                    <p class="mt-2 text-sm text-navy-600">{{ $order->payment_method === 'selbuildi_credit' ? 'Selbuildi Credit' : 'Cash / Pay on Delivery' }}</p>
                    <p class="text-xs text-navy-400 mt-1">Status: {{ ucfirst($order->payment_status) }}</p>
                </div>
            </div>

            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('shop.index') }}" wire:navigate class="flex-1">
                    <x-secondary-button class="w-full justify-center">Continue Shopping</x-secondary-button>
                </a>
                <a href="{{ route('dashboard') }}" wire:navigate class="flex-1">
                    <x-primary-button class="w-full justify-center">Go to Dashboard</x-primary-button>
                </a>
            </div>
        </div>
    </section>
</div>
