<header
    x-data="{ scrolled: false, mobileOpen: false }"
    x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 24)"
    :class="scrolled ? 'bg-white/95 backdrop-blur shadow-sm' : 'bg-transparent'"
    class="fixed inset-x-0 top-0 z-50 transition-colors duration-300"
>
    <nav class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 shrink-0">
                <template x-if="!scrolled">
                    <x-application-logo variant="full" dark class="h-9 w-auto" />
                </template>
                <template x-if="scrolled">
                    <x-application-logo variant="full" class="h-9 w-auto" />
                </template>
            </a>

            <div class="hidden lg:flex items-center gap-10">
                <a href="{{ route('shop.index') }}" wire:navigate :class="scrolled ? 'text-navy-700 hover:text-gold-600' : 'text-white/90 hover:text-white'" class="text-sm font-medium transition-colors">Shop Materials</a>
                <a href="#how-it-works" :class="scrolled ? 'text-navy-700 hover:text-gold-600' : 'text-white/90 hover:text-white'" class="text-sm font-medium transition-colors">How it Works</a>
                <a href="#trust-credit" :class="scrolled ? 'text-navy-700 hover:text-gold-600' : 'text-white/90 hover:text-white'" class="text-sm font-medium transition-colors">Credit &amp; Trust</a>
                <a href="#suppliers" :class="scrolled ? 'text-navy-700 hover:text-gold-600' : 'text-white/90 hover:text-white'" class="text-sm font-medium transition-colors">Suppliers</a>
            </div>

            <div class="hidden lg:flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-primary-button>Dashboard</x-primary-button>
                    </a>
                @else
                    <a href="{{ route('login') }}" wire:navigate :class="scrolled ? 'text-navy-700' : 'text-white'" class="text-sm font-semibold px-4 py-2.5 transition-colors">Log in</a>
                    <a href="{{ route('register') }}" wire:navigate>
                        <x-primary-button>Get Started</x-primary-button>
                    </a>
                @endauth
            </div>

            <button
                @click="mobileOpen = !mobileOpen"
                :class="scrolled ? 'text-navy-700' : 'text-white'"
                class="lg:hidden p-2"
                aria-label="Toggle menu"
            >
                <x-icon :name="'menu'" x-show="!mobileOpen" class="w-7 h-7" />
                <x-icon :name="'close'" x-show="mobileOpen" x-cloak class="w-7 h-7" />
            </button>
        </div>
    </nav>

    <div
        x-show="mobileOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="lg:hidden bg-white shadow-lg border-t border-navy-100"
        @click.outside="mobileOpen = false"
    >
        <div class="px-6 py-6 space-y-4">
            <a href="{{ route('shop.index') }}" wire:navigate @click="mobileOpen = false" class="block text-navy-700 font-medium">Shop Materials</a>
            <a href="#how-it-works" @click="mobileOpen = false" class="block text-navy-700 font-medium">How it Works</a>
            <a href="#trust-credit" @click="mobileOpen = false" class="block text-navy-700 font-medium">Credit &amp; Trust</a>
            <a href="#suppliers" @click="mobileOpen = false" class="block text-navy-700 font-medium">Suppliers</a>
            <div class="pt-4 border-t border-navy-100 flex flex-col gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" wire:navigate><x-primary-button class="w-full">Dashboard</x-primary-button></a>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="text-center text-navy-700 font-semibold py-2">Log in</a>
                    <a href="{{ route('register') }}" wire:navigate><x-primary-button class="w-full">Get Started</x-primary-button></a>
                @endauth
            </div>
        </div>
    </div>
</header>
