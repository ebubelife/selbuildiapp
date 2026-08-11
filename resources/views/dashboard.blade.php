<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading font-semibold text-xl text-navy-900 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (auth()->user()->isSupplier())
                @php $supplier = auth()->user()->supplierProfile; @endphp

                @if ($supplier && ! $supplier->isVerified())
                    <div class="bg-gold-50 border border-gold-100 rounded-2xl p-6 mb-6 flex items-start gap-4">
                        <span class="flex items-center justify-center w-10 h-10 rounded-full bg-gold-500 text-navy-900 shrink-0">
                            <x-icon name="shield" class="w-5 h-5" />
                        </span>
                        <div>
                            <h3 class="font-heading font-semibold text-navy-900">Verification pending</h3>
                            <p class="mt-1 text-sm text-navy-600 leading-relaxed max-w-2xl">
                                {{ $supplier->business_name }} is under review. Our team verifies every supplier before listings go live — we'll email you once it's approved.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-navy-100">
                    <div class="p-8">
                        <h3 class="font-heading text-lg font-semibold text-navy-900">
                            {{ __('Welcome, :name.', ['name' => explode(' ', auth()->user()->name)[0]]) }}
                        </h3>
                        <p class="mt-2 text-sm text-navy-500 leading-relaxed max-w-2xl">
                            {{ __('Your product listings, inventory, and orders will show up here once your account is verified.') }}
                        </p>
                    </div>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-navy-100">
                    <div class="p-8">
                        <h3 class="font-heading text-lg font-semibold text-navy-900">
                            {{ __('Welcome back, :name.', ['name' => explode(' ', auth()->user()->name)[0]]) }}
                        </h3>
                        <p class="mt-2 text-sm text-navy-500 leading-relaxed max-w-2xl">
                            {{ __("Your orders, delivery tracking, and Procurement Trust Score will show up here as soon as you place your first order.") }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
