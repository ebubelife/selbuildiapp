<?php

use App\Models\Address;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderPlaced;
use App\Services\CartService;
use App\Services\CreditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    public string $step = 'review';

    public ?int $selectedAddressId = null;

    public ?int $selectedProjectId = null;

    public string $paymentMethod = 'cash_on_delivery';

    public bool $showNewAddressForm = false;

    public string $recipient_name = '';
    public string $phone = '';
    public string $country = 'Cameroon';
    public string $region = '';
    public string $city = '';
    public string $street = '';
    public string $landmark = '';

    public function mount(): void
    {
        $cart = app(CartService::class)->current();

        if ($cart->items->isEmpty()) {
            $this->redirect(route('shop.index'), navigate: true);

            return;
        }

        $defaultAddress = Auth::user()->addresses()->where('is_default', true)->first()
            ?? Auth::user()->addresses()->first();

        if ($defaultAddress) {
            $this->selectedAddressId = $defaultAddress->id;
        } else {
            $this->showNewAddressForm = true;
        }
    }

    public function goToStep(string $step): void
    {
        $this->step = $step;
    }

    public function selectAddress(int $addressId): void
    {
        $this->selectedAddressId = $addressId;
        $this->showNewAddressForm = false;
    }

    public function saveNewAddress(): void
    {
        $validated = $this->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ]);

        $address = Auth::user()->addresses()->create([
            ...$validated,
            'is_default' => Auth::user()->addresses()->count() === 0,
        ]);

        $this->selectedAddressId = $address->id;
        $this->showNewAddressForm = false;
        $this->reset(['recipient_name', 'phone', 'region', 'city', 'street', 'landmark']);
        $this->country = 'Cameroon';
    }

    public function placeOrder(CreditService $creditService): void
    {
        $this->validate([
            'selectedAddressId' => ['required', 'exists:addresses,id'],
        ], [], ['selectedAddressId' => 'delivery address']);

        $cart = app(CartService::class)->current()->load('items.product.supplierProfile');

        if ($cart->items->isEmpty()) {
            $this->redirect(route('shop.index'), navigate: true);

            return;
        }

        $subtotal = $cart->subtotal();

        $creditAccount = Auth::user()->creditAccount;
        $useCredit = $this->paymentMethod === 'selbuildi_credit'
            && $creditAccount?->isApproved()
            && $creditAccount->available_credit >= $subtotal;

        $order = DB::transaction(function () use ($cart, $subtotal, $useCredit) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => Auth::id(),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_fee' => 0,
                'tax' => 0,
                'discount' => 0,
                'total' => $subtotal,
                'currency' => 'XAF',
                'payment_status' => 'pending',
                'payment_method' => $useCredit ? 'selbuildi_credit' : 'cash_on_delivery',
                'shipping_address_id' => $this->selectedAddressId,
                'project_id' => $this->selectedProjectId,
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'supplier_profile_id' => $item->product->supplier_profile_id,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price_snapshot,
                    'total_price' => $item->unit_price_snapshot * $item->quantity,
                ]);

                // Best-effort stock decrement against the product's first
                // available inventory record - no multi-warehouse
                // allocation logic yet, that's beyond Phase 3 scope.
                $inventory = Inventory::where('product_id', $item->product_id)->first();
                if ($inventory) {
                    $inventory->decrement('quantity_available', min($item->quantity, $inventory->quantity_available));
                }
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'note' => 'Order placed.',
                'changed_by' => Auth::id(),
            ]);

            $cart->items()->delete();

            return $order;
        });

        if ($useCredit) {
            $creditService->drawdown($creditAccount, $order);
        }

        Auth::user()->notify(new OrderPlaced($order));

        $this->redirect(route('orders.show', $order), navigate: true);
    }

    public function with(): array
    {
        $cart = app(CartService::class)->current()->load('items.product.category', 'items.product.supplierProfile', 'items.productVariant');

        $creditAccount = Auth::user()->creditAccount;

        return [
            'cart' => $cart,
            'itemsBySupplier' => $cart->itemsBySupplier(),
            'addresses' => Auth::user()->addresses,
            'projects' => Auth::user()->isContractor() ? Auth::user()->projects()->where('status', 'active')->get() : collect(),
            'creditAccount' => $creditAccount,
            'creditUsableForOrder' => $creditAccount?->isApproved() && $creditAccount->available_credit >= $cart->subtotal(),
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-5xl mx-auto px-6 lg:px-8">
            <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">Checkout</h1>

            <!-- Step indicator -->
            <div class="mt-6 flex items-center gap-2">
                @foreach (['review' => 'Cart Review', 'address' => 'Delivery', 'confirm' => 'Confirm'] as $key => $label)
                    <div class="flex items-center gap-2 {{ ! $loop->first ? 'flex-1' : '' }}">
                        @unless ($loop->first)
                            <div @class([
                                'h-0.5 flex-1',
                                'bg-gold-500' => array_search($step, ['review', 'address', 'confirm']) >= array_search($key, ['review', 'address', 'confirm']),
                                'bg-white/20' => array_search($step, ['review', 'address', 'confirm']) < array_search($key, ['review', 'address', 'confirm']),
                            ])></div>
                        @endunless
                        <div class="flex items-center gap-2 shrink-0">
                            <span @class([
                                'flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold transition-colors duration-300',
                                'bg-gold-500 text-navy-900' => $step === $key || array_search($step, ['review', 'address', 'confirm']) > array_search($key, ['review', 'address', 'confirm']),
                                'bg-white/10 text-white/60' => $step !== $key && array_search($step, ['review', 'address', 'confirm']) < array_search($key, ['review', 'address', 'confirm']),
                            ])>
                                {{ array_search($key, ['review', 'address', 'confirm']) + 1 }}
                            </span>
                            <span @class([
                                'text-xs font-semibold hidden sm:inline',
                                'text-white' => $step === $key,
                                'text-white/50' => $step !== $key,
                            ])>{{ $label }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-5xl mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    @if ($step === 'review')
                        <div class="bg-white rounded-2xl border border-navy-100 p-6">
                            <h2 class="font-heading font-bold text-lg text-navy-900">Review your order</h2>

                            @foreach ($itemsBySupplier as $supplierId => $items)
                                <div class="mt-6 first:mt-4">
                                    <p class="text-xs font-semibold text-gold-800 uppercase tracking-wide mb-3">
                                        {{ $items->first()->product->supplierProfile->business_name }}
                                    </p>
                                    <ul class="divide-y divide-navy-100 border border-navy-100 rounded-xl overflow-hidden">
                                        @foreach ($items as $item)
                                            <li class="flex items-center gap-4 p-4">
                                                <span class="flex items-center justify-center w-12 h-12 rounded-lg bg-navy-50 text-navy-300 shrink-0">
                                                    <x-icon :name="$item->product->category->icon ?? 'cart'" class="w-5 h-5" />
                                                </span>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-semibold text-navy-900 text-sm truncate">{{ $item->product->name }}</p>
                                                    <p class="text-xs text-navy-400">{{ $item->quantity }} &times; {{ number_format($item->unit_price_snapshot) }} XAF</p>
                                                </div>
                                                <span class="font-semibold text-navy-900 text-sm">{{ number_format($item->lineTotal()) }} XAF</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach

                            <div class="mt-6">
                                <x-primary-button wire:click="goToStep('address')" class="w-full justify-center py-3">
                                    Continue to Delivery
                                    <x-icon name="arrow-right" class="w-4 h-4" />
                                </x-primary-button>
                            </div>
                        </div>
                    @elseif ($step === 'address')
                        <div class="bg-white rounded-2xl border border-navy-100 p-6">
                            <h2 class="font-heading font-bold text-lg text-navy-900">Delivery address</h2>

                            @if ($addresses->isNotEmpty() && ! $showNewAddressForm)
                                <div class="mt-4 space-y-3" role="radiogroup" aria-label="Delivery address">
                                    @foreach ($addresses as $address)
                                        <button
                                            type="button"
                                            role="radio"
                                            aria-checked="{{ $selectedAddressId === $address->id ? 'true' : 'false' }}"
                                            wire:click="selectAddress({{ $address->id }})"
                                            @class([
                                                'w-full text-left p-4 rounded-xl border-2 transition-colors duration-150',
                                                'border-gold-500 bg-gold-50' => $selectedAddressId === $address->id,
                                                'border-navy-100 hover:border-navy-300' => $selectedAddressId !== $address->id,
                                            ])
                                        >
                                            <p class="font-semibold text-navy-900 text-sm">{{ $address->recipient_name }} &middot; {{ $address->phone }}</p>
                                            <p class="text-sm text-navy-500 mt-1">{{ $address->street }}, {{ $address->city }}@if($address->region), {{ $address->region }}@endif, {{ $address->country }}</p>
                                        </button>
                                    @endforeach
                                </div>

                                <button type="button" wire:click="$set('showNewAddressForm', true)" class="mt-4 text-sm font-semibold text-navy-700 hover:text-gold-600 transition-colors">
                                    + Add a new address
                                </button>
                            @else
                                <form wire:submit="saveNewAddress" class="mt-4 space-y-4">
                                    <div class="grid sm:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="recipient_name" value="Recipient Name" />
                                            <x-text-input wire:model="recipient_name" id="recipient_name" class="block mt-1 w-full" />
                                            <x-input-error :messages="$errors->get('recipient_name')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="phone" value="Phone" />
                                            <x-text-input wire:model="phone" id="phone" class="block mt-1 w-full" />
                                            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                                        </div>
                                    </div>

                                    <div class="grid sm:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="country" value="Country" />
                                            <x-text-input wire:model="country" id="country" class="block mt-1 w-full" />
                                            <x-input-error :messages="$errors->get('country')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="region" value="Region" />
                                            <x-text-input wire:model="region" id="region" class="block mt-1 w-full" />
                                            <x-input-error :messages="$errors->get('region')" class="mt-1" />
                                        </div>
                                    </div>

                                    <div class="grid sm:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="city" value="City" />
                                            <x-text-input wire:model="city" id="city" class="block mt-1 w-full" />
                                            <x-input-error :messages="$errors->get('city')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="landmark" value="Landmark (optional)" />
                                            <x-text-input wire:model="landmark" id="landmark" class="block mt-1 w-full" />
                                        </div>
                                    </div>

                                    <div>
                                        <x-input-label for="street" value="Street / Address" />
                                        <x-text-input wire:model="street" id="street" class="block mt-1 w-full" />
                                        <x-input-error :messages="$errors->get('street')" class="mt-1" />
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <x-primary-button type="submit">Save Address</x-primary-button>
                                        @if ($addresses->isNotEmpty())
                                            <button type="button" wire:click="$set('showNewAddressForm', false)" class="text-sm text-navy-500 hover:text-navy-700 transition-colors">Cancel</button>
                                        @endif
                                    </div>
                                </form>
                            @endif

                            @if (! $showNewAddressForm)
                                <div class="mt-6 flex items-center gap-3">
                                    <x-secondary-button wire:click="goToStep('review')">Back</x-secondary-button>
                                    <x-primary-button wire:click="goToStep('confirm')" :disabled="! $selectedAddressId" class="flex-1 justify-center">
                                        Continue to Confirm
                                    </x-primary-button>
                                </div>
                            @endif
                        </div>
                    @elseif ($step === 'confirm')
                        <div class="bg-white rounded-2xl border border-navy-100 p-6">
                            <h2 class="font-heading font-bold text-lg text-navy-900">Review &amp; place order</h2>

                            @php $selected = $addresses->firstWhere('id', $selectedAddressId); @endphp
                            @if ($selected)
                                <div class="mt-4 p-4 rounded-xl bg-navy-50 border border-navy-100">
                                    <p class="text-xs font-semibold text-navy-500 uppercase tracking-wide mb-1">Delivering to</p>
                                    <p class="font-semibold text-navy-900 text-sm">{{ $selected->recipient_name }} &middot; {{ $selected->phone }}</p>
                                    <p class="text-sm text-navy-600 mt-1">{{ $selected->street }}, {{ $selected->city }}, {{ $selected->country }}</p>
                                </div>
                            @endif

                            @if ($projects->isNotEmpty())
                                <div class="mt-4">
                                    <x-input-label for="selectedProjectId" value="Link to a project (optional)" />
                                    <select wire:model="selectedProjectId" id="selectedProjectId" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                                        <option value="">Not linked to a project</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="mt-4">
                                <p class="text-xs font-semibold text-navy-500 uppercase tracking-wide mb-2" id="payment-method-label">Payment method</p>

                                <div class="space-y-2" role="radiogroup" aria-labelledby="payment-method-label">
                                    <button
                                        type="button"
                                        role="radio"
                                        aria-checked="{{ $paymentMethod === 'cash_on_delivery' ? 'true' : 'false' }}"
                                        wire:click="$set('paymentMethod', 'cash_on_delivery')"
                                        @class([
                                            'w-full text-left p-4 rounded-xl border-2 transition-colors duration-150 flex items-start gap-3',
                                            'border-gold-500 bg-gold-50' => $paymentMethod === 'cash_on_delivery',
                                            'border-navy-100 hover:border-navy-300' => $paymentMethod !== 'cash_on_delivery',
                                        ])
                                    >
                                        <x-icon name="wallet" class="w-4 h-4 mt-0.5 shrink-0" />
                                        <span>
                                            <span class="font-semibold text-navy-900 text-sm block">Cash / Pay on Delivery</span>
                                            <span class="text-xs text-navy-400">Pay when your materials arrive.</span>
                                        </span>
                                    </button>

                                    @if ($creditUsableForOrder)
                                        <button
                                            type="button"
                                            role="radio"
                                            aria-checked="{{ $paymentMethod === 'selbuildi_credit' ? 'true' : 'false' }}"
                                            wire:click="$set('paymentMethod', 'selbuildi_credit')"
                                            @class([
                                                'w-full text-left p-4 rounded-xl border-2 transition-colors duration-150 flex items-start gap-3',
                                                'border-gold-500 bg-gold-50' => $paymentMethod === 'selbuildi_credit',
                                                'border-navy-100 hover:border-navy-300' => $paymentMethod !== 'selbuildi_credit',
                                            ])
                                        >
                                            <x-icon name="star" class="w-4 h-4 mt-0.5 shrink-0" />
                                            <span>
                                                <span class="font-semibold text-navy-900 text-sm block">Pay with Selbuildi Credit</span>
                                                <span class="text-xs text-navy-400">{{ number_format($creditAccount->available_credit) }} XAF available. Repay by the due date shown on your order.</span>
                                            </span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6 flex items-center gap-3">
                                <x-secondary-button wire:click="goToStep('address')">Back</x-secondary-button>
                                <x-primary-button
                                    wire:click="placeOrder"
                                    wire:loading.attr="disabled"
                                    wire:target="placeOrder"
                                    class="flex-1 justify-center py-3"
                                >
                                    <span wire:loading.remove wire:target="placeOrder">Place Order</span>
                                    <span wire:loading wire:target="placeOrder">Placing order...</span>
                                </x-primary-button>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Order summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl border border-navy-100 p-6 sticky top-24">
                        <h3 class="font-heading font-semibold text-navy-900">Order Summary</h3>
                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between text-navy-500">
                                <span>Subtotal ({{ $cart->totalQuantity() }} items)</span>
                                <span class="text-navy-900 font-medium">{{ number_format($cart->subtotal()) }} XAF</span>
                            </div>
                            <div class="flex justify-between text-navy-500">
                                <span>Delivery</span>
                                <span class="text-navy-900 font-medium">Calculated later</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-navy-100 flex justify-between">
                            <span class="font-semibold text-navy-900">Total</span>
                            <span class="font-heading font-bold text-navy-900">{{ number_format($cart->subtotal()) }} XAF</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
