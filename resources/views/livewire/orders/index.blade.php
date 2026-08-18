<?php

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            'orders' => Order::where('user_id', Auth::id())
                ->with('items')
                ->latest('placed_at')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">My Orders</h1>
            <p class="mt-1 text-navy-200 text-sm">Track and review everything you've ordered from Selbuildi.</p>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            @if ($orders->isEmpty())
                <div class="bg-white rounded-2xl border border-navy-100 p-12 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-navy-50 text-navy-300 mb-4">
                        <x-icon name="cart" class="w-7 h-7" />
                    </span>
                    <h3 class="font-heading text-lg font-semibold text-navy-900">No orders yet</h3>
                    <p class="mt-2 text-sm text-navy-500 max-w-sm mx-auto">Once you place an order, it'll show up here so you can track delivery and reorder easily.</p>
                    <a href="{{ route('shop.index') }}" wire:navigate>
                        <x-primary-button class="mt-6">Shop Materials</x-primary-button>
                    </a>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($orders as $order)
                        <a
                            href="{{ route('orders.show', $order) }}"
                            wire:navigate
                            wire:key="order-{{ $order->id }}"
                            class="group block bg-white rounded-2xl border border-navy-100 p-5 hover:border-gold-300 hover:shadow-brand transition-all duration-200"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-heading font-bold text-navy-900 text-sm">{{ $order->order_number }}</p>
                                    <p class="text-xs text-navy-400 mt-0.5">
                                        {{ $order->placed_at->format('M j, Y') }} &middot;
                                        {{ $order->items->sum('quantity') }} {{ Str::plural('item', $order->items->sum('quantity')) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span @class([
                                        'text-xs font-semibold px-3 py-1 rounded-full',
                                        'bg-gold-100 text-gold-700' => in_array($order->status, ['pending', 'confirmed', 'processing']),
                                        'bg-blue-100 text-blue-700' => in_array($order->status, ['shipped', 'out_for_delivery']),
                                        'bg-green-100 text-green-700' => $order->status === 'delivered',
                                        'bg-red-100 text-red-700' => in_array($order->status, ['cancelled', 'refunded']),
                                    ])>
                                        {{ $order->statusLabel() }}
                                    </span>
                                    <span class="font-heading font-bold text-navy-900 text-sm">{{ $order->formattedTotal() }}</span>
                                    <x-icon name="arrow-right" class="w-4 h-4 text-navy-300 group-hover:text-gold-500 group-hover:translate-x-0.5 transition-all duration-200 hidden sm:block" />
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
