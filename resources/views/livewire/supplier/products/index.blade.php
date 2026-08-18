<?php

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->isSupplier(), 403);
    }

    public function toggleActive(int $productId): void
    {
        $product = Auth::user()->supplierProfile->products()->findOrFail($productId);
        $product->update(['is_active' => ! $product->is_active]);
    }

    public function deleteProduct(int $productId): void
    {
        Auth::user()->supplierProfile->products()->findOrFail($productId)->delete();
    }

    public function with(): array
    {
        $supplier = Auth::user()->supplierProfile;

        return [
            'supplier' => $supplier,
            'products' => $supplier
                ? $supplier->products()
                    ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                    ->with(['category', 'inventories'])
                    ->latest()
                    ->paginate(10)
                : null,
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-5xl mx-auto px-6 lg:px-8 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">My Products</h1>
                <p class="mt-1 text-navy-200 text-sm">Manage your material listings and stock levels.</p>
            </div>
            @if ($supplier?->isVerified())
                <a href="{{ route('supplier.products.create') }}" wire:navigate>
                    <x-primary-button>+ New Product</x-primary-button>
                </a>
            @endif
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-5xl mx-auto px-6 lg:px-8">
            @if (! $supplier?->isVerified())
                <div class="bg-gold-50 border border-gold-100 rounded-2xl p-6 mb-6 flex items-start gap-4">
                    <span class="flex items-center justify-center w-10 h-10 rounded-full bg-gold-500 text-navy-900 shrink-0">
                        <x-icon name="shield" class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="font-heading font-semibold text-navy-900">Verification required</h3>
                        <p class="mt-1 text-sm text-navy-600 leading-relaxed max-w-2xl">
                            You can list products as soon as {{ $supplier->business_name }} is verified. We'll email you once it's approved.
                        </p>
                    </div>
                </div>
            @endif

            <div class="mb-6">
                <x-text-input wire:model.live.debounce.400ms="search" placeholder="Search your products&hellip;" class="w-full sm:w-80" />
            </div>

            @if ($products && $products->isEmpty())
                <div class="bg-white rounded-2xl border border-navy-100 p-12 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-navy-50 text-navy-300 mb-4">
                        <x-icon name="cart" class="w-7 h-7" />
                    </span>
                    <h3 class="font-heading text-lg font-semibold text-navy-900">No products yet</h3>
                    <p class="mt-2 text-sm text-navy-500 max-w-sm mx-auto">
                        {{ $supplier?->isVerified() ? 'List your first material to start selling on Selbuildi.' : 'Once verified, you can start listing materials for sale.' }}
                    </p>
                    @if ($supplier?->isVerified())
                        <a href="{{ route('supplier.products.create') }}" wire:navigate>
                            <x-primary-button class="mt-6">+ New Product</x-primary-button>
                        </a>
                    @endif
                </div>
            @elseif ($products)
                <div class="space-y-3">
                    @foreach ($products as $product)
                        @php $stock = $product->inventories->sum('quantity_available'); @endphp
                        <div wire:key="product-{{ $product->id }}" class="bg-white rounded-2xl border border-navy-100 p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <span class="flex items-center justify-center w-12 h-12 rounded-lg bg-navy-50 text-navy-300 shrink-0">
                                    <x-icon :name="$product->category->icon ?? 'cart'" class="w-5 h-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-navy-900 text-sm truncate">{{ $product->name }}</p>
                                    <p class="text-xs text-navy-400 mt-0.5">{{ $product->category->name }} &middot; {{ $product->formattedPrice() }} / {{ $product->unit }} &middot; {{ $stock }} in stock</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 sm:ml-auto">
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $product->id }})"
                                    @class([
                                        'text-xs font-semibold px-3 py-1.5 rounded-full transition-colors',
                                        'bg-green-100 text-green-700 hover:bg-green-200' => $product->is_active,
                                        'bg-navy-100 text-navy-500 hover:bg-navy-200' => ! $product->is_active,
                                    ])
                                >
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </button>
                                <a href="{{ route('supplier.products.edit', $product) }}" wire:navigate class="text-xs font-semibold text-navy-700 hover:text-gold-600 px-3 py-1.5 transition-colors">
                                    Edit
                                </a>
                                <button
                                    type="button"
                                    wire:click="deleteProduct({{ $product->id }})"
                                    wire:confirm="Delete {{ $product->name }}? This can't be undone."
                                    class="text-xs font-semibold text-red-500 hover:text-red-700 px-3 py-1.5 transition-colors"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
