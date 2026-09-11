<?php

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Support\Js;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site')] class extends Component
{
    public Product $product;
    public int $quantity = 1;
    public bool $justAdded = false;

    public function mount(Product $product): void
    {
        $this->product = $product->load(['category', 'brand', 'supplierProfile', 'variants', 'images', 'inventories']);
        $this->quantity = max(1, $product->min_order_qty);
    }

    public function increment(): void
    {
        $this->quantity++;
    }

    public function decrement(): void
    {
        $this->quantity = max($this->product->min_order_qty, $this->quantity - 1);
    }

    public function addToCart(): void
    {
        app(CartService::class)->add($this->product, $this->quantity);

        $this->justAdded = true;
        $this->dispatch('cart-updated');
    }

    public function with(): array
    {
        return [
            'related' => Product::where('category_id', $this->product->category_id)
                ->where('id', '!=', $this->product->id)
                ->where('is_active', true)
                ->limit(4)
                ->get(),
        ];
    }

    /**
     * Product pages are the highest-value SEO surface - people search for
     * the specific material, not the brand - so the title/description are
     * generated per-product rather than using the layout's static default.
     * parent::render() (Volt's own) resolves the co-located Blade template;
     * ->title()/->layoutData() are Livewire's page-component macros for
     * overriding params merged into the #[Layout] component.
     */
    public function render(): mixed
    {
        $product = $this->product;
        $supplier = $product->supplierProfile?->business_name;

        return parent::render()
            ->title("{$product->name} — Buy Online in Cameroon | Selbuildi")
            ->layoutData([
                'description' => "{$product->name} for {$product->formattedPrice()} per {$product->unit}".
                    ($supplier ? " from {$supplier}." : '.').
                    ' Order online with delivery tracking across Cameroon.',
            ]);
    }
}; ?>

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@@type' => 'Product',
    'name' => $product->name,
    'description' => $product->description ?: $product->name,
    'sku' => $product->sku,
    'category' => $product->category->name,
    ...($product->images->isNotEmpty() ? ['image' => $product->images->map(fn ($image) => asset('storage/'.$image->path))->all()] : []),
    'offers' => [
        '@@type' => 'Offer',
        'priceCurrency' => 'XAF',
        'price' => $product->price,
        'availability' => $product->is_active ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => url()->current(),
        ...($product->supplierProfile ? [
            'seller' => [
                '@@type' => 'Organization',
                'name' => $product->supplierProfile->business_name,
            ],
        ] : []),
    ],
]) !!}
</script>
@endpush

<div>
    <div class="pt-28 pb-6 bg-neutral-50 border-b border-navy-100">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <nav class="flex items-center gap-2 text-sm text-navy-500">
                <a href="{{ route('shop.index') }}" wire:navigate class="hover:text-gold-600 transition-colors">Shop</a>
                <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                <a href="{{ route('shop.index', ['category' => $product->category_id]) }}" wire:navigate class="hover:text-gold-600 transition-colors">{{ $product->category->name }}</a>
                <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                <span class="text-navy-800 font-medium truncate">{{ $product->name }}</span>
            </nav>
        </div>
    </div>

    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Image -->
                <x-reveal>
                    @if ($product->images->isNotEmpty())
                        <div
                            x-data="{
                                active: 0,
                                images: {{ Js::from($product->images->map(fn ($image) => asset('storage/'.$image->path))->all()) }},
                                touchStartX: 0,
                                next() { this.active = (this.active + 1) % this.images.length },
                                prev() { this.active = (this.active - 1 + this.images.length) % this.images.length },
                            }"
                        >
                            <div
                                class="aspect-square bg-navy-50 rounded-2xl relative overflow-hidden"
                                @touchstart="touchStartX = $event.changedTouches[0].screenX"
                                @touchend="
                                    let delta = $event.changedTouches[0].screenX - touchStartX;
                                    if (delta > 50) prev();
                                    else if (delta < -50) next();
                                "
                            >
                                <template x-for="(image, index) in images" :key="index">
                                    <img
                                        :src="image"
                                        alt="{{ $product->name }}"
                                        x-show="active === index"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        class="absolute inset-0 w-full h-full object-cover"
                                    >
                                </template>

                                @if ($product->is_featured)
                                    <span class="absolute top-4 left-4 z-10 bg-gold-500 text-navy-900 text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-full">Featured</span>
                                @endif

                                @if ($product->images->count() > 1)
                                    <button
                                        type="button"
                                        @click="prev"
                                        aria-label="Previous photo"
                                        class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-full bg-white/90 text-navy-900 shadow-md hover:bg-white transition-colors"
                                    >
                                        <x-icon name="chevron-right" class="w-4 h-4 rotate-180" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="next"
                                        aria-label="Next photo"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-full bg-white/90 text-navy-900 shadow-md hover:bg-white transition-colors"
                                    >
                                        <x-icon name="chevron-right" class="w-4 h-4" />
                                    </button>

                                    <div class="absolute bottom-3 inset-x-0 flex items-center justify-center gap-1.5">
                                        <template x-for="(image, index) in images" :key="index">
                                            <button
                                                type="button"
                                                @click="active = index"
                                                aria-label="Go to photo"
                                                class="h-1.5 rounded-full transition-all duration-300"
                                                :class="active === index ? 'w-5 bg-white' : 'w-1.5 bg-white/50'"
                                            ></button>
                                        </template>
                                    </div>
                                @endif
                            </div>

                            @if ($product->images->count() > 1)
                                <div class="mt-4 grid grid-cols-5 gap-3">
                                    <template x-for="(image, index) in images" :key="index">
                                        <button
                                            type="button"
                                            @click="active = index"
                                            class="aspect-square rounded-lg overflow-hidden ring-2 transition-colors"
                                            :class="active === index ? 'ring-gold-500' : 'ring-transparent hover:ring-navy-200'"
                                        >
                                            <img :src="image" alt="" class="w-full h-full object-cover">
                                        </button>
                                    </template>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="aspect-square bg-navy-50 rounded-2xl flex items-center justify-center relative overflow-hidden">
                            <x-icon :name="$product->category->icon ?? 'cart'" class="w-32 h-32 text-navy-300" stroke-width="1" />
                            @if ($product->is_featured)
                                <span class="absolute top-4 left-4 bg-gold-500 text-navy-900 text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-full">Featured</span>
                            @endif
                        </div>
                    @endif
                </x-reveal>

                <!-- Details -->
                <x-reveal :delay="100">
                    <p class="text-sm font-semibold text-gold-800 uppercase tracking-wide">
                        {{ $product->category->name }}
                        @if ($product->brand)
                            &middot; {{ $product->brand->name }}
                        @endif
                    </p>
                    <h1 class="mt-2 font-heading text-3xl font-bold text-navy-900">{{ $product->name }}</h1>

                    <a href="{{ route('suppliers.show', $product->supplierProfile) }}" wire:navigate class="mt-3 inline-flex items-center gap-2 text-sm text-navy-500 hover:text-gold-600 transition-colors">
                        <x-icon name="shield" class="w-4 h-4" />
                        Sold by <span class="font-semibold text-navy-700">{{ $product->supplierProfile->business_name }}</span>
                        @if ($product->supplierProfile->isVerified())
                            <span class="text-green-600 text-xs font-semibold">&middot; Verified</span>
                        @endif
                    </a>

                    <div class="mt-6 flex items-baseline gap-3">
                        <x-price :xaf="$product->price" :show-xaf-hint="true" class="font-heading text-3xl font-bold text-navy-900" />
                        @if ($product->compare_at_price)
                            <span class="text-navy-400 line-through text-sm"><x-price :xaf="$product->compare_at_price" /></span>
                        @endif
                        <span class="text-sm text-navy-500">per {{ $product->unit }}</span>
                    </div>

                    <span @class([
                        'mt-3 inline-block text-xs font-bold uppercase tracking-wide px-2.5 py-1 rounded-full',
                        'bg-green-100 text-green-700' => $product->stockStatus() === 'in_stock',
                        'bg-amber-100 text-amber-700' => $product->stockStatus() === 'low_stock',
                        'bg-red-100 text-red-700' => $product->stockStatus() === 'out_of_stock',
                    ])>
                        {{ $product->stockStatusLabel() }}
                    </span>

                    @if ($product->description)
                        <p class="mt-4 text-navy-600 leading-relaxed">{{ $product->description }}</p>
                    @endif

                    @if ($product->specification)
                        <p class="mt-2 text-sm text-navy-500"><span class="font-semibold text-navy-700">Specification:</span> {{ $product->specification }}</p>
                    @endif

                    <p class="mt-4 text-xs text-navy-400">Minimum order: {{ $product->min_order_qty }} {{ str($product->unit)->plural($product->min_order_qty) }}</p>

                    <!-- Quantity + CTA -->
                    <div class="mt-8 flex items-center gap-4">
                        <div class="flex items-center border border-navy-200 rounded-lg">
                            <button type="button" wire:click="decrement" aria-label="Decrease quantity" class="w-10 h-10 flex items-center justify-center text-navy-500 hover:text-navy-900 transition-colors">&minus;</button>
                            <span class="w-12 text-center font-semibold text-navy-900" aria-live="polite">{{ $quantity }}</span>
                            <button type="button" wire:click="increment" aria-label="Increase quantity" class="w-10 h-10 flex items-center justify-center text-navy-500 hover:text-navy-900 transition-colors">&plus;</button>
                        </div>

                        <x-primary-button
                            class="flex-1 justify-center py-3"
                            wire:click="addToCart"
                            wire:loading.attr="disabled"
                            wire:target="addToCart"
                        >
                            <span wire:loading.remove wire:target="addToCart" class="flex items-center gap-2">
                                <x-icon :name="$justAdded ? 'check' : 'cart'" class="w-4 h-4" />
                                {{ $justAdded ? 'Added to Cart' : 'Add to Cart' }}
                            </span>
                            <span wire:loading wire:target="addToCart">Adding...</span>
                        </x-primary-button>
                    </div>

                    <a href="{{ route('support.create', ['product' => $product->id]) }}" wire:navigate class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-navy-500 hover:text-gold-600 transition-colors">
                        <x-icon name="check" class="w-3.5 h-3.5" />
                        Need a bulk order or custom quote? Request a Quote
                    </a>

                    @if ($justAdded)
                        <p
                            x-data
                            x-init="setTimeout(() => $wire.justAdded = false, 2500)"
                            class="mt-3 text-sm text-green-600 flex items-center gap-1.5 animate-fade-in"
                        >
                            <x-icon name="check" class="w-4 h-4" stroke-width="2.5" />
                            Added {{ $quantity }} {{ str($product->unit)->plural($quantity) }} to your cart.
                        </p>
                    @endif

                    <div class="mt-8 grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-3 text-sm text-navy-600">
                            <span class="flex items-center justify-center w-9 h-9 rounded-full bg-navy-50 text-navy-700 shrink-0">
                                <x-icon name="truck" class="w-4 h-4" />
                            </span>
                            Delivery tracked to your site
                        </div>
                        <div class="flex items-center gap-3 text-sm text-navy-600">
                            <span class="flex items-center justify-center w-9 h-9 rounded-full bg-navy-50 text-navy-700 shrink-0">
                                <x-icon name="shield" class="w-4 h-4" />
                            </span>
                            Verified supplier
                        </div>
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="py-16 bg-neutral-50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <h2 class="font-heading text-xl font-bold text-navy-900">More in {{ $product->category->name }}</h2>

                <div class="mt-8 grid grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach ($related as $item)
                        <a href="{{ route('shop.show', $item) }}" wire:navigate class="group bg-white rounded-2xl border border-navy-100 overflow-hidden hover:shadow-brand hover:-translate-y-1 transition-all duration-300">
                            <div class="aspect-square bg-navy-50 flex items-center justify-center">
                                <x-icon :name="$item->category->icon ?? 'cart'" class="w-14 h-14 text-navy-300 group-hover:text-gold-500 transition-colors duration-300" stroke-width="1.2" />
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-navy-900 text-sm leading-snug">{{ $item->name }}</h3>
                                <x-price :xaf="$item->price" class="mt-2 block font-heading font-bold text-navy-900 text-sm" />
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
