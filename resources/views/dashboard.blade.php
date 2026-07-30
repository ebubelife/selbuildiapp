<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading font-semibold text-xl text-navy-900 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
        </div>
    </div>
</x-app-layout>
