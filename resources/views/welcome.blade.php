<x-layouts.site
    title="Selbuildi — Buy Building Materials Online in Cameroon"
    description="Buy cement, roofing sheets, steel & rebar, and tiles from verified suppliers in Cameroon. Track deliveries live and unlock procurement credit — for local and diaspora builders."
>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700 pt-32 pb-24 lg:pt-44 lg:pb-32">
        <div class="absolute inset-0 opacity-[0.06]" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 32px 32px;"></div>
        <div class="absolute -top-32 -right-32 w-[32rem] h-[32rem] rounded-full bg-gold-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-navy-500/30 blur-3xl"></div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="animate-fade-in-up">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/20 px-4 py-1.5 text-sm font-medium text-gold-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold-500"></span>
                        Now serving Cameroon
                    </span>

                    <h1 class="mt-6 font-heading text-4xl sm:text-5xl xl:text-6xl font-bold text-white leading-[1.1]">
                        Building the <span class="text-gold-500">infrastructure</span> of trust.
                    </h1>

                    <p class="mt-6 text-lg text-navy-200 leading-relaxed max-w-xl">
                        Buy building materials from verified suppliers, ship them straight to your site, and track every delivery in real time — whether you're building down the road or from abroad.
                    </p>

                    <div class="mt-10 flex flex-col sm:flex-row gap-4">
                        <a href="#categories">
                            <x-primary-button class="w-full sm:w-auto px-8 py-3.5 text-base">
                                Shop Materials
                                <x-icon name="arrow-right" class="w-4 h-4" />
                            </x-primary-button>
                        </a>
                        <a href="#trust-credit">
                            <button type="button" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-lg font-heading font-semibold text-base text-white border-2 border-white/30 hover:bg-white/10 transition-colors duration-150">
                                Learn about Credit
                            </button>
                        </a>
                    </div>
                </div>

                <div class="relative hidden lg:block animate-fade-in-up" style="animation-delay: 150ms">
                    <div class="relative bg-white rounded-2xl shadow-2xl p-6 rotate-2 hover:rotate-0 transition-transform duration-500 max-w-sm mx-auto">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-navy-500 uppercase tracking-wide">Order #SB-20481</span>
                            <span class="text-xs font-semibold text-gold-800 bg-gold-100 rounded-full px-2.5 py-1">In Transit</span>
                        </div>

                        <ul class="mt-6 space-y-5">
                            @foreach ([
                                ['label' => 'Order placed', 'done' => true],
                                ['label' => 'Confirmed by supplier', 'done' => true],
                                ['label' => 'Out for delivery', 'done' => true, 'current' => true],
                                ['label' => 'Delivered to site', 'done' => false],
                            ] as $step)
                                <li class="flex items-center gap-3">
                                    <span @class([
                                        'flex items-center justify-center w-6 h-6 rounded-full shrink-0',
                                        'bg-gold-500 text-navy-900' => $step['done'],
                                        'bg-navy-100 text-navy-400' => !$step['done'],
                                    ])>
                                        @if($step['done'])
                                            <x-icon name="check" class="w-3.5 h-3.5" stroke-width="2.5" />
                                        @endif
                                    </span>
                                    <span @class([
                                        'text-sm',
                                        'text-navy-900 font-semibold' => $step['current'] ?? false,
                                        'text-navy-700' => $step['done'] && !($step['current'] ?? false),
                                        'text-navy-400' => !$step['done'],
                                    ])>{{ $step['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="absolute -bottom-6 -left-10 bg-white rounded-xl shadow-xl px-5 py-4 flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-full bg-navy-700/10 text-navy-700">
                            <x-icon name="shield" class="w-5 h-5" />
                        </span>
                        <div>
                            <div class="font-heading font-bold text-navy-900 text-lg leading-none">98%</div>
                            <div class="text-xs text-navy-500 mt-1">On-time delivery</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust bar --}}
    <section class="bg-navy-800 py-14">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-8">
            <x-stat-counter :target="2400" suffix="+" label="Orders Fulfilled" />
            <x-stat-counter :target="85" suffix="+" label="Verified Suppliers" />
            <x-stat-counter :target="12" label="Cities Covered" />
            <x-stat-counter :target="98" suffix="%" label="On-time Delivery" />
        </div>
    </section>

    {{-- Categories --}}
    <section id="categories" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <x-reveal class="text-center max-w-2xl mx-auto">
                <span class="text-sm font-semibold text-gold-800 uppercase tracking-wide">Shop by Category</span>
                <h2 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-navy-900">Everything for your build, in one place</h2>
                <p class="mt-4 text-navy-500">From foundation to finishing — sourced from suppliers we've verified ourselves.</p>
            </x-reveal>

            <div class="mt-14 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5">
                @foreach ($categories as $i => $category)
                    <x-reveal :delay="$i * 60">
                        <a href="{{ route('shop.index', ['category' => $category->id]) }}" wire:navigate class="group flex flex-col items-center text-center gap-3 p-6 rounded-2xl border border-navy-100 hover:border-gold-500 hover:shadow-brand hover:-translate-y-1 transition-all duration-300">
                            <span class="flex items-center justify-center w-14 h-14 rounded-xl bg-navy-50 text-navy-700 group-hover:bg-gold-500 group-hover:text-navy-900 transition-colors duration-300">
                                <x-icon :name="$category->icon ?? 'cart'" class="w-7 h-7" />
                            </span>
                            <span class="text-sm font-semibold text-navy-800">{{ $category->name }}</span>
                        </a>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured products --}}
    <section class="py-24 bg-neutral-50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex items-end justify-between gap-6">
                <x-reveal>
                    <span class="text-sm font-semibold text-gold-800 uppercase tracking-wide">Featured Materials</span>
                    <h2 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-navy-900">Popular this week</h2>
                </x-reveal>
                <a href="{{ route('shop.index') }}" wire:navigate class="hidden sm:inline-flex items-center gap-1 text-navy-700 font-semibold hover:text-gold-600 transition-colors shrink-0">
                    View all <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>

            <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($featuredProducts as $i => $product)
                    <x-reveal :delay="$i * 80" class="group bg-white rounded-2xl border border-navy-100 overflow-hidden hover:shadow-brand hover:-translate-y-1 transition-all duration-300">
                        <a href="{{ route('shop.show', $product) }}" wire:navigate class="block">
                            <div class="aspect-square bg-navy-50 flex items-center justify-center relative overflow-hidden">
                                <x-icon :name="$product->category->icon ?? 'cart'" class="w-16 h-16 text-navy-300 group-hover:scale-110 group-hover:text-gold-500 transition-all duration-300" stroke-width="1.2" />
                            </div>
                            <div class="px-5 pt-5">
                                <h3 class="font-semibold text-navy-900 text-sm">{{ $product->name }}</h3>
                                <p class="text-xs text-navy-400 mt-1">per {{ $product->unit }}</p>
                            </div>
                        </a>
                        <div class="px-5 pb-5">
                            <div class="mt-3 flex items-center justify-between">
                                <span class="font-heading font-bold text-navy-900">{{ number_format($product->price) }} <span class="text-xs font-normal text-navy-400">XAF</span></span>
                                <livewire:quick-add-to-cart :product="$product" :key="'quick-add-'.$product->id" />
                            </div>
                        </div>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="py-24 bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <x-reveal class="text-center max-w-2xl mx-auto">
                <span class="text-sm font-semibold text-gold-800 uppercase tracking-wide">How it Works</span>
                <h2 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-navy-900">From order to site, fully tracked</h2>
            </x-reveal>

            <div class="mt-16 relative grid grid-cols-1 md:grid-cols-4 gap-10">
                <div class="hidden md:block absolute top-7 left-[12.5%] right-[12.5%] h-0.5 bg-navy-100"></div>

                @foreach ([
                    ['icon' => 'cart', 'title' => 'Browse & Order', 'text' => 'Compare prices across verified suppliers and order in a few taps.'],
                    ['icon' => 'shield', 'title' => 'Verified & Packed', 'text' => 'Your supplier confirms and prepares your materials for dispatch.'],
                    ['icon' => 'truck', 'title' => 'Track Delivery', 'text' => 'Follow your order in real time, from warehouse to your site.'],
                    ['icon' => 'check', 'title' => 'Build with Confidence', 'text' => 'Materials arrive as ordered — every step recorded and visible.'],
                ] as $i => $step)
                    <x-reveal :delay="$i * 120" class="relative text-center">
                        <div class="relative z-10 mx-auto flex items-center justify-center w-14 h-14 rounded-full bg-navy-700 text-white shadow-brand">
                            <x-icon :name="$step['icon']" class="w-6 h-6" />
                        </div>
                        <h3 class="mt-5 font-heading font-semibold text-navy-900">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm text-navy-500 leading-relaxed">{{ $step['text'] }}</p>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Diaspora --}}
    <section class="py-24 bg-navy-50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 grid lg:grid-cols-2 gap-16 items-center">
            <x-reveal>
                <span class="text-sm font-semibold text-gold-800 uppercase tracking-wide">For the Diaspora</span>
                <h2 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-navy-900">Building from abroad? See every step.</h2>
                <p class="mt-4 text-navy-500 leading-relaxed">
                    Stop sending money home and hoping it's spent right. Order materials directly, pay securely from wherever you are, and watch your project's delivery progress in real time — no middlemen, no guesswork.
                </p>
                <ul class="mt-6 space-y-3">
                    @foreach (['Pay in your currency, deliver in Cameroon', 'Live delivery tracking for every order', 'Share project access with family on the ground'] as $point)
                        <li class="flex items-center gap-3 text-navy-700">
                            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gold-100 text-gold-600 shrink-0">
                                <x-icon name="check" class="w-3.5 h-3.5" stroke-width="2.5" />
                            </span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </x-reveal>

            <x-reveal :delay="150" class="relative">
                <div class="bg-navy-900 rounded-2xl shadow-2xl p-6 max-w-sm mx-auto">
                    <div class="flex items-center gap-3 text-white">
                        <x-icon name="globe" class="w-6 h-6 text-gold-500" />
                        <span class="font-heading font-semibold">Project: Douala Family Home</span>
                    </div>
                    <div class="mt-6 space-y-4">
                        @foreach ([
                            ['label' => 'Cement & Blocks', 'pct' => 100],
                            ['label' => 'Roofing Materials', 'pct' => 65],
                            ['label' => 'Tiles & Finishing', 'pct' => 20],
                        ] as $line)
                            <div>
                                <div class="flex items-center justify-between text-xs text-navy-200 mb-1.5">
                                    <span>{{ $line['label'] }}</span>
                                    <span>{{ $line['pct'] }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-navy-700 overflow-hidden">
                                    <div
                                        x-data="{ w: 0 }"
                                        x-intersect.once="setTimeout(() => w = {{ $line['pct'] }}, 200)"
                                        :style="`width: ${w}%`"
                                        class="h-full bg-gold-500 rounded-full transition-all duration-1000 ease-out"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-reveal>
        </div>
    </section>

    {{-- Trust & Credit --}}
    <section id="trust-credit" class="py-24 bg-navy-900 relative overflow-hidden">
        <div class="absolute -bottom-32 -right-32 w-[28rem] h-[28rem] rounded-full bg-gold-500/10 blur-3xl"></div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 grid lg:grid-cols-2 gap-16 items-center">
            <x-reveal class="order-2 lg:order-1 flex justify-center">
                <div class="relative w-64 h-64">
                    <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#0A1B47" stroke-width="12" />
                        <circle
                            x-data="{ offset: 327 }"
                            x-intersect.once="setTimeout(() => offset = 327 - (327 * 0.72), 200)"
                            cx="60" cy="60" r="52" fill="none" stroke="#D99400" stroke-width="12"
                            stroke-linecap="round"
                            stroke-dasharray="327"
                            :stroke-dashoffset="offset"
                            style="transition: stroke-dashoffset 1.4s ease-out"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="font-heading text-4xl font-bold text-white">72</span>
                        <span class="text-xs text-gold-300 font-semibold uppercase tracking-wide mt-1">Gold Tier</span>
                    </div>
                </div>
            </x-reveal>

            <x-reveal :delay="120" class="order-1 lg:order-2">
                <span class="text-sm font-semibold text-gold-500 uppercase tracking-wide">Procurement Trust Score</span>
                <h2 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-white">Your purchase history becomes your credit line</h2>
                <p class="mt-4 text-navy-200 leading-relaxed">
                    Every order you complete builds your Procurement Trust Score. As it grows, you unlock better payment terms — from deposit-and-balance plans to full Net-30 credit on your materials.
                </p>
                <div class="mt-8 grid grid-cols-2 gap-4">
                    @foreach ([
                        ['tier' => 'Bronze', 'perk' => 'Eligible to apply for credit'],
                        ['tier' => 'Silver', 'perk' => '30% deposit, balance on delivery'],
                        ['tier' => 'Gold', 'perk' => 'Net-15 credit terms'],
                        ['tier' => 'Platinum', 'perk' => 'Net-30, higher limits'],
                    ] as $tier)
                        <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                            <div class="font-heading font-semibold text-gold-500 text-sm">{{ $tier['tier'] }}</div>
                            <div class="text-xs text-navy-200 mt-1 leading-relaxed">{{ $tier['perk'] }}</div>
                        </div>
                    @endforeach
                </div>
            </x-reveal>
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="py-24 bg-white">
        <div class="max-w-4xl mx-auto px-6 lg:px-8 text-center">
            <span class="text-sm font-semibold text-gold-800 uppercase tracking-wide">Testimonials</span>
            <h2 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-navy-900">Trusted by builders and their families</h2>

            <div
                x-data="{
                    active: 0,
                    items: [
                        { quote: 'I ordered every bag of cement for my house from London. My brother in Douala just had to sign for delivery.', name: 'Achille N.', role: 'Diaspora customer, UK' },
                        { quote: 'The trust score got my company Net-15 terms after our third order. That changed our cash flow completely.', name: 'Fatimatou B.', role: 'Contractor, Yaoundé' },
                        { quote: 'As a supplier, Selbuildi brought us consistent verified buyers without chasing payments.', name: 'Roger E.', role: 'Supplier partner' },
                    ]
                }"
                x-init="setInterval(() => active = (active + 1) % items.length, 6000)"
                class="mt-12 relative"
            >
                <template x-for="(item, index) in items" :key="index">
                    <div
                        x-show="active === index"
                        x-transition:enter="transition ease-out duration-500"
                        x-transition:enter-start="opacity-0 translate-y-3"
                        x-transition:enter-end="opacity-100 translate-y-0"
                    >
                        <p class="font-heading text-xl sm:text-2xl text-navy-800 leading-relaxed" x-text="'“' + item.quote + '”'"></p>
                        <div class="mt-6">
                            <div class="font-semibold text-navy-900" x-text="item.name"></div>
                            <div class="text-sm text-navy-400" x-text="item.role"></div>
                        </div>
                    </div>
                </template>

                <div class="mt-8 flex items-center justify-center gap-2">
                    <template x-for="(item, index) in items" :key="index">
                        <button
                            @click="active = index"
                            :class="active === index ? 'bg-gold-500 w-6' : 'bg-navy-200 w-2'"
                            class="h-2 rounded-full transition-all duration-300"
                        ></button>
                    </template>
                </div>
            </div>
        </div>
    </section>

    {{-- Supplier CTA --}}
    <section id="suppliers" class="py-20 bg-gold-500">
        <div class="max-w-5xl mx-auto px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-8">
            <div class="text-center sm:text-left">
                <h2 class="font-heading text-2xl sm:text-3xl font-bold text-navy-900">Are you a supplier?</h2>
                <p class="mt-2 text-navy-800/80">Join Selbuildi and reach verified buyers across Cameroon and the diaspora.</p>
            </div>
            <a href="{{ route('register') }}" wire:navigate class="shrink-0">
                <button type="button" class="inline-flex items-center gap-2 px-8 py-3.5 rounded-lg font-heading font-semibold bg-navy-900 text-white hover:bg-navy-800 hover:-translate-y-0.5 hover:shadow-lg transition-all duration-150">
                    Join as a Supplier
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </button>
            </a>
        </div>
    </section>

</x-layouts.site>
