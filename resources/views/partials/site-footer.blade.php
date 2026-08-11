<footer class="bg-navy-900 text-navy-200">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
            <div class="md:col-span-1">
                <x-application-logo variant="full" dark class="h-10 w-auto" />
                <p class="mt-4 text-sm leading-relaxed text-navy-300">
                    Building the infrastructure of trust — commerce, logistics, and finance for construction across Cameroon.
                </p>
            </div>

            <div>
                <h3 class="font-heading font-semibold text-white text-sm uppercase tracking-wide">Shop</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="{{ route('shop.index') }}" wire:navigate class="hover:text-gold-500 transition-colors">Categories</a></li>
                    <li><a href="{{ route('shop.index') }}" wire:navigate class="hover:text-gold-500 transition-colors">Featured Materials</a></li>
                    <li><a href="{{ route('home') }}#trust-credit" class="hover:text-gold-500 transition-colors">Procurement Credit</a></li>
                </ul>
            </div>

            <div>
                <h3 class="font-heading font-semibold text-white text-sm uppercase tracking-wide">Company</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="#how-it-works" class="hover:text-gold-500 transition-colors">How it Works</a></li>
                    <li><a href="#suppliers" class="hover:text-gold-500 transition-colors">Become a Supplier</a></li>
                    <li><a href="#" class="hover:text-gold-500 transition-colors">Contact</a></li>
                </ul>
            </div>

            <div>
                <h3 class="font-heading font-semibold text-white text-sm uppercase tracking-wide">Stay Updated</h3>
                <p class="mt-4 text-sm text-navy-300">Get updates on new suppliers and material deals.</p>
                <form class="mt-4 flex gap-2">
                    <input type="email" placeholder="Your email" class="min-w-0 flex-1 rounded-lg border-navy-700 bg-navy-800 text-white placeholder-navy-300 text-sm focus:border-gold-500 focus:ring-gold-500">
                    <button type="submit" class="shrink-0 rounded-lg bg-gold-500 hover:bg-gold-600 transition-colors px-4 py-2 text-sm font-semibold text-navy-900">
                        Join
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-12 pt-8 border-t border-navy-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-navy-300">
            <p>&copy; {{ date('Y') }} Selbuildi. All rights reserved.</p>
            <p>Made for builders in Cameroon and the diaspora.</p>
        </div>
    </div>
</footer>
