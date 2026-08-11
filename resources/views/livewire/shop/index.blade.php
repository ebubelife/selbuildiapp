<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site')] class extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public ?int $category = null;

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

    public function with(): array
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with(['category', 'supplierProfile']);

        if ($this->search !== '') {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if ($this->category) {
            $query->where('category_id', $this->category);
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
        </div>
    </section>

    <!-- Results -->
    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div wire:loading.class="opacity-50" wire:target="search,sort,selectCategory" class="transition-opacity duration-200">
                @if ($products->isEmpty())
                    <div class="text-center py-24">
                        <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-navy-100 text-navy-400 mb-4">
                            <x-icon name="cart" class="w-7 h-7" />
                        </span>
                        <h3 class="font-heading text-lg font-semibold text-navy-900">No materials found</h3>
                        <p class="mt-2 text-sm text-navy-500">Try a different search term or category.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach ($products as $product)
                            <a
                                href="{{ route('shop.show', $product) }}"
                                wire:navigate
                                wire:key="product-{{ $product->id }}"
                                class="group bg-white rounded-2xl border border-navy-100 overflow-hidden hover:shadow-brand hover:-translate-y-1 transition-all duration-300"
                            >
                                <div class="aspect-square bg-navy-50 flex items-center justify-center relative overflow-hidden">
                                    <x-icon :name="$product->category->icon ?? 'cart'" class="w-16 h-16 text-navy-300 group-hover:scale-110 group-hover:text-gold-500 transition-all duration-300" stroke-width="1.2" />
                                    @if ($product->is_featured)
                                        <span class="absolute top-3 left-3 bg-gold-500 text-navy-900 text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full">Featured</span>
                                    @endif
                                </div>
                                <div class="p-5">
                                    <p class="text-[11px] font-semibold text-gold-600 uppercase tracking-wide">{{ $product->category->name }}</p>
                                    <h3 class="mt-1 font-semibold text-navy-900 text-sm leading-snug">{{ $product->name }}</h3>
                                    <p class="text-xs text-navy-400 mt-1">per {{ $product->unit }} &middot; {{ $product->supplierProfile->business_name }}</p>
                                    <div class="mt-3 flex items-center justify-between">
                                        <span class="font-heading font-bold text-navy-900">{{ number_format($product->price) }} <span class="text-xs font-normal text-navy-400">XAF</span></span>
                                        <span class="flex items-center justify-center w-9 h-9 rounded-full bg-gold-500 text-navy-900 group-hover:bg-gold-600 group-hover:scale-110 transition-all duration-150">
                                            <x-icon name="arrow-right" class="w-4 h-4" />
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
