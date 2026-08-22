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

                        @if ($supplier?->isVerified())
                            <p class="mt-2 text-sm text-navy-500">Manage your listings and fulfill orders from here.</p>

                            <div class="mt-6 grid sm:grid-cols-2 gap-4">
                                <a href="{{ route('supplier.products.index') }}" wire:navigate class="block p-5 rounded-xl border border-navy-100 hover:border-gold-300 hover:shadow-brand transition-all duration-200">
                                    <p class="font-heading font-bold text-2xl text-navy-900">{{ $productCount }}</p>
                                    <p class="text-sm text-navy-500 mt-1 flex items-center gap-1">
                                        My Products
                                        <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                                    </p>
                                </a>
                                <a href="{{ route('supplier.orders.index') }}" wire:navigate class="block p-5 rounded-xl border border-navy-100 hover:border-gold-300 hover:shadow-brand transition-all duration-200">
                                    <p class="font-heading font-bold text-2xl text-navy-900">{{ $pendingFulfillmentCount }}</p>
                                    <p class="text-sm text-navy-500 mt-1 flex items-center gap-1">
                                        Orders to Fulfill
                                        <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                                    </p>
                                </a>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-navy-500 leading-relaxed max-w-2xl">
                                {{ __('Your product listings, inventory, and orders will show up here once your account is verified.') }}
                            </p>
                        @endif
                    </div>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    @foreach ([
                        ['route' => 'orders.index', 'icon' => 'truck', 'label' => 'Order History'],
                        ['route' => 'addresses.index', 'icon' => 'map-pin', 'label' => 'My Addresses'],
                        ['route' => 'credit.index', 'icon' => 'star', 'label' => 'Trust &amp; Credit'],
                        ['route' => 'profile', 'icon' => 'shield', 'label' => 'Profile &amp; Security'],
                    ] as $action)
                        <a
                            href="{{ route($action['route']) }}"
                            wire:navigate
                            class="flex flex-col items-center text-center gap-2 p-4 bg-white rounded-2xl border border-navy-100 hover:border-gold-300 hover:shadow-brand hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-navy-50 text-navy-700">
                                <x-icon :name="$action['icon']" class="w-4.5 h-4.5" />
                            </span>
                            <span class="text-xs font-semibold text-navy-800">{!! $action['label'] !!}</span>
                        </a>
                    @endforeach
                </div>

                @if (auth()->user()->isContractor())
                    @php $contractor = auth()->user()->contractorProfile; @endphp

                    @if ($contractor && $contractor->verification_status !== 'verified')
                        <div @class([
                            'border rounded-2xl p-6 mb-6 flex items-start gap-4',
                            'bg-gold-50 border-gold-100' => $contractor->verification_status === 'pending',
                            'bg-red-50 border-red-100' => $contractor->verification_status === 'rejected',
                        ])>
                            <span @class([
                                'flex items-center justify-center w-10 h-10 rounded-full shrink-0',
                                'bg-gold-500 text-navy-900' => $contractor->verification_status === 'pending',
                                'bg-red-500 text-white' => $contractor->verification_status === 'rejected',
                            ])>
                                <x-icon name="shield" class="w-5 h-5" />
                            </span>
                            <div>
                                <h3 class="font-heading font-semibold text-navy-900">
                                    {{ $contractor->verification_status === 'rejected' ? 'Verification unsuccessful' : 'Verification pending' }}
                                </h3>
                                <p class="mt-1 text-sm text-navy-600 leading-relaxed max-w-2xl">
                                    @if ($contractor->verification_status === 'rejected')
                                        We couldn't verify your contractor account with the documents provided. Contact support to resubmit.
                                    @else
                                        Your contractor account is under review. Our team verifies identification and business details before full features unlock — we'll email you once it's approved.
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-navy-100 mb-6">
                        <div class="p-8">
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <h3 class="font-heading text-lg font-semibold text-navy-900">Your Projects</h3>
                                <a href="{{ route('projects.index') }}" wire:navigate class="text-sm font-semibold text-navy-700 hover:text-gold-600 transition-colors flex items-center gap-1">
                                    {{ $projectCount > 0 ? 'View all projects' : 'Create a project' }}
                                    <x-icon name="arrow-right" class="w-4 h-4" />
                                </a>
                            </div>

                            @if ($recentProjects->isEmpty())
                                <p class="mt-2 text-sm text-navy-500 leading-relaxed max-w-2xl">
                                    Group your orders into a Project to track spend per build against a budget.
                                </p>
                            @else
                                <ul class="mt-6 divide-y divide-navy-100 border border-navy-100 rounded-xl overflow-hidden">
                                    @foreach ($recentProjects as $project)
                                        <li>
                                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex items-center justify-between gap-4 p-4 hover:bg-navy-50 transition-colors">
                                                <div>
                                                    <p class="font-semibold text-navy-900 text-sm">{{ $project->name }}</p>
                                                    <p class="text-xs text-navy-400 mt-0.5">{{ $project->orders_count }} {{ Str::plural('order', $project->orders_count) }}</p>
                                                </div>
                                                <span @class([
                                                    'text-xs font-semibold px-3 py-1 rounded-full',
                                                    'bg-green-100 text-green-700' => $project->status === 'active',
                                                    'bg-navy-100 text-navy-600' => $project->status !== 'active',
                                                ])>
                                                    {{ ucfirst($project->status) }}
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-navy-100 mb-6">
                    <a href="{{ route('credit.index') }}" wire:navigate class="p-6 flex items-center justify-between gap-4 hover:bg-navy-50 transition-colors">
                        <div class="flex items-center gap-4">
                            <span class="flex items-center justify-center w-12 h-12 rounded-full bg-gold-50 text-gold-800 shrink-0">
                                <span class="font-heading font-bold text-sm">{{ $trustScore?->score ?? 0 }}</span>
                            </span>
                            <div>
                                <p class="font-heading font-semibold text-navy-900 text-sm">{{ ucfirst($trustScore?->tier ?? 'unrated') }} Tier</p>
                                <p class="text-xs text-navy-400 mt-0.5">Procurement Trust Score &amp; Selbuildi Credit</p>
                            </div>
                        </div>
                        <x-icon name="arrow-right" class="w-4 h-4 text-navy-300 shrink-0" />
                    </a>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-navy-100">
                    <div class="p-8">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <h3 class="font-heading text-lg font-semibold text-navy-900">
                                {{ __('Welcome back, :name.', ['name' => explode(' ', auth()->user()->name)[0]]) }}
                            </h3>
                            @if ($orderCount > 0)
                                <a href="{{ route('orders.index') }}" wire:navigate class="text-sm font-semibold text-navy-700 hover:text-gold-600 transition-colors flex items-center gap-1">
                                    View all orders
                                    <x-icon name="arrow-right" class="w-4 h-4" />
                                </a>
                            @endif
                        </div>

                        @if ($recentOrders->isEmpty())
                            <p class="mt-2 text-sm text-navy-500 leading-relaxed max-w-2xl">
                                {{ __("Your orders, delivery tracking, and Procurement Trust Score will show up here as soon as you place your first order.") }}
                            </p>
                            <a href="{{ route('shop.index') }}" wire:navigate>
                                <x-primary-button class="mt-5">Shop Materials</x-primary-button>
                            </a>
                        @else
                            <p class="mt-2 text-sm text-navy-500">{{ $orderCount }} {{ Str::plural('order', $orderCount) }} placed so far.</p>

                            <ul class="mt-6 divide-y divide-navy-100 border border-navy-100 rounded-xl overflow-hidden">
                                @foreach ($recentOrders as $order)
                                    <li>
                                        <a href="{{ route('orders.show', $order) }}" wire:navigate class="flex items-center justify-between gap-4 p-4 hover:bg-navy-50 transition-colors">
                                            <div>
                                                <p class="font-semibold text-navy-900 text-sm">{{ $order->order_number }}</p>
                                                <p class="text-xs text-navy-400 mt-0.5">{{ $order->placed_at->format('M j, Y') }}</p>
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
                                                <span class="font-semibold text-navy-900 text-sm">{{ $order->formattedTotal() }}</span>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
