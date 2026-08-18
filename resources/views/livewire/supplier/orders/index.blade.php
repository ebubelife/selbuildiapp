<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderFulfillmentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(Auth::user()->isSupplier(), 403);
    }

    public function advance(int $itemId, string $status, OrderFulfillmentService $fulfillmentService): void
    {
        $supplier = Auth::user()->supplierProfile;

        $item = OrderItem::where('supplier_profile_id', $supplier->id)->findOrFail($itemId);

        $fulfillmentService->advanceItemStatus($item, $status, Auth::user());
    }

    public function with(): array
    {
        $supplier = Auth::user()->supplierProfile;

        $orders = $supplier
            ? Order::whereHas('items', fn ($q) => $q->where('supplier_profile_id', $supplier->id))
                ->with(['items' => fn ($q) => $q->where('supplier_profile_id', $supplier->id), 'user'])
                ->latest('placed_at')
                ->paginate(10)
            : null;

        return ['orders' => $orders];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-5xl mx-auto px-6 lg:px-8">
            <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">Orders to Fulfill</h1>
            <p class="mt-1 text-navy-200 text-sm">Orders containing your products, and their fulfillment status.</p>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-5xl mx-auto px-6 lg:px-8">
            @if (! $orders || $orders->isEmpty())
                <div class="bg-white rounded-2xl border border-navy-100 p-12 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-navy-50 text-navy-300 mb-4">
                        <x-icon name="truck" class="w-7 h-7" />
                    </span>
                    <h3 class="font-heading text-lg font-semibold text-navy-900">No orders yet</h3>
                    <p class="mt-2 text-sm text-navy-500 max-w-sm mx-auto">Orders containing your products will show up here as customers buy from you.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($orders as $order)
                        <div wire:key="order-{{ $order->id }}" class="bg-white rounded-2xl border border-navy-100 p-5">
                            <div class="flex items-center justify-between flex-wrap gap-3 pb-4 border-b border-navy-100">
                                <div>
                                    <p class="font-heading font-bold text-navy-900 text-sm">{{ $order->order_number }}</p>
                                    <p class="text-xs text-navy-400 mt-0.5">{{ $order->user->name }} &middot; {{ $order->placed_at->format('M j, Y') }}</p>
                                </div>
                                <span class="text-xs font-semibold bg-gold-100 text-gold-700 px-3 py-1 rounded-full">Order: {{ $order->statusLabel() }}</span>
                            </div>

                            <ul class="mt-4 divide-y divide-navy-100">
                                @foreach ($order->items as $item)
                                    <li wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-4 py-3 flex-wrap">
                                        <div>
                                            <p class="font-semibold text-navy-900 text-sm">{{ $item->product_name }}</p>
                                            <p class="text-xs text-navy-400 mt-0.5">{{ $item->quantity }} &times; {{ number_format($item->unit_price) }} XAF</p>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <span @class([
                                                'text-xs font-semibold px-3 py-1 rounded-full',
                                                'bg-gold-100 text-gold-700' => in_array($item->fulfillment_status, ['pending', 'confirmed', 'processing']),
                                                'bg-blue-100 text-blue-700' => in_array($item->fulfillment_status, ['shipped', 'out_for_delivery']),
                                                'bg-green-100 text-green-700' => $item->fulfillment_status === 'delivered',
                                                'bg-red-100 text-red-700' => in_array($item->fulfillment_status, ['cancelled', 'refunded']),
                                            ])>
                                                {{ ucfirst(str_replace('_', ' ', $item->fulfillment_status)) }}
                                            </span>

                                            @php
                                                $next = match ($item->fulfillment_status) {
                                                    'pending' => 'confirmed',
                                                    'confirmed' => 'shipped',
                                                    'shipped' => 'delivered',
                                                    default => null,
                                                };
                                                $nextLabel = match ($next) {
                                                    'confirmed' => 'Confirm',
                                                    'shipped' => 'Mark Shipped',
                                                    'delivered' => 'Mark Delivered',
                                                    default => null,
                                                };
                                            @endphp

                                            @if ($next)
                                                <x-secondary-button wire:click="advance({{ $item->id }}, '{{ $next }}')" wire:loading.attr="disabled">
                                                    {{ $nextLabel }}
                                                </x-secondary-button>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
