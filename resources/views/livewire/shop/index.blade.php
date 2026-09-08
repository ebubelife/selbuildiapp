<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site', [
    'title' => 'Shop Building Materials Online in Cameroon — Selbuildi',
    'description' => 'Browse cement, roofing sheets, steel & rebar, tiles, and blocks from verified suppliers across Cameroon. Compare prices and order with real-time delivery tracking.',
])] class extends Component
{
    use WithPagination;

    public ?int $justAdded = null;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public ?int $category = null;

    #[Url(history: true)]
    public ?int $brand = null;

    #[Url(history: true)]
    public ?int $priceMin = null;

    #[Url(history: true)]
    public ?int $priceMax = null;

    #[Url(history: true)]
    public bool $verifiedOnly = false;

    #[Url(history: true)]
    public bool $inStockOnly = false;

    #[Url(history: true)]
    public string $sort = 'featured';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->category = $this->category === $categoryId ? null : $categoryId;
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedBrand(): void
    {
        $this->resetPage();
    }

    public function updatedPriceMin(): void
    {
        $this->resetPage();
    }

    public function updatedPriceMax(): void
    {
        $this->resetPage();
    }

    public function updatedVerifiedOnly(): void
    {
        $this->resetPage();
    }

    public function updatedInStockOnly(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category', 'brand', 'priceMin', 'priceMax', 'verifiedOnly', 'inStockOnly']);
        $this->resetPage();
    }

    public function addToCart(int $productId): void
    {
        $product = Product::findOrFail($productId);

        app(CartService::class)->add($product, max(1, $product->min_order_qty));

        $this->justAdded = $productId;
        $this->dispatch('cart-updated');
    }

    public function with(): array
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with(['category', 'brand', 'supplierProfile', 'images', 'inventories']);

        if ($this->search !== '') {
            // Token-split so "50kg cement" still matches a name like
            // "Cement 50kg Bag" regardless of word order, and each token
            // can match any of name/brand/sku/description/specification.
            foreach (preg_split('/\s+/', trim($this->search)) as $word) {
                $query->where(function ($group) use ($word) {
                    $group->where('name', 'like', "%{$word}%")
                        ->orWhere('sku', 'like', "%{$word}%")
                        ->orWhere('description', 'like', "%{$word}%")
                        ->orWhere('specification', 'like', "%{$word}%")
                        ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$word}%"));
                });
            }
        }

        if ($this->category) {
            $query->where('category_id', $this->category);
        }

        if ($this->brand) {
            $query->where('brand_id', $this->brand);
        }

        if ($this->priceMin) {
            $query->where('price', '>=', $this->priceMin);
        }

        if ($this->priceMax) {
            $query->where('price', '<=', $this->priceMax);
        }

        if ($this->verifiedOnly) {
            $query->whereHas('supplierProfile', fn ($s) => $s->whereNotNull('verified_at'));
        }

        if ($this->inStockOnly) {
            $query->whereIn('id', fn ($q) => $q->select('product_id')
                ->from('inventories')
                ->groupBy('product_id')
                ->havingRaw('SUM(quantity_available) > 0'));
        }

        match ($this->sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('is_featured')->orderBy('name'),
        };

        return [
            'products' => $query->paginate(12),
            'categories' => Category::whereNull('parent_id')->orderBy('sort_order')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <section class="bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700 pt-32 pb-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <span class="text-sm font-semibold text-gold-500 uppercase tracking-wide">Shop Materials</span>
            <h1 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-white">Everything for your build</h1>
            <p class="mt-3 text-navy-200 max-w-xl">Browse materials from verified suppliers across Cameroon.</p>
        </div>
    </section>

    <section class="bg-white border-b border-navy-100 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <!-- Search -->
                <div class="relative flex-1 max-w-md">
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Search materials..."
                        class="w-full rounded-lg border-navy-200 pl-10 pr-4 py-2.5 text-sm focus:border-gold-500 focus:ring-gold-500"
                    >
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-navy-300">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    </span>
                </div>

                <!-- Sort -->
                <select wire:model.live="sort" class="rounded-lg border-navy-200 text-sm focus:border-gold-500 focus:ring-gold-500 lg:ml-auto">
                    <option value="featured">Featured</option>
                    <option value="newest">Newest</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                </select>
            </div>

            <!-- Category pills -->
            <div class="mt-4 flex gap-2 overflow-x-auto pb-1 -mx-6 px-6 lg:mx-0 lg:px-0 scrollbar-none">
                <button
                    type="button"
                    wire:click="selectCategory(null)"
                    @class([
                        'shrink-0 rounded-full px-4 py-2 text-sm font-medium transition-colors duration-150 border',
                        'bg-navy-900 text-white border-navy-900' => ! $category,
                        'bg-white text-navy-600 border-navy-200 hover:border-navy-400' => $category,
                    ])
                >
                    All
                </button>
                @foreach ($categories as $cat)
                    <button
                        type="button"
                        wire:click="selectCategory({{ $cat->id }})"
                        @class([
                            'shrink-0 flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-colors duration-150 border',
                            'bg-navy-900 text-white border-navy-900' => $category === $cat->id,
                            'bg-white text-navy-600 border-navy-200 hover:border-navy-400' => $category !== $cat->id,
                        ])
                    >
                        @if ($cat->icon)
                            <x-icon :name="$cat->icon" class="w-4 h-4" />
                        @endif
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            <!-- Filters -->
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-navy-100 pt-4">
                <select wire:model.live="brand" class="rounded-lg border-navy-200 text-sm focus:border-gold-500 focus:ring-gold-500">
                    <option value="">All brands</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-1.5">
                    <input type="number" min="0" wire:model.live.debounce.500ms="priceMin" placeholder="Min XAF" class="w-28 rounded-lg border-navy-200 text-sm focus:border-gold-500 focus:ring-gold-500">
                    <span class="text-navy-300">&ndash;</span>
                    <input type="number" min="0" wire:model.live.debounce.500ms="priceMax" placeholder="Max XAF" class="w-28 rounded-lg border-navy-200 text-sm focus:border-gold-500 focus:ring-gold-500">
                </div>

                <label class="flex items-center gap-1.5 text-sm text-navy-600 cursor-pointer">
                    <input type="checkbox" wire:model.live="verifiedOnly" class="rounded border-navy-300 text-gold-500 focus:ring-gold-500">
                    Verified suppliers only
                </label>

                <label class="flex items-center gap-1.5 text-sm text-navy-600 cursor-pointer">
                    <input type="checkbox" wire:model.live="inStockOnly" class="rounded border-navy-300 text-gold-500 focus:ring-gold-500">
                    In stock only
                </label>

                @if ($search || $category || $brand || $priceMin || $priceMax || $verifiedOnly || $inStockOnly)
                    <button type="button" wire:click="clearFilters" class="text-sm font-semibold text-navy-500 hover:text-gold-600 transition-colors">
                        Clear filters
                    </button>
                @endif
            </div>
        </div>
    </section>

    <!-- Results -->
    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div wire:loading.class="opacity-50" wire:target="search,sort,selectCategory,brand,priceMin,priceMax,verifiedOnly,inStockOnly,clearFilters" class="transition-opacity duration-200">
                @if ($products->isEmpty())
                    <div class="text-center py-24">
                        <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-navy-100 text-navy-400 mb-4">
                            <x-icon name="cart" class="w-7 h-7" />
                        </span>
                        <h3 class="font-heading text-lg font-semibold text-navy-900">No materials found</h3>
                        <p class="mt-2 text-sm text-navy-500">Try a different search term or category.</p>
                        <a href="{{ route('support.create') }}" wire:navigate>
                            <x-primary-button class="mt-6">Can't Find What You Need? Tell Us</x-primary-button>
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach ($products as $product)
                            <div
                                wire:key="product-{{ $product->id }}"
                                class="group bg-white rounded-2xl border border-navy-100 overflow-hidden hover:shadow-brand hover:-translate-y-1 transition-all duration-300"
                            >
                                <a href="{{ route('shop.show', $product) }}" wire:navigate class="block">
                                    <div class="aspect-square bg-navy-50 flex items-center justify-center relative overflow-hidden">
                                        @if ($product->images->isNotEmpty())
                                            <img
                                                src="{{ asset('storage/'.$product->images->first()->path) }}"
                                                alt="{{ $product->name }}"
                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            >
                                        @else
                                            <x-icon :name="$product->category->icon ?? 'cart'" class="w-16 h-16 text-navy-300 group-hover:scale-110 group-hover:text-gold-500 transition-all duration-300" stroke-width="1.2" />
                                        @endif
                                        @if ($product->is_featured)
                                            <span class="absolute top-3 left-3 bg-gold-500 text-navy-900 text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full">Featured</span>
                                        @endif
                                        <span @class([
                                            'absolute top-3 right-3 text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full',
                                            'bg-green-100 text-green-700' => $product->stockStatus() === 'in_stock',
                                            'bg-amber-100 text-amber-700' => $product->stockStatus() === 'low_stock',
                                            'bg-red-100 text-red-700' => $product->stockStatus() === 'out_of_stock',
                                        ])>
                                            {{ $product->stockStatusLabel() }}
                                        </span>
                                    </div>
                                    <div class="px-5 pt-5">
                                        <p class="text-[11px] font-semibold text-gold-800 uppercase tracking-wide">
                                            {{ $product->category->name }}
                                            @if ($product->brand)
                                                &middot; {{ $product->brand->name }}
                                            @endif
                                        </p>
                                        <h3 class="mt-1 font-semibold text-navy-900 text-sm leading-snug">{{ $product->name }}</h3>
                                        <p class="text-xs text-navy-400 mt-1 flex items-center gap-1 truncate">
                                            per {{ $product->unit }} &middot; {{ $product->supplierProfile->business_name }}
                                            @if ($product->supplierProfile->isVerified())
                                                <x-icon name="shield" class="w-3 h-3 text-green-600 shrink-0" />
                                            @endif
                                        </p>
                                    </div>
                                </a>
                                <div class="px-5 pb-5">
                                    <div class="mt-3 flex items-center justify-between">
                                        <span class="font-heading font-bold text-navy-900">{{ number_format($product->price) }} <span class="text-xs font-normal text-navy-400">XAF</span></span>
                                        <button
                                            type="button"
                                            wire:click="addToCart({{ $product->id }})"
                                            @class([
                                                'flex items-center justify-center w-9 h-9 rounded-full transition-all duration-150',
                                                'bg-green-500 text-white' => $justAdded === $product->id,
                                                'bg-gold-500 text-navy-900 hover:bg-gold-600 hover:scale-110' => $justAdded !== $product->id,
                                            ])
                                        >
                                            <x-icon :name="$justAdded === $product->id ? 'check' : 'cart'" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $products->links() }}
                    </div>
                @endif

                <div class="mt-12 flex flex-col sm:flex-row items-center justify-between gap-4 bg-white rounded-2xl border border-navy-100 p-6">
                    <div class="text-center sm:text-left">
                        <p class="font-heading font-semibold text-navy-900">Can't find what you need?</p>
                        <p class="text-sm text-navy-500 mt-1">Tell us what you're looking for and we'll help you source it.</p>
                    </div>
                    <a href="{{ route('support.create') }}" wire:navigate class="shrink-0">
                        <x-primary-button>Tell Us What You Need</x-primary-button>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
