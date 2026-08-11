<?php

use App\Models\SupplierProfile;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site')] class extends Component
{
    use WithPagination;

    public SupplierProfile $supplier;

    public function mount(SupplierProfile $supplier): void
    {
        $this->supplier = $supplier;
    }

    public function with(): array
    {
        return [
            'products' => $this->supplier->products()
                ->where('is_active', true)
                ->with('category')
                ->paginate(12),
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-12 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex items-center gap-5">
                <span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-white/10 border border-white/20 text-gold-500 shrink-0">
                    <x-icon name="shield" class="w-8 h-8" />
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">{{ $supplier->business_name }}</h1>
                        @if ($supplier->isVerified())
                            <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-500/15 text-green-400 px-2.5 py-1 rounded-full">
                                <x-icon name="check" class="w-3 h-3" stroke-width="2.5" />
                                Verified
                            </span>
                        @endif
                    </div>
                    @if ($supplier->description)
                        <p class="mt-2 text-navy-200 max-w-2xl text-sm leading-relaxed">{{ $supplier->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[40vh]">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <h2 class="font-heading text-lg font-bold text-navy-900">Materials from {{ $supplier->business_name }}</h2>

            @if ($products->isEmpty())
                <div class="text-center py-20">
                    <p class="text-sm text-navy-500">No materials listed yet.</p>
                </div>
            @else
                <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach ($products as $product)
                        <a href="{{ route('shop.show', $product) }}" wire:navigate class="group bg-white rounded-2xl border border-navy-100 overflow-hidden hover:shadow-brand hover:-translate-y-1 transition-all duration-300">
                            <div class="aspect-square bg-navy-50 flex items-center justify-center">
                                <x-icon :name="$product->category->icon ?? 'cart'" class="w-16 h-16 text-navy-300 group-hover:text-gold-500 transition-colors duration-300" stroke-width="1.2" />
                            </div>
                            <div class="p-5">
                                <p class="text-[11px] font-semibold text-gold-600 uppercase tracking-wide">{{ $product->category->name }}</p>
                                <h3 class="mt-1 font-semibold text-navy-900 text-sm leading-snug">{{ $product->name }}</h3>
                                <span class="mt-2 block font-heading font-bold text-navy-900 text-sm">{{ number_format($product->price) }} XAF</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
